<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-30
 * Time: 下午2:02
 */

namespace app\Controllers;

use app\Models\Address;
use app\Models\Vcode;

class AccountManager extends BaseController
{
    /**
     * @var Vcode
     */
    protected $Vcode;

    /**
     * @var Address
     */
    protected $Address;

    public function initialization($controller_name, $method_name)
    {
        parent::initialization($controller_name, $method_name);
        $this->Vcode = $this->loader->model('Vcode', $this);
        $this->Address = $this->loader->model('Address', $this);
    }

    /**
     * 登录
     */
    public function http_login()
    {
        $this->clearCookieToken();
        $phone = $this->http_input->post('phone');
        $passwords_md5 = $this->http_input->post('passwords_md5');
        $this->existValues(['phone', 'passwords_md5'], $phone, $passwords_md5);
        $user_info = yield $this->Account->login($phone, $passwords_md5);
        $this->refreshCookieToken($user_info);
        $this->end($user_info);
    }

    /**
     * 获取验证码
     */
    public function http_getVcode()
    {
        $phone = $this->http_input->post('phone');
        $phone_is_exist = $this->http_input->post('phone_is_exist');
        $this->existValues(['phone','phone_is_exist'], $phone, $phone_is_exist);
        $phone_is_exist = $this->getBool($phone_is_exist);
        yield $this->Vcode->sendMessage($phone,$phone_is_exist);
        $this->end('ok');
    }

    /**
     * 注册并登录
     */
    public function http_regist()
    {
        $this->clearCookieToken();
        $phone = $this->http_input->post('phone');
        $passwords_md5 = $this->http_input->post('passwords_md5');
        $vcode = $this->http_input->post('vcode');
        $this->existValues(['phone', 'passwords_md5', 'vcode'], $phone, $passwords_md5, $vcode);
        yield $this->Vcode->verification($phone, $vcode);
        $user_info = yield $this->Account->regist($phone, $passwords_md5);
        $this->refreshCookieToken($user_info);
        $this->end($user_info);
    }

    /**
     * 重设密码
     */
    public function http_resetPasswords()
    {
        $phone = $this->http_input->post('phone');
        $passwords_md5 = $this->http_input->post('passwords_md5');
        $vcode = $this->http_input->post('vcode');
        $this->existValues(['phone', 'passwords_md5', 'vcode'], $phone, $passwords_md5, $vcode);
        yield $this->Vcode->verification($phone, $vcode);
        yield $this->Account->resetPasswords($phone, $passwords_md5);
        $user_info = yield $this->Account->login($phone, $passwords_md5);
        $this->refreshCookieToken($user_info);
        $this->end("ok");
    }

    /**
     * 绑定phone
     */
    public function http_bindPhone()
    {
        $user_info = yield $this->login(false);
        $uid = $user_info['uid'];
        $phone = $this->http_input->post('phone');
        $vcode = $this->http_input->post('vcode');
        $this->existValues(['phone', 'vcode'], $phone, $vcode);
        yield $this->Vcode->verification($phone, $vcode);
        yield $this->Account->updateUserInfo($uid, ['phone' => $phone]);
        $user_info['phone'] = $phone;
        $this->refreshCookieToken($user_info, true);
        $this->end("ok");
    }

    /**
     * 添加地址
     * @return \Generator
     */
    public function http_addAddress()
    {
        $user_info = yield $this->login(false);
        $address_info = $this->http_input->getAllPostGet();
        $address_info = $this->existKeys($address_info, 'user_name', 'user_phone', 'user_address', 'is_normal');
        $is_normal = $this->getBool($address_info['is_normal']);
        $address_info = yield $this->Address->add($user_info['uid'], $address_info['user_name'], $address_info['user_phone'], $address_info['user_address']);
        if ($is_normal) {
            yield $this->Account->updateUserInfo($user_info['uid'], ['address_id' => $address_info['address_id']]);
            $user_info['address_id'] = $address_info['address_id'];
            $this->refreshCookieToken($user_info, true);
        }
        $this->end('ok');
    }

    /**
     * 移除地址
     * @return \Generator
     */
    public function http_removeAddress()
    {
        $user_info = yield $this->login(false);
        $address_id = $this->http_input->post('address_id');
        $this->existValues('address_id',$address_id);
        yield $this->Address->remove($address_id);
        if($user_info['address_id']==$address_id){
            yield $this->Account->updateUserInfo($user_info['uid'],['address_id'=>null]);
            $this->refreshCookieToken($user_info, true);
        }
        $this->end('ok');
    }

    /**
     * 更新地址
     * @return \Generator
     */
    public function http_updateAddress()
    {
        $user_info = yield $this->login(false);
        $address_info = $this->http_input->getAllPostGet();
        $address_info = $this->filteKeys($address_info,'address_id', 'user_name', 'user_phone', 'user_address', 'is_normal');
        if(isset($address_info['is_normal'])){
            $is_normal = $this->getBool($address_info['is_normal']);
            unset($address_info['is_normal']);
            if($is_normal) {
                yield $this->Account->updateUserInfo($user_info['uid'], ['address_id' => $address_info['address_id']]);
                $user_info['address_id'] = $address_info['address_id'];
                $this->refreshCookieToken($user_info, true);
            }
        }
        $address_id = $address_info['address_id'];
        unset($address_info['address_id']);
        yield $this->Address->update($address_id,$address_info);
        $this->end('ok');
    }
}