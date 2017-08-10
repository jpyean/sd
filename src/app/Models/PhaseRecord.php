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
 * 夺宝记录
 * Class Shop
 * @package app\Models
 */
class PhaseRecord extends BaseModel
{
    protected $table = 'phase_record';

    const RECORD_TYPE_ALL = 'all';
    const RECORD_TYPE_ING = 'ing';
    const RECORD_TYPE_OVER = 'over';

    /**
     * 获得夺宝记录
     * @param $uid
     * @param $type
     * @return mixed
     * @throws OneException
     */
    public function getUidPayPhaseRecord($uid, $type)
    {
        $result = null;
        switch ($type) {
            case self::RECORD_TYPE_ALL:
                $result = yield $this->mysql_pool->dbQueryBuilder->select("*")->from($this->table)
                    ->innerJoin('goods_phase', 'phase_id')
                    ->innerJoin('goods', 'goods_id')
                    ->where('uid', $uid)->coroutineSend();
                break;
            case self::RECORD_TYPE_ING:
                $result = yield $this->mysql_pool->dbQueryBuilder->select("*")->from($this->table)
                    ->innerJoin('goods_phase', 'phase_id')
                    ->innerJoin('goods', 'goods_id')
                    ->where('uid', $uid)->andWhere('type', 0)->coroutineSend();
                break;
            case self::RECORD_TYPE_OVER:
                $result = yield $this->mysql_pool->dbQueryBuilder->select("*")->from($this->table)
                    ->innerJoin('goods_phase', 'phase_id')
                    ->innerJoin('goods', 'goods_id')
                    ->where('uid', $uid)->andWhere('type', 1)->coroutineSend();
                break;
            default:
                throw new OneException('record type 不存在');
        }
        return $result['result']??null;
    }

    /**
     * 添加一个记录
     * @param $uid
     * @param $phase_id
     * @param $money
     * @return \Generator
     */
    public function addRecord($uid, $phase_id, $money)
    {
        $id = $this->helpCreateId($uid, $phase_id);
        yield $this->mysql_pool->dbQueryBuilder
            ->coroutineSend(null, "INSERT INTO $this->table (phase_record_id,uid,phase_id,pay_total_coupon) VALUES ('$id',$uid,'$phase_id',$money) ON DUPLICATE KEY UPDATE pay_total_coupon = pay_total_coupon+$money");
    }

    /**
     * 获取记录
     * @param $uid
     * @param $phase_id
     * @return mixed
     */
    public function getRecord($uid, $phase_id)
    {
        $id = $this->helpCreateId($uid, $phase_id);
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from($this->table)->where('phase_record_id', $id)->coroutineSend();
        $this->judgeMySQLHaveValue($result, '没有记录');
        return $result['result'][0];
    }

    /**
     * 更新状态为开奖
     * @param $phase_id
     * @return \Generator
     */
    public function updateOverType($phase_id)
    {
        yield $this->mysql_pool->dbQueryBuilder->update($this->table)->set('type', 1)->where('phase_id', $phase_id)->coroutineSend();
    }

    /**
     * 创建id
     * @param $uid
     * @param $phase_id
     * @return string
     */
    public function helpCreateId($uid, $phase_id)
    {
        return $phase_id . '-' . $uid;
    }
}