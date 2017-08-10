<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-30
 * Time: 下午2:07
 */

namespace app\Models;


use app\OneException;

class Coupon extends BaseModel
{
    protected $tabel = 'coupon_box_';
    protected $bind_tabel = 'coupon_box_bind_';
    /**
     * 创建一个奖卷池
     * @param $id
     * @param $count
     * @throws OneException
     */
    public function createCouponBox($id,$count)
    {
        $result = yield $this->redis_pool->getCoroutine()->evalSha(getLuaSha1('sadd_from_count'),[$this->tabel.$id,$count],2);
        if(!$result){
            throw new OneException('创建CouponBox失败');
        }
    }

    /**
     * 随机获取奖卷
     * @param $id
     * @param $count
     * @return mixed
     * @throws OneException
     */
    public function getCoupon($id,$count)
    {
        $result = yield $this->redis_pool->getCoroutine()->sPop($this->tabel.$id,$count);
        return $result;
    }

    /**
     * 获取奖卷数量
     * @param $id
     * @return mixed
     */
    public function countCoupon($id)
    {
        $result = yield $this->redis_pool->getCoroutine()->sCard($this->tabel.$id);
        return $result;
    }

    /**
     * 奖票绑定order_info
     * @param $order_info
     * @param $coupons
     * @return \Generator
     */
    public function couponBindUid($order_info,$coupons)
    {
        $hashkeys = [];
        foreach ($coupons as $coupon){
            $hashkeys[$coupon] = json_encode($order_info);
        }
        $id = $order_info['phase_id'];
        yield $this->redis_pool->getCoroutine()->hMset($this->bind_tabel.$id,$hashkeys);
    }

    /**
     * 开奖
     * @param $id
     * @param $coupon
     * @return array order_info
     */
    public function couponRun($id,$coupon)
    {
        $value = yield $this->redis_pool->getCoroutine()->hGet($this->bind_tabel.$id,$coupon);
        //开奖后就可以删除对应表了
        $this->redis_pool->getCoroutine()->del($this->bind_tabel.$id);
        return json_decode($value,true);
    }

    /**
     * 移除
     * @param $id
     * @return mixed
     */
    public function removeCoupon($id)
    {
        $result = yield $this->redis_pool->getCoroutine()->del($this->tabel.$id);
        return $result;
    }

    /**
     * 获取table
     * @param $id
     * @return string
     */
    public function getRedisTable($id)
    {
        return $this->tabel.$id;
    }
}