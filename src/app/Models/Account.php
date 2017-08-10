<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-30
 * Time: 下午2:05
 */

namespace app\Models;

use app\OneException;
use Firebase\JWT\JWT;


/**
 * 登录注册模块
 * Class Account
 * @package app\Models
 */
class Account extends BaseModel
{
    protected $redis_table = 'account';
    protected $table = 'account';
    /**
     * @var MWechat
     */
    protected $MWechat;

    public function initialization(&$context)
    {
        parent::initialization($context);
        $this->MWechat = $this->loader->model('MWechat', $this);
    }

    /**
     * 获取用户信息
     * @param $uid
     * @return mixed
     */
    public function getUserInfo($uid)
    {
        $user_info = yield $this->redis_pool->getCoroutine()->hGet($this->redis_table, $uid);
        if (empty($user_info)) {
            $result = yield $this->mysql_pool->dbQueryBuilder->select("*")->from($this->table)->where('uid', $uid)->coroutineSend();
            $this->judgeMySQLHaveValue($result, '用户不存在');
            $user_info = $result['result'][0];
            yield $this->redis_pool->getCoroutine()->hSet($this->redis_table, $user_info['uid'], json_encode($user_info));
        } else {
            $user_info = json_decode($user_info, true);
        }
        return $user_info;
    }

    /**
     * 通过Phone查找用户
     * @param $phone
     * @return \Generator
     */
    public function getUserInfoFromPhone($phone)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select("*")->from($this->table)->where('phone', $phone)->coroutineSend();
        $this->judgeMySQLHaveValue($result, '用户不存在');
        $user_info = $result['result'][0];
        yield $this->redis_pool->getCoroutine()->hSet($this->redis_table, $user_info['uid'], json_encode($user_info));
        return $user_info;
    }

    /**
     * 更新userInfo
     * @param $uid
     * @param $userInfo
     * @return \Generator
     */
    public function updateUserInfo($uid, $userInfo)
    {
        unset ($userInfo['wid']);
        unset ($userInfo['uid']);
        yield $this->mysql_pool->dbQueryBuilder->update($this->table)->set($userInfo)->where('uid', $uid)->coroutineSend();
        yield $this->redis_pool->getCoroutine()->hDel($this->redis_table, $uid);
    }

    /**
     * 注册
     * @param $phone
     * @param $passwords_md5
     * @return array
     */
    public function regist($phone, $passwords_md5)
    {
        $user_info = [
            'phone' => $phone,
            'passwords_md5' => $passwords_md5,
            'user_name' => '客官',
            'sex' => 0
        ];
        $result = yield $this->mysql_pool->dbQueryBuilder->insert($this->table)
            ->set($user_info)->coroutineSend();
        $user_info['uid'] = $result['insert_id'];
        $token = JWT::encode($user_info, $this->config['jwt_key']);
        $user_info['token'] = $token;
        return $user_info;
    }

    /**
     * 重设密码
     * @param $phone
     * @param $passwords_md5
     * @return mixed
     */
    public function resetPasswords($phone, $passwords_md5)
    {
        yield $this->mysql_pool->dbQueryBuilder->update($this->table)
            ->set('passwords_md5', $passwords_md5)->where('phone', $phone)->coroutineSend();
    }

    /**
     * 用户名密码登录
     * @param $phone
     * @param $passwords_md5
     * @return mixed
     * @throws OneException
     */
    public function login($phone, $passwords_md5)
    {
        $result = yield $this->mysql_pool->dbQueryBuilder->select("*")->from($this->table)->where('phone', $phone)->coroutineSend();
        $this->judgeMySQLHaveValue($result, '用户不存在');
        $user_info = $result['result'][0];
        //放到redis中缓存
        yield $this->redis_pool->getCoroutine()->hSet($this->redis_table, $user_info['uid'], json_encode($user_info));
        if ($user_info['passwords_md5'] == $passwords_md5) {
            $token = JWT::encode($user_info, $this->config['jwt_key']);
            $user_info['token'] = $token;
            return $user_info;
        } else {
            throw new OneException('密码错误');
        }
    }

    /**
     * 获取token
     * @param $user_info
     * @return string
     */
    public function getToken($user_info)
    {
        if (array_key_exists('token', $user_info)) {
            return $user_info['token'];
        }
        $token = JWT::encode($user_info, $this->config['jwt_key']);
        return $token;
    }

    /**
     * token登录
     * @param $token
     * @return array
     */
    public function tokenLogin($token)
    {
        $user_info_object = JWT::decode($token, $this->config['jwt_key'], array('HS256'));
        $user_info = [];
        foreach ($user_info_object as $key => $value) {
            $user_info[$key] = $value;
        }
        $token = JWT::encode($user_info, $this->config['jwt_key']);
        $user_info['token'] = $token;
        return $user_info;
    }

    /**
     * 微信通过code获取用户信息
     * @param $code
     * @return array
     * @throws OneException
     */
    public function wchatLogin($code)
    {
        $wuser_info = yield $this->MWechat->login($code);
        //获取到微信用户信息后开始注册或者登录
        $result = yield $this->mysql_pool->dbQueryBuilder->select('*')->from($this->table)->where('wid', $wuser_info['openid'])->coroutineSend();
        if (count($result['result']) == 0) {//没有这个用户，创建这个用户
            $user_info = [
                'wid' => $wuser_info['openid'],
                'user_name' => $wuser_info['nickname'],
                'sex' => $wuser_info['sex'],//用户的性别，值为1时是男性，值为2时是女性，值为0时是未知
                'user_icon' => $wuser_info['headimgurl']
            ];
            $result = yield $this->mysql_pool->dbQueryBuilder->insert($this->table)->set($user_info)->coroutineSend();
            $uid = $result['insert_id'];
            $user_info['uid'] = $uid;
        } else {
            $user_info = $result['result'][0];
        }
        $token = JWT::encode($user_info, $this->config['jwt_key']);
        $user_info['token'] = $token;
        return $user_info;
    }

}