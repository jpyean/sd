<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-30
 * Time: 下午3:46
 */

namespace app\Models;

use app\OneException;
use Server\Asyn\Mysql\Miner;

/**
 * 奖品的期数
 * Class GoodsPhase
 * @package app\Models
 */
class GoodsPhase extends BaseModel
{
    const RUN_LUCKY_DELAY = 60;
    protected $redis_lucky = 'lucky_list';
    protected $sql_table = 'goods_phase';
    /**
     * @var Goods
     */
    protected $Goods;
    /**
     * @var Coupon
     */
    protected $Coupon;
    /**
     * @var Order
     */
    protected $Order;
    /**
     * @var User
     */
    protected $User;
    /**
     * @var PhaseRecord
     */
    protected $PhaseRecord;
    /**
     * @var DeliverGoodsOrder
     */
    protected $DeliverGoodsOrder;

    public function initialization(&$context)
    {
        parent::initialization($context);
        $this->Goods = $this->loader->model('Goods', $this);
        $this->Coupon = $this->loader->model('Coupon', $this);
        $this->User = $this->loader->model('User', $this);
        $this->Order = $this->loader->model('Order', $this);
        $this->PhaseRecord = $this->loader->model('PhaseRecord', $this);
        $this->DeliverGoodsOrder = $this->loader->model('DeliverGoodsOrder', $this);
    }

    /**
     * 开启商品的下一期
     * @param $goods_id
     * @return array
     * @throws OneException
     */
    public function createNextPhase($goods_id)
    {
        $goods_info = yield $this->Goods->getGoodsInfo($goods_id);
        yield $this->Goods->isAuditThrough(null,$goods_info);
        $goods_now_phase = $goods_info['goods_now_phase'];
        if ($goods_now_phase == 0) {//还没有期数,那就创建第一期
            $goods_now_phase++;
            $result = yield $this->createPhase($goods_id, $goods_now_phase, $goods_info['goods_money'], $goods_info['shop_id']);
        } else {//有往期，那么要判断往期有没有结束
            $phaseInfo = yield $this->getGoodsPhaseInfo($goods_id, $goods_now_phase);
            if (empty($phaseInfo['winer_uid'])) {//代表没有开奖，那么不能创建下一期
                throw new OneException('上一期还没结束，不能创建下一期');
            } else if ($goods_info['left_phase'] == 0) {
                throw new OneException('没有库存，不能继续创建');
            } else {
                $goods_now_phase++;
                $result = yield $this->createPhase($goods_id, $goods_now_phase, $goods_info['goods_money'], $goods_info['shop_id']);
            }
        }
        //创建成功后要更新数据库
        yield $this->Goods->updateGoodsInfoNextPhase($goods_id);
        return $result;
    }

    /**
     * 创建期数
     * @param $goods_id
     * @param $phase
     * @param $goods_money
     * @param $shop_id
     * @return array
     */
    private function createPhase($goods_id, $phase, $goods_money, $shop_id)
    {
        $phase_id = $this->helpMathPhaseId($goods_id, $phase);
        //创建奖卷池
        yield $this->Coupon->createCouponBox($phase_id, $goods_money);
        //插入到mysql中记录
        $result = [
            'phase_id' => $phase_id,
            'goods_id' => $goods_id,
            'shop_id' => $shop_id,
            'phase' => $phase,
            'need_money' => $goods_money,
            'now_money' => 0
        ];
        yield $this->mysql_pool->dbQueryBuilder->insert($this->sql_table)->set($result)->coroutineSend();
        return $result;
    }

    /**
     * 收入了钱（钱足够就开奖）
     * @param $uid
     * @param $phase_id
     * @param $money
     * @return \Generator
     */
    public function phaseGetMoney($uid, $phase_id, $money)
    {
        //添加购买记录
        yield $this->PhaseRecord->addRecord($uid, $phase_id, $money);
        //更新期数总金钱判断是否能开奖
        yield $this->mysql_pool->dbQueryBuilder->coroutineSend(null,
            "UPDATE $this->sql_table SET now_money = now_money+$money WHERE phase_id = '$phase_id'");
        $phase_info = yield $this->getGoodsPhaseInfo(null,null,$phase_id);
        if ($phase_info['need_money'] == $phase_info['now_money']) {//可以开奖了,扔到开奖队列中去
            $redis_info = ['time'=>time(),'phase_id'=>$phase_id];
            yield $this->redis_pool->getCoroutine()->lPush($this->redis_lucky,json_encode($redis_info));
        }
    }

    /**
     * 定时器调用开奖
     */
    public function timerRunLucky()
    {
        $redis_json_info = yield $this->redis_pool->getCoroutine()->lPop($this->redis_lucky);
        if($redis_json_info==null){
            return;
        }
        $redis_info = json_decode($redis_json_info,true);
        $time = $redis_info['time'];
        if(time()-$time>self::RUN_LUCKY_DELAY){//可以开奖了
            $phase_info = yield $this->getGoodsPhaseInfo(null,null,$redis_info['phase_id']);
            yield $this->runLucky($phase_info);
        }else{//扔回队列
            yield $this->redis_pool->getCoroutine()->lPush($this->redis_lucky,$redis_json_info);
        }
    }

    /**
     * 开奖
     * @param $phase_info
     * @return \Generator
     */
    protected function runLucky($phase_info)
    {
        $phase_id = $phase_info['phase_id'];
        $lucky_coupon = yield $this->luckyMath($phase_id, 50, $phase_info['need_money']);
        $order_info = yield $this->Coupon->couponRun($phase_id, $lucky_coupon);
        $uid = $order_info['uid'];
        $lucky_time = date("Y-m-d H:i:s");
        //获取这个用户一共购买了多少
        $winer_record = yield $this->PhaseRecord->getRecord($uid, $phase_id);
        $winer_buy_count = $winer_record['pay_total_coupon'];
        //更新中奖用户
        yield $this->mysql_pool->dbQueryBuilder->update($this->sql_table)
            ->set([
                'winer_uid' => $order_info['uid'],
                'winer_ip' => $order_info['ip'],
                'winer_address' => $order_info['ip_address'],
                'winer_buy_count' => $winer_buy_count,
                'winer_name' => $order_info['user_name'],
                'winer_icon' => $order_info['user_icon'],
                'lucky_time' => $lucky_time,
                'lucky_coupon' => $lucky_coupon,
                'lucky_order_id' => $order_info['order_id'],
            ])->where('phase_id', $phase_id)->coroutineSend();
        //添加发货订单
        yield $this->DeliverGoodsOrder->addDeliverGoodsOrder($order_info, $winer_record, $lucky_time);
        //发送给用户
        yield $this->User->noticeWinning($uid);
        //更新下记录,奖期状态为结束状态
        yield $this->PhaseRecord->updateOverType($phase_id);
        //自动开启下一期
        try {
            yield $this->createNextPhase($phase_info['goods_id']);
        } catch (\Exception $e) {

        }
    }

    /**
     * 开奖算法
     * @param $phase_id
     * @param $times
     * @param $money
     * @return int
     */
    protected function luckyMath($phase_id, $times, $money)
    {
        $sum = yield $this->Order->getSumOrderTime($phase_id, $times);
        $lucky_coupon = $sum % $money + 10000001;
        return $lucky_coupon;
    }

    /**
     * 获取期数信息
     * @param $goods_id
     * @param $phase
     * @param null $phase_id
     * @return mixed
     */
    public function getGoodsPhaseInfo($goods_id, $phase, $phase_id = null)
    {
        if ($phase_id == null) {
            $phase_id = $this->helpMathPhaseId($goods_id, $phase);
        }
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from($this->sql_table)->where('phase_id', $phase_id)->coroutineSend();
        $this->judgeMySQLHaveValue($result, '期数不存在');
        return $result['result'][0];
    }

    /**
     * 获取关于次物品的所有期数信息
     * @param $goods_id
     * @param $limit
     * @param $page
     * @return array
     */
    public function getGoodsPhaseInfos($goods_id, $limit, $page)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from($this->sql_table)->where('goods_id', $goods_id)
            ->orderBy('phase', Miner::ORDER_BY_DESC)->limit($limit, ($page - 1) * $limit)->coroutineSend();
        return $result['result'];
    }

    /**
     * 寻找shop的信息
     * @param $shop_id
     * @param $limit
     * @param $page
     * @return mixed
     */
    public function getGoodsPhaseFromShopId($shop_id,$limit,$page)
    {
        $offset = ($page-1)*$limit;
        $result = yield $this->mysql_pool->dbQueryBuilder->coroutineSend(null,
            "SELECT * FROM goods_phase INNER JOIN deliver_goods_order ON goods_phase.lucky_order_id = deliver_goods_order.order_id WHERE goods_phase.shop_id = $shop_id LIMIT $limit OFFSET $offset"
        );
        return $result['result'];
    }

    /**
     * 获取总页数
     * @param $shop_id
     * @param $limit
     * @return mixed
     */
    public function getGoodsPhaseFromShopIdPages($shop_id,$limit)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->coroutineSend(null,
            "SELECT count(*) as total FROM goods_phase WHERE goods_phase.shop_id = $shop_id"
        );
        return ceil($result['result'][0]['total']/$limit);
    }

    /**
     * 计算PhaseId
     * @param $goods_id
     * @param $phase
     * @return string
     */
    public function helpMathPhaseId($goods_id, $phase)
    {
        return $goods_id . '-' . $phase;
    }

    /**
     * @param $phase_id
     * @return array
     */
    public function helpGoodsIDPhase($phase_id)
    {
        list($goods_id, $phase) = explode('-', $phase_id);
        return ['goods_id' => $goods_id, 'phase' => $phase];
    }

    /**
     * 删除Goodsid的信息（危险）
     * @param $goods_id
     * @return \Generator
     */
    public function delAllGoodsId($goods_id)
    {
        return yield $this->mysql_pool->dbQueryBuilder->delete()->from($this->sql_table)->where('good_id', $goods_id)->coroutineSend();
    }

    /**
     * 删除ShopId的信息（危险）
     * @param $shop_id
     * @return \Generator
     */
    public function delAllShopId($shop_id)
    {
        return yield $this->mysql_pool->dbQueryBuilder->delete()->from($this->sql_table)->where('shop_id', $shop_id)->coroutineSend();
    }
}