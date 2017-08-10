<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-30
 * Time: 下午2:05
 */

namespace app\Models;

use Firebase\JWT\JWT;


/**
 * 登录注册模块
 * Class Account
 * @package app\Models
 */
class Manager extends BaseModel
{
    protected $table = 'manager';

    /**
     * 获取管理员信息
     * @param $manager_name
     * @param $manager_key
     * @return mixed
     */
    public function getInfo($manager_name, $manager_key)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select("*")->from($this->table)->where('manager_name', $manager_name)->andWhere('manager_key', $manager_key)->coroutineSend();
        $this->judgeMySQLHaveValue($result, '用户名密码不正确');
        return $result['result'][0];
    }

    /**
     * token登录
     * @param $token
     * @return array
     */
    public function tokenLogin($token)
    {
        $manager_info_object = JWT::decode($token, $this->config['jwt_key'], array('HS256'));
        $manager_info = [];
        foreach ($manager_info_object as $key => $value) {
            $user_info[$key] = $value;
        }
        $token = JWT::encode($manager_info, $this->config['jwt_key']);
        $manager_info['token'] = $token;
        return $manager_info;
    }

    /**
     * 获取token
     * @param $info
     * @return string
     */
    public function getToken($info)
    {
        if (array_key_exists('token', $info)) {
            return $info['token'];
        }
        $token = JWT::encode($info, $this->config['jwt_key']);
        return $token;
    }
}