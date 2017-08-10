<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-30
 * Time: 下午2:03
 */

namespace app\Controllers;


use app\Models\Account;
use app\Models\Manager;
use app\OneException;
use Server\CoreBase\Controller;
use Server\SwooleMarco;

class BaseController extends Controller
{
    /**
     * @var Account
     */
    protected $Account;
    /**
     * @var Manager
     */
    protected $Manager;
    /**
     * 用户信息
     * @var array
     */
    protected $userInfo;

    public function initialization($controller_name, $method_name)
    {
        parent::initialization($controller_name, $method_name);
        $this->Account = $this->loader->model('Account', $this);
        $this->Manager = $this->loader->model('Manager', $this);
        $this->userInfo = null;
    }

    /**
     * @param $template
     * @param $data mixed
     * @param string $title
     */
    protected function endRenderData($template, $data, $title = '')
    {
        $this->http_output->setHeader('Content-Type', 'text/html; charset=UTF-8');
        $base = get_instance()->templateEngine->render("app::base", [
            'data' => json_encode($data, JSON_UNESCAPED_UNICODE),
            'userInfo' => json_encode($this->userInfo, JSON_UNESCAPED_UNICODE),
            'title' => $title
        ]);
        $data = date("Y-m-d h:i:sa");
        //print_r("=={$data}==============={$this->context['method_name']}====================\n");
        //print_r($base);
        //print_r("\n\n");
        $host = $this->request->header['host'];
        $path = $this->request->server['path_info'];
        $www_path = get_instance()->getHostRoot($host) . $path . '.html';
        if (file_exists($www_path)) {
            swoole_async_readfile($www_path, function ($filename, $content) use ($base) {
                $this->http_output->end($base . $content);
            });
        } else {
            $this->redirect404();
        }
    }

    /**
     * 管理员登录
     * @return array
     * @throws OneException
     */
    protected function manager_login()
    {
        return;
        //首先token登录,从cookie中找
        $manager_token = $this->http_input->cookie('manager_token');
        if (!empty($manager_token)) {
            return $this->Manager->tokenLogin($manager_token);
        } else {
            throw new OneException('需要管理员权限');
        }
    }

    /**
     * 微信登录/登录
     * @param bool $autoRedirect
     * @return mixed
     */
    protected function login($autoRedirect = false)
    {
        //首先token登录,从cookie中找
        $token = $this->http_input->cookie('token');
        //找不到，从get中找
        if (empty($token)) {
            $token = $this->http_input->get('token');
        }
        //找token试着登陆
        if (!empty($token)) {
            $this->userInfo = $this->Account->tokenLogin($token);
            return $this->userInfo;
        }
        //没有token如果有微信登录标识尝试登陆
        $code = $this->http_input->get('code');
        $state = $this->http_input->get('state');
        if (!empty($code) && $state == 'weixin_login') {
            $this->userInfo = yield $this->Account->wchatLogin($code);
            $this->refreshCookieToken($this->userInfo);
            return $this->userInfo;
        }
        //没有登陆如果自动重定向
        if ($autoRedirect) {
            $this->wxloginRedirect();
        }
        return false;
    }

    /**
     * 微信登录重定向
     */
    protected function wxloginRedirect()
    {
        $redirect_uri = 'http://' . $this->http_input->header('host') . $this->http_input->getRequestUri();
        $redirect_uri = urlencode($redirect_uri);
        //微信登陆url
        $appid = $this->config->get('wechat_appid');
        $location = "https://open.weixin.qq.com/connect/oauth2/authorize?appid=$appid&redirect_uri=$redirect_uri&response_type=code&scope=snsapi_userinfo&state=weixin_login#wechat_redirect";
        $this->redirect($location);
    }

    /**
     * 登陆重定向
     */
    protected function loginRedirect()
    {
        $redirect_uri = 'http://' . $this->http_input->header('host') . $this->http_input->getRequestUri();
        $redirect_uri = urlencode($redirect_uri);
        $location = 'http://' . $this->http_input->header('host') . "/Page/login?redirect_uri=$redirect_uri";
        $this->redirect($location);
    }

    /**
     * 判断 参数是否存在
     * @param $names
     * @param array ...$values
     * @throws OneException
     */
    protected function existValues($names, ...$values)
    {
        if (!is_array($names)) {
            $names = [$names];
        }

        for ($i = 0; $i < count($values); $i++) {
            if (empty($values[$i])) {
                throw new OneException("缺少参数 $names[$i]");
            }
        }
    }

    /**
     * @param $values
     * @param array ...$keys
     * @return mixed
     * @throws OneException
     */
    protected function existKeys($values, ...$keys)
    {
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $values)) {
                if (!empty($values[$key])) {
                    $result[$key] = $values[$key];
                    continue;
                }
            }
            throw new OneException("缺少参数 $key 或者值不存在");
        }
        return $result;
    }

    /**
     * @param $values
     * @param array ...$keys
     * @return mixed
     * @throws OneException
     */
    protected function filteKeys($values, ...$keys)
    {
        $result = [];
        foreach ($keys as $key) {
            if (array_key_exists($key, $values)) {
                if (!empty($values[$key])) {
                    $result[$key] = $values[$key];
                    if (is_array($result[$key])) {
                        $result[$key] = json_encode($result[$key]);
                    }
                }
            }
        }
        return $result;
    }

    /**
     * http参数bool转换
     * @param $bool
     * @return bool
     */
    protected function getBool($bool)
    {
        $_bool = strtolower($bool);
        switch ($_bool) {
            case 'true':
                return true;
            case 'false':
                return false;
            default:
                return false;
        }
    }

    /**
     * http参数bool转换
     * @param $bool
     * @return bool
     */
    protected function getBoolNum($bool)
    {
        $_bool = strtolower($bool);
        switch ($_bool) {
            case '1':
                return 1;
            case 'true':
                return 1;
            case 'false':
                return 0;
            default:
                return 0;
        }
    }

    /**
     * 统一输出
     * @param $output
     * @param $code
     */
    protected function end($output, $code = 'success')
    {
        $this->http_output->setHeader('Access-Control-Allow-Origin', '*');
        $this->http_output->setHeader('Content-Type', 'application/json; charset=UTF-8');
        if (is_array($output)) {
            $output['code'] = $code;
        } else {
            $_output['code'] = $code;
            $_output['msg'] = $output;
            $output = $_output;
        }
        $end = json_encode($output, JSON_UNESCAPED_UNICODE);
        $data = date("Y-m-d h:i:sa");
        //print_r("=={$data}==============={$this->context['method_name']}====================\n");
        //print_r($end);
        //print_r("\n\n");
        $this->http_output->end($end);
    }

    public function onExceptionHandle(\Exception $e, $handle = null)
    {
        parent::onExceptionHandle($e, function (\Exception $e) {
            switch ($this->request_type) {
                case SwooleMarco::HTTP_REQUEST:
                    $this->end($e->getMessage(), $e->getCode());
                    break;
                case SwooleMarco::TCP_REQUEST:
                    $this->send($e->getMessage());
                    break;
            }
        });
    }

    /**
     * 刷新token
     * @param $user_info
     * @param bool $force
     */
    protected function refreshCookieToken($user_info, $force = false)
    {
        if ($force) {
            unset($user_info['token']);
        }
        $token = $this->Account->getToken($user_info);
        $this->http_output->setCookie('token', $token, 0, '/', "", false, true);
    }

    /**
     * 清除token
     * @param string $name
     */
    protected function clearCookieToken($name = 'token')
    {
        $this->http_output->setCookie($name, '');
    }
}