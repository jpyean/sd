<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-30
 * Time: 下午2:02
 */

namespace app\Controllers;

class ManagerAuth extends BaseController
{
    public function initialization($controller_name, $method_name)
    {
        parent::initialization($controller_name, $method_name);
    }

    /**
     * 管理员登录
     */
    public function http_login()
    {
        $this->clearCookieToken('manager_token');
        $manager_name = $this->http_input->post('manager_name');
        $manager_key = $this->http_input->post('manager_key');
        $this->existValues(['manager_name','manager_key'],$manager_name,$manager_key);
        $info = yield $this->Manager->getInfo($manager_name,$manager_key);
        $token = $this->Manager->getToken($info);
        $this->http_output->setCookie('manager_token',$token);
        $this->end('ok');
    }
}