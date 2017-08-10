<?php

/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-31
 * Time: 下午5:07
 */

/**
 * 微信支付
 */
//微信参数
$config['wechat_appid']         = '*******************'; //微信支付id
$config['wechat_appsecret']     = '*******************'; //微信分享使用的secret
$config['wechat_appkey']        = '*******************'; //微信支付使用的key
$config['wechat_partner']       = '*******************'; //微信支付使用的商户号
$config['wechat_callback']      = '*******************'; //微信支付回调地址
$config['wechat_url']           = '*******************'; //微信支付接口API URL前缀
$config['wechat_unifiedorder']  = '*******************'; //微信支付接口API下单接口

return $config;
