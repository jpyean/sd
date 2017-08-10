<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-4-1
 * Time: 下午1:08
 */

namespace app\Controllers;


use app\Models\Coupon;
use app\Models\Goods;
use app\Models\GoodsPhase;
use app\Models\MWechat;
use app\Models\Order;
use app\Models\Shop;
use Server\Asyn\HttpClient\HttpClientPool;

class OrderManager extends BaseController
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
     * @var HttpClientPool
     */
    protected $GetIPAddressHttpClient;

    /**
     * @var Order
     */
    protected $Order;

    /**
     * @var MWechat
     */
    protected $MWechat;

    public function initialization($controller_name, $method_name)
    {
        parent::initialization($controller_name, $method_name);
        $this->Goods = $this->loader->model('Goods', $this);
        $this->GoodsPhase = $this->loader->model('GoodsPhase', $this);
        $this->Coupon = $this->loader->model('Coupon', $this);
        $this->Shop = $this->loader->model('Shop', $this);
        $this->Order = $this->loader->model('Order', $this);
        $this->MWechat = $this->loader->model('MWechat', $this);
        $this->GetIPAddressHttpClient = get_instance()->getAsynPool('GetIPAddress');
    }


    /**
     * 创建订单
     */
    public function http_createOrder()
    {
        //需要登录
        $user_info = yield $this->login(true);
        $parmas = $this->http_input->getAllPostGet();
        $this->existKeys($parmas, 'shop_id', 'goods_id', 'phase_id', 'want_money');
        $ip = $this->http_input->server('remote_addr');
        if ($ip != '127.0.0.1') {
            $response = yield $this->GetIPAddressHttpClient->httpClient
                ->setQuery(['format' => 'json', 'ip' => $ip])
                ->coroutineExecute('/iplookup/iplookup.php');
            $body = json_decode($response['body'], true);
            if (is_int($body)) {//代表错误了
                $ip_address = '未知地址';
            } else {
                $ip_address = $body['country'] . $body['province'] . $body['city'];
                if (!empty($body['isp'])) {
                    $ip_address = $ip_address . '(' . $body['isp'] . ')';
                }
            }
        } else {
            $ip_address = '本地';
        }
        $orderInfo = yield $this->Order->createOrder($user_info['uid'], $parmas['shop_id'], $parmas['goods_id'],
            $parmas['phase_id'], $parmas['want_money'],
            $user_info['user_name'], $user_info['user_icon'],
            $ip, $ip_address);
        //统一下单
        $res_unified_order = yield $this->MWechat->unifiedOrder($orderInfo['order_id'], $user_info['wid'], $orderInfo['money'] * 100, $ip);
        if ($res_unified_order['return_code'] == 'FAIL') {
            $res_app_params = $res_unified_order['return_msg'];
        } else {
            //生成APP端支付参数
            $res_app_params = yield $this->MWechat->getAppPayParams($res_unified_order['prepay_id']);
            $order_id = $orderInfo['order_id'];
            $this->log("微信统一下单：$order_id");
        }
        $this->end(['order_info' => $orderInfo, 'params' => $res_app_params]);
    }

    /**
     * ip地址测试
     */
    public function http_ip_test()
    {
        $ip = $this->http_input->server('remote_addr');
        $response = yield $this->GetIPAddressHttpClient->httpClient
            ->setQuery(['format' => 'json', 'ip' => $ip])
            ->coroutineExecute('/iplookup/iplookup.php');
        $this->end($response);
    }

    /**
     * 支付的回调
     */
    public function http_pay_callback()
    {
        $post_arr = $this->http_input->getRawContent();//xml格式
        $data = (array)simplexml_load_string($post_arr, 'SimpleXMLElement', LIBXML_NOCDATA);
        $out_trade_no = $data['out_trade_no'];//商户订单号
        $return_code = $data['return_code'];//成功标识
        $total_fee = $data['total_fee'];//总金额
        $sign = $data['sign'];//签名

        //验证是否支付成功
        if (empty($return_code) || strtoupper($return_code) != 'SUCCESS') {
            $this->log("支付失败：$out_trade_no");
            $this->http_output->end('fail', false);
            return;
        }
        if ($this->MWechat->wx_sign($sign, $data)) {//签名验证成功
            $result = yield $this->Order->confirmOrder($out_trade_no, $total_fee / 100);
            $this->log("$result：$out_trade_no");
            $this->http_output->end('success', false);
        } else {
            $this->log("验证失败：$out_trade_no");
            $this->http_output->end('fail', false);
        }
    }

    /**
     * 取消订单
     * @return \Generator
     */
    public function http_cancelOrder()
    {
        $order_id = $this->http_input->get('order_id');
        $this->existValues('order_id', $order_id);
        $order_info = yield $this->Order->getOrderInfo($order_id);
        yield $this->Order->cancelOrder($order_info);
        $this->end('ok');
    }

}