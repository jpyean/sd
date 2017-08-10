<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-4-6
 * Time: 下午4:46
 */

namespace app\Controllers;


use app\Models\DeliverGoodsOrder;
use app\Models\Goods;
use app\Models\GoodsPhase;
use app\Models\Order;
use app\Models\PhaseRecord;

class PhaseQuery extends BaseController
{
    /**
     * @var Order
     */
    protected $Order;
    /**
     * @var DeliverGoodsOrder
     */
    protected $DeliverGoodsOrder;

    /**
     * @var PhaseRecord
     */
    protected $PhaseRecord;

    /**
     * @var Goods;
     */
    protected $Goods;

    /**
     * @var GoodsPhase;
     */
    protected $GoodsPhase;
    public function initialization($controller_name, $method_name)
    {
        parent::initialization($controller_name, $method_name);
        $this->Order = $this->loader->model('Order',$this);
        $this->PhaseRecord = $this->loader->model('PhaseRecord',$this);
        $this->DeliverGoodsOrder = $this->loader->model('DeliverGoodsOrder',$this);
        $this->Goods = $this->loader->model('Goods',$this);
        $this->GoodsPhase = $this->loader->model('GoodsPhase',$this);
    }

    /**
     * 获取参与记录
     * page从1开始
     */
    public function http_queryPhaseOrderRecord()
    {
        $phase_id = $this->http_input->get('phase_id');
        $page = $this->http_input->get('page');
        $limit = $this->http_input->get('limit');
        $this->existValues(['phase_id','page','limit'],$phase_id,$page,$limit);
        $result = yield $this->Order->getPhaseOrderRecord($phase_id,$limit,$page);
        $send = ['order_infos'=>$result];
        $this->end($send);
    }

    /**
     * 夺宝记录
     */
    public function http_queryUidPayRecord()
    {
        $user_info = yield $this->login(true);
        $type = $this->http_input->get('type');
        $this->existValues('type',$type);
        $result = yield $this->PhaseRecord->getUidPayPhaseRecord($user_info['uid'],$type);
        $send = ['infos'=>$result];
        $this->end($send);
    }

    /**
     * 查看夺宝详情
     */
    public function http_payRecordInfo()
    {
        $user_info = yield $this->login(true);
        $phase_id = $this->http_input->get('phase_id');
        $this->existValues('phase_id',$phase_id);
        $result = yield $this->Order->getUserPhaseOrderRecord($phase_id,$user_info['uid']);
        $count = 0;
        foreach ($result as $one){
            $count += $one['money'];
        }
        $goodsPhase = $this->GoodsPhase->helpGoodsIDPhase($phase_id);
        $goods_info = yield $this->Goods->getGoodsInfo($goodsPhase['goods_id']);
        $data['phase'] = $goodsPhase['phase'];
        $data['goods_name'] = $goods_info['goods_name'];
        $data['count'] = $count;
        $send = ['goods_info'=>$data,'infos'=>$result];
        $this->end($send);
    }

    /**
     * 查看号码
     */
    public function http_payRecordCoupons()
    {
        $user_info = yield $this->login(true);
        $order_id = $this->http_input->get('order_id');
        $this->existValues('order_id',$order_id);
        $order_info = yield $this->Order->getOrderInfo($order_id);
        $phase_id = $order_info['phase_id'];
        $result = yield $this->Order->getUserPhaseOrderRecord($phase_id,$user_info['uid']);
        $count = 0;
        foreach ($result as $one){
            $count += $one['money'];
        }
        $goodsPhase = $this->GoodsPhase->helpGoodsIDPhase($phase_id);
        $goods_info = yield $this->Goods->getGoodsInfo($goodsPhase['goods_id']);
        $data['phase'] = $goodsPhase['phase'];
        $data['goods_name'] = $goods_info['goods_name'];
        $data['count'] = $count;
        $coupons = yield $this->Order->getOrderCoupons($order_id);
        $send = ['goods_info'=>$data,'coupons'=>$coupons];
        $this->end($send);
    }

    /**
     * 获取中奖记录
     */
    public function http_getWinningRecord()
    {
        $user_info = yield $this->login(false);
        $type = $this->http_input->get('type');
        $page = $this->http_input->get('page');
        $this->existValues(['type','page'],$type,$page);
        $result = yield $this->DeliverGoodsOrder->getUserTypeInfo($user_info['uid'],$type,20,$page);
        $return_info = ['recordInfo'=>$result];
        $this->end($return_info);
    }
}