<?php
namespace app;

use Server\Asyn\HttpClient\HttpClientPool;
use Server\SwooleDistributedServer;

/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-9-19
 * Time: 下午2:36
 */
class AppServer extends SwooleDistributedServer
{
    /**
     * 开服初始化(支持协程)
     * @return mixed
     */
    public function onOpenServiceInitialization()
    {
        parent::onOpenServiceInitialization();
    }

    /**
     * 当一个绑定uid的连接close后的清理
     * 支持协程
     * @param $uid
     */
    public function onUidCloseClear($uid)
    {
        // TODO: Implement onUidCloseClear() method.
    }

    public function initAsynPools()
    {
        parent::initAsynPools();
        $this->addAsynPool('GetIPAddress',new HttpClientPool($this->config,'http://int.dpool.sina.com.cn'));
        $this->addAsynPool('WeiXinAPI',new HttpClientPool($this->config,'https://api.weixin.qq.com'));
        $this->addAsynPool('Wechat_HttpClient', new HttpClientPool($this->config, $this->config->get('wechat_url')));//微信支付
    }

}