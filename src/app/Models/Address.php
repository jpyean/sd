<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-30
 * Time: 下午2:07
 */

namespace app\Models;


class Address extends BaseModel
{
    protected $tabel = 'address';

    /**
     * 添加一个地址
     * @param $user_name
     * @param $user_phone
     * @param $user_address
     * @return array address_info
     */
    public function add($uid,$user_name,$user_phone,$user_address)
    {
        $address_info = [
            'uid'=>$uid,
            'user_name'=>$user_name,
            'user_phone'=>$user_phone,
            'user_address'=>$user_address
        ];
        $result = yield $this->mysql_pool->dbQueryBuilder->insert($this->tabel)->set($address_info)->coroutineSend();
        $address_info['address_id'] = $result['insert_id'];
        return $address_info;
    }

    /**
     * 删除一个地址
     * @param $address_id
     * @return \Generator
     */
    public function remove($address_id)
    {
        yield $this->mysql_pool->dbQueryBuilder->delete()->from($this->tabel)->where('address_id',$address_id)->coroutineSend();
    }

    /**
     * @param $address_id
     * @param $address_info
     * @return \Generator
     */
    public function update($address_id,$address_info)
    {
        yield $this->mysql_pool->dbQueryBuilder->update($this->tabel)->where('address_id',$address_id)->set($address_info)->coroutineSend();
    }

    /**
     * 获取所有的地址
     * @param $uid
     * @return mixed
     */
    public function allAddress($uid)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select("*")->from($this->tabel)->where('uid',$uid)->coroutineSend();
        return $result['result'];
    }

    /**
     * 获取一个地址
     * @param $address_id
     * @return mixed
     */
    public function getAddress($address_id)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select("*")->from($this->tabel)->where('address_id',$address_id)->coroutineSend();
        return $result['result'][0];
    }
}