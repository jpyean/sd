<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-4-6
 * Time: 下午3:19
 */

namespace app\Models;


use app\OneException;

class Vcode extends BaseModel
{
    protected $table = 'vcode_';

    /**
     * @var Account
     */
    protected $Account;

    public function initialization(&$context)
    {
        parent::initialization($context);
        $this->Account = $this->loader->model('Account',$this);
    }

    /**
     * 发送验证码
     * @param $phone
     * @param $phone_is_exist
     * @return \Generator
     * @throws OneException
     */
    public function sendMessage($phone,$phone_is_exist)
    {
        //首先判断phone有没有被注册
        $user_info = null;
        try {
            $user_info = yield $this->Account->getUserInfoFromPhone($phone);
        }catch (\Exception $e){//代表没有被注册

        }

        if($user_info!=null&&!$phone_is_exist){
            throw new OneException('该电话号码已被注册');
        }
        if($user_info==null&&$phone_is_exist){
            throw new OneException('该电话号码没有被注册');
        }
        //发验证码测试阶段全是1234
        //$vcode = rand(1000,9999);
        $vcode = 1234;
        yield $this->redis_pool->getCoroutine()->setex($this->table.$phone,60,$vcode);
    }

    /**
     * 验证
     * @param $phone
     * @param $vcode
     * @return bool
     * @throws OneException
     */
    public function verification($phone,$vcode)
    {
        $result = yield $this->redis_pool->getCoroutine()->get($this->table.$phone);
        if(empty($result)){
            throw new OneException('验证码过期或者不存在');
        }
        if($result != $vcode){
            throw new OneException('验证码不正确');
        }
    }
}