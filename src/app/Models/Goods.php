<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-30
 * Time: 下午2:58
 */

namespace app\Models;

use app\OneException;

/**
 * 商品，这个是在mysql中存储的
 * Class Goods
 * @package app\Models
 */
class Goods extends BaseModel
{
    protected $table = 'goods';

    /**
     * 创建商品
     * @param $goodsInfo
     * @return \Generator
     */
    public function createGoods($goodsInfo)
    {
        $goodsInfo['create_time'] = date('y-m-d H:i:s');
        return yield $this->mysql_pool->dbQueryBuilder->insert($this->table)->set($goodsInfo)->coroutineSend();
    }

    /**
     * 获取某个商品信息
     * @param $goods_id
     * @return array
     * @throws OneException
     */
    public function getGoodsInfo($goods_id)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from($this->table)->where('goods_id', $goods_id)->coroutineSend();
        $this->judgeMySQLHaveValue($result, '物品不存在');
        $goods_info = $result['result'][0];
        return $goods_info;
    }

    /**
     * 判断商品有没有过审核
     * @param $goods_id
     * @param null $goods_info
     * @return null
     * @throws OneException
     */
    public function isAuditThrough($goods_id, $goods_info = null)
    {
        if ($goods_info == null) {
            $goods_info = yield $this->getGoodsInfo($goods_id);
        }
        if ($goods_info['is_audit_through'] != 1) {
            throw new OneException('商品未通过审核，不允许购买');
        }
        return $goods_info;
    }

    /**
     * 更新GoodsInfo
     * @param $goods_id
     * @param $update
     * @return \Generator
     */
    public function updateGoodsInfo($goods_id, $update)
    {
        return yield $this->mysql_pool->dbQueryBuilder->update($this->table)->set($update)->where('goods_id', $goods_id)->coroutineSend();
    }

    /**
     * 更新期数
     * @param $goods_id
     * @return \Generator
     */
    public function updateGoodsInfoNextPhase($goods_id)
    {
        yield $this->mysql_pool->dbQueryBuilder->coroutineSend(null,
            "UPDATE goods SET goods_now_phase = goods_now_phase+1, goods_now_phase_id = concat(goods_id,'-',goods_now_phase), left_phase = left_phase-1 WHERE goods_id = $goods_id"
        );
    }

    /**
     * 获取商店所有物品信息(后台用)
     * @param $shop_id
     * @return array
     */
    public function getShopGoodsInfo($shop_id)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from($this->table)->where('shop_id', $shop_id)->coroutineSend();
        return $result['result'];
    }

    /**
     * 获取商店所有奖品信息
     * @param $shop_id
     * @return array
     */
    public function getShopGoodsAndPhaseInfo($shop_id)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->coroutineSend(null,
            "SELECT * FROM goods INNER JOIN goods_phase ON goods.goods_now_phase_id = goods_phase.phase_id WHERE goods.shop_id = $shop_id AND goods.left_phase != 0 AND is_audit_through = 1 AND is_show = 1"
        );
        return $result['result'];
    }

    /**
     * 删除某个商品（危险操作）
     * @param $goods_id
     * @return \Generator
     */
    public function delShopGoods($goods_id)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->delete()->from($this->table)->where('goods_id', $goods_id)->coroutineSend();
        return $result;
    }

    /**
     * 删除一个商店的所有商品（危险操作）
     * @param $shop_id
     * @return \Generator
     */
    public function delShopAllGoods($shop_id)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->delete()->from($this->table)->where('shop_id', $shop_id)->coroutineSend();
        return $result;
    }
}