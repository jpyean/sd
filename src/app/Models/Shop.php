<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-30
 * Time: 下午2:58
 */

namespace app\Models;


/**
 * 商家，这个是在mysql中存储的
 * Class Shop
 * @package app\Models
 */
class Shop extends BaseModel
{
    protected $table = 'shop';
    protected $redis_hash_table = 'ShopInfo';

    /**
     * 创建一个商店
     * @param $shopInfo
     * @return mixed
     */
    public function createShop($shopInfo)
    {
        $shopInfo['create_time'] = date('y-m-d H:i:s');
        $result = yield $this->mysql_pool->dbQueryBuilder->insert($this->table)->set($shopInfo)->coroutineSend();
        return $result;
    }

    /**
     * 更新一个商店
     * @param $shop_id
     * @param $shopInfo
     * @return mixed
     */
    public function updateShop($shop_id,$shopInfo)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->update($this->table)->where('shop_id',$shop_id)->set($shopInfo)->coroutineSend();
        yield $this->redis_pool->getCoroutine()->hDel($this->redis_hash_table,$shop_id);
        return $result;
    }

    /**
     * 获取商店信息
     * @param $shop_id
     * @return mixed
     */
    public function getShopInfo($shop_id)
    {
        $result= yield $this->redis_pool->getCoroutine()->hGet($this->redis_hash_table,$shop_id);
        if(empty($result)) {
            $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from($this->table)->where('shop_id', $shop_id)->coroutineSend();
            $this->judgeMySQLHaveValue($result, '商店不存在');
            $shop_info = $result['result'][0];
            yield $this->redis_pool->getCoroutine()->hSet($this->redis_hash_table,$shop_id,json_encode($shop_info));
        }else{
            $shop_info = json_decode($result,true);
        }
        return $shop_info;
    }

    /**
     * 获取所有的商店信息
     * @param $limit
     * @param $page
     * @return mixed
     */
    public function getAllShopInfo($limit,$page)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from($this->table)->limit($limit,($page-1)*$limit)->coroutineSend();
        return $result['result'];
    }

    /**
     * 获取所有的商店信息页数
     * @param $limit
     * @return mixed
     */
    public function getAllShopInfoPages($limit)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->coroutineSend(null,
            "SELECT count(*) as total FROM $this->table"
        );
        return ceil($result['result'][0]['total']/$limit);
    }

    /**
     * 删除商店
     * @param $shop_id
     * @return mixed
     */
    public function delShop($shop_id)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->delete()->from($this->table)->where('shop_id',$shop_id)->coroutineSend();
        return $result;
    }
}