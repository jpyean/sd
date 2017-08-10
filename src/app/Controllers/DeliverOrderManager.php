<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-4-1
 * Time: 下午1:08
 */

namespace app\Controllers;


use app\Models\DeliverGoodsOrder;

class DeliverOrderManager extends BaseController
{
    /**
     * @var DeliverGoodsOrder
     */
    protected $DeliverGoodsOrder;

    public function initialization($controller_name, $method_name)
    {
        parent::initialization($controller_name, $method_name);
        $this->DeliverGoodsOrder = $this->loader->model('DeliverGoodsOrder', $this);
    }

    /**
     * 确认收货地址
     */
    public function http_readyAddress()
    {
        yield $this->login(false);
        $address_id = $this->http_input->post('address_id');
        $order_id = $this->http_input->post('order_id');
        $remarks = $this->http_input->post('remarks');
        $this->existValues(['address_id', 'order_id'], $address_id, $order_id);
        yield $this->DeliverGoodsOrder->typeNormalHandle($order_id, $address_id, $remarks);
        $this->end('ok');
    }

    /**
     * 确定收货
     * @return \Generator
     */
    public function http_readyGet()
    {
        yield $this->login(false);
        $address_id = $this->http_input->post('address_id');
        $this->existValues('address_id', $address_id);
        yield $this->DeliverGoodsOrder->typeWaitingGet($address_id);
        $this->end('ok');
    }
}