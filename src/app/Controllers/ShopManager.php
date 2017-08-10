<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-31
 * Time: 下午1:41
 */

namespace app\Controllers;


use app\Models\Goods;
use app\Models\GoodsPhase;
use app\Models\Shop;
use app\OneException;

/**
 * 商店管理相关方法
 * Class ShopManager
 * @package app\Controllers
 */
class ShopManager extends BaseController
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

    public function initialization($controller_name, $method_name)
    {
        parent::initialization($controller_name, $method_name);
        $this->Shop = $this->loader->model('Shop', $this);
        $this->Goods = $this->loader->model('Goods', $this);
        $this->GoodsPhase = $this->loader->model('GoodsPhase', $this);
    }

    /**
     * 创建一个商店
     */
    public function http_createShop()
    {
        //需要管理员权限
        $this->manager_login();
        $data = $this->http_input->getAllPostGet();
        $shop_info = $this->filteKeys($data, 'wid', 'shop_wxname', 'shop_icon', 'shop_desc', 'shop_name', 'shop_qq', 'shop_phone', 'shop_address', 'shop_type', 'is_show');
        $this->existKeys($shop_info, 'shop_name', 'shop_type', 'shop_icon', 'is_show');
        $shop_info['is_show'] = $this->getBoolNum($shop_info['is_show']);
        try {
            $result = yield $this->Shop->createShop($shop_info);
        } catch (\Exception $e) {
            throw new OneException('商店创建失败，有可能是你已经拥有一个商店了');
        }
        $shop_id = $result['insert_id'];
        $shop_info['shop_id'] = $shop_id;
        $this->end($shop_info);
    }

    /**
     * 修改一个商店
     */
    public function http_updateShop()
    {
        //需要管理员权限
        $this->manager_login();
        $data = $this->http_input->getAllPostGet();
        $shop_info = $this->filteKeys($data, 'shop_id', 'wid', 'shop_wxname', 'shop_icon', 'shop_desc', 'shop_name', 'shop_qq', 'shop_phone', 'shop_address', 'shop_type', 'is_show');
        $this->existKeys($shop_info, 'shop_id');
        if (array_key_exists('is_show', $shop_info)) {
            $shop_info['is_show'] = $this->getBoolNum($shop_info['is_show']);
        }
        yield $this->Shop->updateShop($shop_info['shop_id'], $shop_info);
        $this->end('ok');
    }

    /**
     * 创建一个商品
     */
    public function http_createGoods()
    {
        //需要管理员权限
        $this->manager_login();
        $data = $this->http_input->getAllPostGet();
        $goods_info = $this->filteKeys($data, 'shop_id', 'goods_name', 'goods_desc', 'goods_money', 'goods_icons', 'left_phase', 'is_show');
        $this->existKeys($goods_info, 'shop_id', 'goods_name', 'goods_money', 'goods_icons', 'left_phase', 'is_show');
        $goods_info['is_show'] = $this->getBoolNum($goods_info['is_show']);
        if ($goods_info['left_phase'] <= 0) {
            throw new OneException('剩余库存必须大于0');
        }
        yield $this->Shop->getShopInfo($goods_info['shop_id']);
        $result = yield $this->Goods->createGoods($goods_info);
        $goods_id = $result['insert_id'];
        $goods_info['goods_id'] = $goods_id;
        $this->end($goods_info);
    }

    /**
     * 更新一个商品
     */
    public function http_updateGoods()
    {
        //需要管理员权限
        $this->manager_login();
        $data = $this->http_input->getAllPostGet();
        $goods_info = $this->filteKeys($data, 'goods_id', 'shop_id', 'goods_name', 'goods_desc', 'goods_money', 'goods_icons', 'left_phase', 'is_show');
        $this->existKeys($goods_info, 'goods_id');
        if (array_key_exists('is_show', $goods_info)) {
            $goods_info['is_show'] = $this->getBoolNum($goods_info['is_show']);
        }
        if (array_key_exists('left_phase', $goods_info)) {
            if ($goods_info['left_phase'] <= 0) {
                throw new OneException('剩余库存必须大于0');
            }
        }
        yield $this->Shop->getShopInfo($goods_info['shop_id']);
        yield $this->Goods->updateGoodsInfo($goods_info['goods_id'], $goods_info);
        $this->end('ok');
    }

    /**
     * 创建一个奖期
     */
    public function http_createGoodsPhase()
    {
        //需要管理员权限
        $this->manager_login();
        $goods_id = $this->http_input->get('goods_id');
        $this->existValues('goods_id', $goods_id);
        $result = yield $this->GoodsPhase->createNextPhase($goods_id);
        $this->end($result);
    }

    /**
     * 过审核商品
     */
    public function http_auditGoods()
    {
        //需要管理员权限
        $this->manager_login();
        $goods_id = $this->http_input->post('goods_id');
        $type = $this->http_input->post('type');
        $this->existValues(['goods_id', 'type'], $goods_id, $type);
        $type = $this->getBoolNum($type);
        yield $this->Goods->updateGoodsInfo($goods_id, ['is_audit_through' => $type]);
        $this->end('ok');
    }

    /**
     * 获取所有商店信息
     */
    public function http_getAllShopInfo()
    {
        //需要管理员权限
        $this->manager_login();
        $limit = $this->http_input->get('limit');
        $page = $this->http_input->get('page');
        $this->existValues(['limit', 'page'], $limit, $page);
        $result = yield $this->Shop->getAllShopInfo($limit, $page);
        if ($page == 1) {
            $total = yield $this->Shop->getAllShopInfoPages($limit);
            $this->end(['infos' => $result, 'total' => $total]);
        } else {
            $this->end(['infos' => $result]);
        }
    }

    /**
     * 获取商店信息
     */
    public function http_getShopInfoFromId()
    {
        //需要管理员权限
        $this->manager_login();
        $shop_id = $this->http_input->get('shop_id');
        $shop_info = yield $this->Shop->getShopInfo($shop_id);
        $this->end(['info' => $shop_info]);
    }

    /**
     * 获取物品信息
     */
    public function http_getGoodsInfoFromId()
    {
        //需要管理员权限
        $this->manager_login();
        $goods_id = $this->http_input->get('goods_id');
        $goods_info = yield $this->Goods->getGoodsInfo($goods_id);
        $this->end(['info' => $goods_info]);
    }

    /**
     * 获取商店开奖信息
     */
    public function http_getShopGoodsPhase()
    {
        //需要管理员权限
        $this->manager_login();
        $shop_id = $this->http_input->get('shop_id');
        $limit = $this->http_input->get('limit');
        $page = $this->http_input->get('page');
        $this->existValues(['shop_id', 'limit', 'page'], $shop_id, $limit, $page);
        $result = yield $this->GoodsPhase->getGoodsPhaseFromShopId($shop_id, $limit, $page);
        if ($page == 1) {
            $total = yield $this->GoodsPhase->getGoodsPhaseFromShopIdPages($shop_id, $limit);
            $this->end(['infos' => $result, 'total' => $total]);
        } else {
            $this->end(['infos' => $result]);
        }
    }

    /**
     * 获取商店所有物品信息
     */
    public function http_getGoodsInfo()
    {
        //需要管理员权限
        $this->manager_login();
        $shop_id = $this->http_input->get('shop_id');
        $this->existValues('shop_id', $shop_id);
        $result = yield $this->Goods->getShopGoodsInfo($shop_id);
        $this->end(['infos' => $result]);
    }
}
