<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-31
 * Time: 上午10:54
 */

namespace app\Models;


use app\OneException;
use Server\Asyn\Mysql\Miner;

class Order extends BaseModel
{
    const PAY_STATUS_NORMAL = 0;
    const PAY_STATUS_IS_PAY = 1;
    const PAY_STATUS_IS_CANCEL = 2;
    const PAY_STATUS_NEED_REFUND = 3;
    const PAY_STATUS_IS_REFUND = 4;

    protected $mysql_table = 'order_form';
    //订单队列用于自动取消超时订单
    protected $redis_temp_order_table = 'temp_order';
    //用户奖卷信息
    protected $redis_order_phase_coupon_table = 'order_phase_coupon_';
    protected $order_pay_timeout = 120;
    /**
     * @var Coupon
     */
    protected $Coupon;
    /**
     * @var GoodsPhase
     */
    protected $GoodsPhase;
    /**
     * @var Goods
     */
    protected $Goods;


    public function initialization(&$context)
    {
        parent::initialization($context);
        $this->Coupon = $this->loader->model('Coupon', $this);
        $this->GoodsPhase = $this->loader->model('GoodsPhase', $this);
        $this->Goods = $this->loader->model('Goods', $this);
    }

    /*
    * microsecond 微秒     millisecond 毫秒
    *返回时间戳的毫秒数部分
    */
    protected function get_millisecond()
    {
        list($usec, $sec) = explode(" ", microtime());
        $msec = (string)(1000 + round($usec * 1000));
        return (int)(date("His") . substr($msec,1));
    }

    /**
     * 创建一个订单，返回需要支付的钱
     * @param $uid
     * @param $shop_id
     * @param $goods_id
     * @param $phase_id
     * @param $want_money
     * @param $user_name
     * @param $user_icon
     * @param $ip
     * @param $ip_address
     * @return array
     * @throws OneException
     */
    public function createOrder($uid, $shop_id, $goods_id, $phase_id, $want_money, $user_name, $user_icon, $ip, $ip_address)
    {
        //看看这个用户有没有没支付或者没取消的订单
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from($this->mysql_table)->where('uid', $uid)->andWhere('is_pay', 0)->coroutineSend();
        if (count($result['result']) != 0) {
            foreach ($result['result'] as $orderInfo) {
                yield $this->cancelOrder($orderInfo);
            }
        }
        //判断下商品有没有过审
        yield $this->Goods->isAuditThrough($goods_id);
        //期数问题查询
        $goods_phase_info = yield $this->GoodsPhase->getGoodsPhaseInfo(null, null, $phase_id);
        if ($goods_phase_info['winer_uid'] != null) {
            throw new OneException('该期数已开奖');
        }
        if ($goods_phase_info['now_money'] == $goods_phase_info['need_money']) {
            throw new OneException('该期数已全部购买完，等待开奖中');
        }
        //获取奖卷
        $coupons = yield $this->Coupon->getCoupon($phase_id, $want_money);
        //获取真正需要支付的钱
        $money = count($coupons);
        if ($money == 0) {
            if ($goods_phase_info['now_money'] != $goods_phase_info['need_money']) {
                throw new OneException('奖票空了，但存在未支付的订单，可以稍后重新尝试购买');
            } else {
                throw new OneException('创建订单失败');
            }
        }
        $order_id = yield $this->createOrderID();
        //将奖卷存在redis中
        yield $this->redis_pool->getCoroutine()->sAdd($this->redis_order_phase_coupon_table . $order_id, $coupons);
        $orderInfo = [
            'order_id' => $order_id,
            'shop_id' => $shop_id,
            'goods_id' => $goods_id,
            'phase_id' => $phase_id,
            'money' => $money,
            'uid' => $uid,
            'order_time' => $this->get_millisecond(),
            'order_data_time' => date('Y-m-d H:i:s'),
            'user_name' => $user_name,
            'ip' => $ip,
            'ip_address' => $ip_address,
            'user_icon' => $user_icon,
            'is_pay' => 0
        ];
        //订单扔到临时队列中
        yield $this->redis_pool->getCoroutine()->rPush($this->redis_temp_order_table, json_encode([
            'phase_id' => $orderInfo['phase_id'],
            'order_time' => $orderInfo['order_time'],
            'order_id' => $order_id,]));
        //订单写数据库
        yield $this->mysql_pool->dbQueryBuilder->insert($this->mysql_table)->set($orderInfo)->coroutineSend();
        return $orderInfo;
    }

    /**
     * 确定支付了订单
     * @param $order_id
     * @param $money
     * @return string
     */
    public function confirmOrder($order_id, $money)
    {
        $id = yield $this->mysql_pool->coroutineBegin($this);
        $result = yield $this->mysql_pool->dbQueryBuilder->select("*")->from($this->mysql_table)->where('order_id', $order_id)->coroutineSend($id);
        if (count($result['result']) == 0) {//订单不存在
            yield $this->mysql_pool->coroutineRollback($id);
            $result = '订单不存在';
            return $result;
        }
        $order_info = $result['result'][0];
        switch ($order_info['is_pay']) {
            case self::PAY_STATUS_NORMAL://待处理
                if ($order_info['money'] != $money) {//2者金钱不统一就直接走退款流程
                    $result = '金钱不对，进入退款状态';
                    //更新订单状态
                    yield $this->mysql_pool->dbQueryBuilder->update($this->mysql_table)
                        ->set(['is_pay' => self::PAY_STATUS_NEED_REFUND])->where('order_id', $order_id)->coroutineSend($id);
                    yield $this->mysql_pool->coroutineCommit($id);
                    //取消订单
                    yield $this->cancelOrder($order_info);
                    break;
                }
                //更新订单状态
                yield $this->mysql_pool->dbQueryBuilder->update($this->mysql_table)
                    ->set(['is_pay' => self::PAY_STATUS_IS_PAY])->where('order_id', $order_id)->coroutineSend($id);
                yield $this->mysql_pool->coroutineCommit($id);
                //奖票和用户进行绑定
                $coupons = yield $this->getOrderCoupons($order_id);
                yield $this->Coupon->couponBindUid($order_info, $coupons);
                //更新奖期金钱数
                yield $this->GoodsPhase->phaseGetMoney($order_info['uid'], $order_info['phase_id'], $order_info['money']);
                $result = '支付成功';
                break;
            case self::PAY_STATUS_IS_CANCEL://取消订单，订单已取消但是来了支付成功的回调需要退款
                //更新订单状态
                yield $this->mysql_pool->dbQueryBuilder->update($this->mysql_table)
                    ->set(['is_pay' => self::PAY_STATUS_NEED_REFUND])->where('order_id', $order_id)->coroutineSend($id);
                yield $this->mysql_pool->coroutineCommit($id);
                $result = '订单已被取消，进入退款状态';
                break;
            case self::PAY_STATUS_IS_PAY://已支付的话还来回调就不管了
            case self::PAY_STATUS_NEED_REFUND:
            case self::PAY_STATUS_IS_REFUND:
                $result = '订单处于无效状态，不进行任何处理';
                yield $this->mysql_pool->coroutineRollback($id);
                break;
            default:
                $result = '未知订单状态，不进行任何处理';
                yield $this->mysql_pool->coroutineRollback($id);
        }
        return $result;
    }

    /**
     * 取消订单
     * @param $orderInfo
     * @return \Generator
     * @throws OneException
     */
    public function cancelOrder($orderInfo)
    {
        $couponTable = $this->Coupon->getRedisTable($orderInfo['phase_id']);
        $order_phase_coupon_table = $this->redis_order_phase_coupon_table . $orderInfo['order_id'];
        yield $this->redis_pool->getCoroutine()->evalSha(getLuaSha1('cancel_order'), [$couponTable, $order_phase_coupon_table], 2);
        //更新订单状态
        yield $this->mysql_pool->dbQueryBuilder->update($this->mysql_table)
            ->set(['is_pay' => 2])->where('order_id', $orderInfo['order_id'])->coroutineSend();
    }

    /**
     * 定时取消未支付的订单
     */
    public function timerCancelOrder()
    {
        while (true) {
            $order_info_json = yield $this->redis_pool->getCoroutine()->lPop($this->redis_temp_order_table);
            if (empty($order_info_json)) {
                break;
            }
            $orderInfo = json_decode($order_info_json, true);
            if (time() - $orderInfo['order_time'] > $this->order_pay_timeout) {//订单过时了
                $order_phase_coupon_table = $this->redis_order_phase_coupon_table . $orderInfo['order_id'];
                $isExist = yield $this->redis_pool->getCoroutine()->exists($order_phase_coupon_table);
                if ($isExist) {//看是否存在，不存在代表已经取消过了
                    $newOrderInfo = yield $this->getOrderInfo($orderInfo['order_id']);
                    if ($newOrderInfo['is_pay'] != self::PAY_STATUS_IS_PAY) {//没有支付或者不是支付状态
                        yield $this->cancelOrder($orderInfo);
                    }
                }
            } else {//没有过时扔回去
                yield $this->redis_pool->getCoroutine()->lPush($this->redis_temp_order_table, $order_info_json);
                break;
            }
        }
    }

    /**
     * 获取订单详情
     * @param $order_id
     * @return mixed
     */
    public function getOrderInfo($order_id)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from($this->mysql_table)->where('order_id', $order_id)->coroutineSend();
        $this->judgeMySQLHaveValue($result, '订单不存在');
        return $result['result'][0];
    }

    /**
     * 获取奖期的已支付订单信息
     * @param $phase_id
     * @param $limit
     * @param $page
     * @return mixed
     */
    public function getPhaseOrderRecord($phase_id, $limit, $page)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from($this->mysql_table)->where('phase_id', $phase_id)->andWhere('is_pay', 1)->limit($limit, ($page - 1) * $limit)->coroutineSend();
        return $result['result'];
    }

    /**
     * 获取用户订单
     * @param $phase_id
     * @param $uid
     * @return mixed
     */
    public function getUserPhaseOrderRecord($phase_id, $uid)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from($this->mysql_table)->where('phase_id', $phase_id)->andWhere('is_pay', 1)->andWhere('uid', $uid)->coroutineSend();
        return $result['result'];
    }

    /**
     * 开奖算法
     * 获取最后$times个订单时间之和
     * @param $phase_id
     * @param $times
     * @return int
     */
    public function getSumOrderTime($phase_id, $times)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->coroutineSend(null, "
          SELECT SUM(order_time) AS total FROM order_form WHERE phase_id = '$phase_id' AND is_pay = 1 ORDER BY order_time DESC LIMIT $times
        ");
        if (count($result['result']) == 0) {
            return 0;
        }
        return $result['result'][0]['total'] == null ? 0 : $result['result'][0]['total'];
    }

    /**
     * 获取最后$times个订单
     * @param $phase_id
     * @param $times
     * @return mixed
     */
    public function getLastNumOrder($phase_id, $times)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from($this->mysql_table)
            ->where('phase_id', $phase_id)->andWhere('is_pay', self::PAY_STATUS_IS_PAY)
            ->orderBy('order_time', Miner::ORDER_BY_DESC)->limit($times)->coroutineSend();
        $this->judgeMySQLHaveValue($result, '订单不存在');
        return $result['result'];
    }

    /**
     * 获取该订单的奖卷情况
     * @param $order_id
     * @return mixed
     */
    public function getOrderCoupons($order_id)
    {
        $result = yield $this->redis_pool->getCoroutine()->sMembers($this->redis_order_phase_coupon_table . $order_id);
        return $result;
    }

    /**
     * 创建一个订单id
     * @return string
     */
    protected function createOrderID()
    {
        $time = date("YmdHis");
        return $this->redis_pool->getCoroutine()->evalSha(getLuaSha1('create_order_id'), [$time], 1);
    }
}