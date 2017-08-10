<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-30
 * Time: 下午6:05
 */

namespace app\Controllers;

use app\Models\Address;
use app\Models\Coupon;
use app\Models\DeliverGoodsOrder;
use app\Models\Goods;
use app\Models\GoodsPhase;
use app\Models\Order;
use app\Models\PhaseRecord;
use app\Models\Shop;

/**
 * 奖期相关的方法
 * Class GoodsPhaseController
 * @package app\Controllers
 */
class Page extends BaseController
{
    /**
     * @var Shop
     */
    protected $Shop;
    /**
     * @var Goods
     */
    protected $Goods;
    /**
     * @var GoodsPhase
     */
    protected $GoodsPhase;
    /**
     * @var Coupon
     */
    protected $Coupon;
    /**
     * @var Order
     */
    protected $Order;

    /**
     * @var PhaseRecord
     */
    protected $PhaseRecord;

    /**
     * @var DeliverGoodsOrder
     */
    protected $DeliverGoodsOrder;

    /**
     * @var Address
     */
    protected $Address;
    public function initialization($controller_name, $method_name)
    {
        parent::initialization($controller_name, $method_name);
        $this->Goods = $this->loader->model('Goods', $this);
        $this->GoodsPhase = $this->loader->model('GoodsPhase', $this);
        $this->Coupon = $this->loader->model('Coupon', $this);
        $this->Shop = $this->loader->model('Shop', $this);
        $this->Order = $this->loader->model('Order', $this);
        $this->PhaseRecord = $this->loader->model('PhaseRecord', $this);
        $this->Address = $this->loader->model('Address', $this);
        $this->DeliverGoodsOrder = $this->loader->model('DeliverGoodsOrder', $this);
    }

    /**
     * 获取最新期数的信息
     */
    public function http_goodDetail()
    {
        $user_info = yield $this->login();
        $goods_id = $this->http_input->get('goods_id');
        $phase = $this->http_input->get('phase');
        $this->existValues('goods_id', $goods_id);
        //物品信息
        $goods_info = yield $this->Goods->getGoodsInfo($goods_id);
        //商店信息
        $shop_id = $goods_info['shop_id'];
        $shop_info = yield $this->Shop->getShopInfo($shop_id);
        //期数信息
        if (empty($phase)) {
            $phase = $goods_info['goods_now_phase'];
        }
        $phase_id = $this->GoodsPhase->helpMathPhaseId($goods_id, $phase);
        $goods_phase_info = yield $this->GoodsPhase->getGoodsPhaseInfo($goods_id, $phase);
        //奖池剩余奖卷信息
        $coupon_count = yield $this->Coupon->countCoupon($goods_phase_info['phase_id']);
        //构建返回信息
        $return_info['shop_info'] = $shop_info;
        $return_info['goods_info'] = $goods_info;
        $return_info['goods_phase_info'] = $goods_phase_info;
        $return_info['coupon_count'] = $coupon_count;
        //获奖者信息
        if (!empty($goods_phase_info['winer_uid'])) {
            $winer_user_info = yield $this->Account->getUserInfo($goods_phase_info['winer_uid']);
            $return_info['winer_user_info'] = $winer_user_info;
        }
        //参与记录
        $order_infos = yield $this->Order->getPhaseOrderRecord($goods_phase_info['phase_id'], 20, 1);
        $return_info['order_infos'] = $order_infos;
        //上一期获奖数据
        $return_info['last_phase_info'] = null;
        if ($phase > 1) {//代表有上一期
            $phase_info = yield $this->GoodsPhase->getGoodsPhaseInfo($goods_id, $phase - 1);
            $return_info['last_phase_info'] = $phase_info;
        }
        //自己的参与记录，如果有登录的话
        $return_info['my_buy_count'] = 0;
        if ($user_info != false) {
            try {
                $my_record = yield $this->PhaseRecord->getRecord($user_info['uid'], $phase_id);
                $return_info['my_buy_count'] = $my_record['pay_total_coupon'];
            }catch (\Exception $e){
                $return_info['my_buy_count'] = 0;
            }
        }
        $this->endRenderData('goodDetail', $return_info, $goods_info['goods_name']);
    }

    /**
     * user
     */
    public function http_user()
    {
        yield $this->login(true);
        $this->endRenderData('user', null);
    }

    /**
     * 计算详情
     */
    public function http_countDetail()
    {
        $goods_id = $this->http_input->get('goods_id');
        $phase = $this->http_input->get('phase');
        $phase_id = $this->GoodsPhase->helpMathPhaseId($goods_id, $phase);
        $goods_info = yield $this->Goods->getGoodsInfo($goods_id);
        $result = yield $this->Order->getLastNumOrder($phase_id, 50);
        $return_info['order_infos'] = $result;
        $return_info['goods_money'] = $goods_info['goods_money'];
        $this->endRenderData('countDetail', $return_info);
    }

    /**
     * 获取往期获奖记录
     */
    public function http_oldGoodsPhase()
    {
        $goods_id = $this->http_input->get('goods_id');
        $result = yield $this->GoodsPhase->getGoodsPhaseInfos($goods_id, 50, 1);
        $return_info['goods_phase_infos'] = $result;
        $this->endRenderData('oldGoodsPhase', $return_info);
    }

    /**
     * 进入下单界面
     */
    public function http_cart()
    {
        yield $this->login(true);
        $goods_id = $this->http_input->get('goods_id');
        $phase = $this->http_input->get('phase');
        $this->existValues(['goods_id', 'phase'], $goods_id, $phase);
        //奖池剩余奖卷信息
        $phase_id = $this->GoodsPhase->helpMathPhaseId($goods_id, $phase);
        $goods_info = yield $this->Goods->getGoodsInfo($goods_id);
        $coupon_count = yield $this->Coupon->countCoupon($phase_id);
        $return_info['goods_info'] = $goods_info;
        $return_info['coupon_count'] = $coupon_count;
        $return_info['phase'] = $phase;
        $return_info['phase_id'] = $this->GoodsPhase->helpMathPhaseId($goods_id,$phase);
        $this->endRenderData('cart', $return_info);
    }

    /**
     * 登陆界面
     */
    public function http_login()
    {
        $user_info = yield $this->login();
        $redirect_uri = $this->http_input->get('redirect_uri');
        $redirect_uri = urldecode($redirect_uri);
        if ($user_info != null && !empty($redirect_uri)) {//已经登录了，直接跳转
            $this->redirect($redirect_uri);
        }
        //微信登陆url
        $appid = $this->config->get('weixin.appid');
        $weixin_url = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=$appid&redirect_uri=$redirect_uri&response_type=code&scope=snsapi_userinfo&state=weixin_login#wechat_redirect";
        $this->endRenderData('login', $weixin_url);
    }

    /**
     * 图文详情
     */
    public function http_recordDetail()
    {
        $goods_id = $this->http_input->get('goods_id');
        $this->existValues(['goods_id'], $goods_id);
        $goods_info = yield $this->Goods->getGoodsInfo($goods_id);
        $return_info['goods_info'] = $goods_info;
        $this->endRenderData('recordDetail', $return_info);
    }

    /**
     * 获取商店首页
     */
    public function http_shopIndex()
    {
        $shop_id = $this->http_input->get('shop_id');
        if(empty($shop_id)){
            $shop_id = $this->http_input->cookie('shop_id');
        }else{
            $this->http_output->setCookie('shop_id',$shop_id);
        }
        $shop_info = yield $this->Shop->getShopInfo($shop_id);
        $goods_info = yield $this->Goods->getShopGoodsAndPhaseInfo($shop_id);
        $return_info['shop_info'] = $shop_info;
        $return_info['goods_info'] = $goods_info;
        $this->endRenderData('shopIndex', $return_info);
    }

    /**
     * 收货地址
     */
    public function http_address()
    {
        $user_info = yield $this->login(true);
        $address = yield $this->Address->allAddress($user_info['uid']);
        $return_info['address_infos'] = $address;
        $this->endRenderData('address', $return_info);
    }

    /**
     * 中奖记录
     */
    public function http_winningRecord()
    {
        $user_info = yield $this->login(true);
        $info = yield $this->DeliverGoodsOrder->getUserAllTypeCount($user_info['uid']);
        $return_info['info'] = $info;
        $this->endRenderData('winningRecord', $return_info);
    }

    /**
     * 中奖订单
     */
    public function http_winningOrder()
    {
        $user_info = yield $this->login(true);
        $order_id = $this->http_input->get('order_id');
        $this->existValues(['order_id'], $order_id);
        $order_info = yield $this->DeliverGoodsOrder->getInfoFromOrderId($order_id);
        $shop_info = yield $this->Shop->getShopInfo($order_info['shop_id']);
        $address_infos = yield $this->Address->allAddress($user_info['uid']);
        $return_info['order_info'] = $order_info;
        $return_info['address_infos'] = $address_infos;
        $return_info['shop_info'] = $shop_info;
        $this->endRenderData('winningRecord', $return_info);
    }

    /**
     * 中奖订单
     */
    public function http_deliveryOrder()
    {
        $user_info = yield $this->login(true);
        $order_id = $this->http_input->get('order_id');
        $this->existValues(['order_id'], $order_id);
        $order_info = yield $this->DeliverGoodsOrder->getInfoFromOrderId($order_id);
        $shop_info = yield $this->Shop->getShopInfo($order_info['shop_id']);
        $address_infos = yield $this->Address->allAddress($user_info['uid']);
        $return_info['order_info'] = $order_info;
        $return_info['address_infos'] = $address_infos;
        $return_info['shop_info'] = $shop_info;
        $this->endRenderData('deliveryOrder', $return_info);
    }

}