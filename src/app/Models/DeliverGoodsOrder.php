<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-31
 * Time: 上午10:54
 */

namespace app\Models;

/**
 * 发货订单
 * Class DeliverGoodsOrder
 * @package app\Models
 */
class DeliverGoodsOrder extends BaseModel
{
    const TYPE_NORMAL = 1;
    const TYPE_WAITING_SEND_GOODS = 2;
    const TYPE_WAITING_GET_GOODS = 3;
    const TYPE_COMPLETE = 4;

    protected $table = 'deliver_goods_order';
    /**
     * @var PhaseRecord
     */
    protected $PhaseRecord;
    /**
     * @var Address
     */
    protected $Address;
    /**
     * @var Goods
     */
    protected $Goods;
    /**
     * @var GoodsPhase
     */
    protected $GoodsPhase;

    public function initialization(&$context)
    {
        parent::initialization($context);
        $this->PhaseRecord = $this->loader->model('PhaseRecord', $this);
        $this->Address = $this->loader->model('Address', $this);
        $this->Goods = $this->loader->model('Goods', $this);
        $this->GoodsPhase = $this->loader->model('GoodsPhase', $this);
    }

    /**
     * 添加一个发货订单
     * @param $order_info
     * @param $phase_record
     * @return \Generator
     */
    public function addDeliverGoodsOrder($order_info, $phase_record, $lucky_time)
    {
        $goods_info = yield $this->Goods->getGoodsInfo($order_info['goods_id']);
        $goods_phase = $this->GoodsPhase->helpGoodsIDPhase($order_info['phase_id'])['phase'];
        $info = [
            'order_id' => $order_info['order_id'],
            'uid' => $order_info['uid'],
            'order_type' => self::TYPE_NORMAL,
            'shop_id' => $order_info['shop_id'],
            'phase_id' => $order_info['phase_id'],
            'goods_id' => $order_info['goods_id'],
            'phase_record_id' => $phase_record['phase_record_id'],
            'lucky_time' => $lucky_time,
            'goods_icon' => $goods_info['goods_icons'],
            'goods_name' => $goods_info['goods_name'],
            'goods_money' => $goods_info['goods_money'],
            'goods_phase' => $goods_phase,
            'user_buy_money' => $phase_record['pay_total_coupon'],
            'order_data_time' => $order_info['order_data_time']
        ];
        yield $this->mysql_pool->dbQueryBuilder->insert($this->table)->set($info)->coroutineSend();
    }

    /**
     * 获取用户所有状态信息
     * @param $uid
     * @return \Generator
     */
    public function getUserAllTypeCount($uid)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->coroutineSend(null,
            "SELECT order_type ,COUNT(*) AS count FROM $this->table WHERE uid = $uid GROUP BY order_type");
        return $result['result'];
    }

    /**
     * 获取类别的具体信息
     * @param $uid
     * @param $type
     * @param $limit
     * @param $page
     * @return mixed
     */
    public function getUserTypeInfo($uid, $type, $limit, $page)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from($this->table)->where('uid', $uid)->andWhere('order_type', $type)
            ->limit($limit, ($page - 1) * $limit)->coroutineSend();
        return $result['result'];
    }

    /**
     * 根据订单号获取信息
     * @param $order_id
     * @return mixed
     */
    public function getInfoFromOrderId($order_id)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from($this->table)->where("order_id", $order_id)->coroutineSend();
        return $result['result'][0];
    }

    /**
     * Normal->WaitingSend
     * @param $order_id
     * @param $address_id
     * @param $remarks
     * @return \Generator
     */
    public function typeNormalHandle($order_id, $address_id, $remarks)
    {
        $address_info = yield $this->Address->getAddress($address_id);
        $update_info = [
            'user_remarks' => $remarks,
            'user_address' => $address_info['user_address'],
            'user_name' => $address_info['user_name'],
            'user_phone' => $address_info['user_phone'],
            'order_type' => self::TYPE_WAITING_SEND_GOODS
        ];
        yield $this->mysql_pool->dbQueryBuilder->update($this->table)->where('order_id', $order_id)->set($update_info)->coroutineSend();
    }

    /**
     * WaitingSend->WaitingGet
     * @param $order_id
     * @param $send_good_order
     * @param $send_good_company
     * @param $send_good_time
     * @return \Generator
     */
    public function typeWaitingSend($order_id, $send_good_order, $send_good_company, $send_good_time)
    {
        $update_info = [
            'order_type' => self::TYPE_WAITING_GET_GOODS,
            'send_good_order' => $send_good_order,
            'send_good_company' => $send_good_company,
            'send_good_time' => $send_good_time
        ];
        yield $this->mysql_pool->dbQueryBuilder->update($this->table)->where('oreder_id', $order_id)->set($update_info)->coroutineSend();
    }

    /**
     * WaitingGet->Complete
     * @param $order_id
     * @return \Generator
     */
    public function typeWaitingGet($order_id)
    {
        $update_info = [
            'order_type' => self::TYPE_COMPLETE
        ];
        yield $this->mysql_pool->dbQueryBuilder->update($this->table)->where('oreder_id', $order_id)->set($update_info)->coroutineSend();
    }
}