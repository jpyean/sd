<?php
/**
 * Created by PhpStorm.
 * User: Olly
 * Date: 2016/8/31
 * Time: 13:48
 */

namespace app\Models;

use app\OneException;
use Server\Asyn\HttpClient\HttpClientPool;

class MWechat extends BaseModel
{
    private $wechat_appkey;
    private $wechat_appid;
    private $wechat_partner;
    private $wechat_callback;
    private $wechat_url;
    private $wechat_unifiedorder;
    /**
     * @var HttpClientPool
     */
    private $Wechat_HttpClient;
    /**
     * @var HttpClientPool
     */
    private $WenXinHttpClient;

    public function initialization(&$context)
    {
        parent::initialization($context);
        $this->wechat_appkey = $this->config['wechat_appkey'];                   //微信支付使用的key;
        $this->wechat_appid = $this->config['wechat_appid'];                    //微信支付id;
        $this->wechat_partner = $this->config['wechat_partner'];                  //微信支付使用的商户号;
        $this->wechat_callback = $this->config['wechat_callback'];                 //微信支付回调地址(正式);
        $this->wechat_url = $this->config['wechat_url'];                          //微信支付接口API URL前缀
        $this->wechat_unifiedorder = $this->config['wechat_unifiedorder'];             //微信支付接口API下单接口
        $this->Wechat_HttpClient = get_instance()->getAsynPool('Wechat_HttpClient');
        $this->WenXinHttpClient = get_instance()->getAsynPool('WeiXinAPI');
    }

    /**
     * 登录
     * @param $code
     * @return mixed
     * @throws OneException
     */
    public function login($code)
    {
        $response = yield $this->WenXinHttpClient->httpClient->setQuery([
            'appid' => $this->config->get('wechat_appid'),
            'secret' => $this->config->get('wechat_appsecret'),
            'code' => $code,
            'grant_type' => 'authorization_code'
        ])->coroutineExecute('/sns/oauth2/access_token');
        $json = $response['body'];
        $info = json_decode($json, true);
        if (array_key_exists('errcode', $info)) {
            throw new OneException('微信登录失败'.$info['errmsg']);
        }

        //可以拉取用户信息了
        $response = yield $this->WenXinHttpClient->httpClient->setQuery([
            'access_token' => $info['access_token'],
            'openid' => $info['openid'],
            'lang' => 'zh_CH'
        ])->coroutineExecute('/sns/userinfo');
        $json = $response['body'];

        $wuser_info = json_decode($json, true);
        if (array_key_exists('errcode', $wuser_info)) {
            throw new OneException('微信登录失败'.$wuser_info['errmsg']);
        }

        return $wuser_info;
    }

    /**
     * 统一下单方法
     * @param $order_id 自定义的订单号
     * @param $openid
     * @param $money 订单金额 只能为整数 单位为分
     * @param $ip 用户端实际ip
     * @return bool|mixed
     */
    public function unifiedOrder($order_id,$openid,$money,$ip)
    {
        $nonce_str = $this->getRandStr(30);
        $params['out_trade_no'] = $order_id;                                 //自定义的订单号
        $params['total_fee'] = $money;                                       //订单金额 只能为整数 单位为分
        $params['spbill_create_ip'] = $ip;
        $params['appid'] = $this->wechat_appid;
        $params['openid'] = $openid;
        $params['mch_id'] = $this->wechat_partner;
        $params['nonce_str'] = $nonce_str;
        $params['trade_type'] = 'JSAPI';
        $params['notify_url'] = $this->wechat_callback;
        $params['body'] = '再来一单-微店支付';
        //获取签名数据
        $sign = $this->makeSign($params);
        $params['sign'] = $sign;
        $xml = $this->data_to_xml($params);

        $response = yield $this->postXmlCurl($xml);
        if (!$response) {
            return false;
        }
        $result = $this->xml_to_data($response);
        if (!empty($result['result_code']) && !empty($result['err_code'])) {
            $result['err_msg'] = $this->error_code($result['err_code']);
        }
        return $result;
    }

    /**
     *生成APP端支付参数
     * @param $prepayid
     * @return mixed
     */
    public function getAppPayParams($prepayid)
    {
        $data['appId'] = $this->wechat_appid;
        $data['package'] = "prepay_id=$prepayid";
        $data['nonceStr'] = $this->getRandStr(30);
        $data['timeStamp'] = time()."";
        $data['signType'] = 'MD5';
        $data['paySign'] = $this->makeSign($data);
        return $data;
    }

    /**
     * 微信签名验证
     * @param $sign
     * @param $post_arr
     * @return bool
     */
    public function wx_sign($sign,$post_arr){
        $app_key = $this->config['wechat_appkey'];
        $sign_true = '';
        ksort($post_arr);
        foreach($post_arr as $key=> $val){
            if(empty($val) || $key=='sign'){
                continue;
            }
            $sign_true .= $key."=".$val."&";
        }
        $sign_true .="key=".$app_key;
        $my_sign = strtoupper(md5($sign_true));
        if($sign==$my_sign){
            return true;
        }else{
            return false;
        }
    }
    /**
     * array转为xml格式
     * @param $params
     * @return bool|string
     */
    private function data_to_xml($params)
    {
        if (!is_array($params) || count($params) <= 0) {
            return false;
        }
        $xml = "<xml>";
        foreach ($params as $key => $val) {
            if (is_numeric($val)) {
                $xml .= "<" . $key . ">" . $val . "</" . $key . ">";
            } else {
                $xml .= "<" . $key . "><![CDATA[" . $val . "]]></" . $key . ">";
            }
        }
        $xml .= "</xml>";
        return $xml;
    }

    /**
     * 将xml转为array
     * @param $xml
     * @return bool|mixed
     */
    protected function xml_to_data($xml)
    {
        if (!$xml) {
            return false;
        }
        //将XML转为array
        //禁止引用外部xml实体
        libxml_disable_entity_loader(true);
        $data = json_decode(json_encode(simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA)), true);

        return $data;
    }

    /**
     * post xml 到微信请求统一下单接口
     * @param $xml
     * @return mixed
     */
    private function postXmlCurl($xml)
    {
        $xmlData = $xml;
        $response = yield $this->Wechat_HttpClient->httpClient->setData($xmlData)
            ->setHeaders(['Content-type' => 'application/xml'])
            ->setMethod('POST')->coroutineExecute($this->config->get('wechat_unifiedorder'));

        return $response['body'];

    }

    /**
     * 随机生成以dl开头的字符串  长度为length+2
     * @param $length
     * @return string
     */
    private function getRandStr($length)
    {
        $str = "QWERTYUIOPASDFGHJKLZXCVBNM1234567890qwertyuiopasdfghjklzxcvbnm";
        str_shuffle($str);
        $rand_str = "dl" . substr(str_shuffle($str), 0, $length);
        return $rand_str;
    }

    /**
     * 生成签名
     * @param $params
     * @return string
     */
    private function makeSign($params)
    {
        $app_key = $this->wechat_appkey;
        $sign_true = '';
        ksort($params);                             //签名步骤一：按字典序排序数组参数
        foreach ($params as $key => $val) {
            if (empty($val) || $key == 'sign') {
                continue;
            }
            $sign_true .= $key . "=" . $val . "&";
        }
        $sign_true .= "key=" . $app_key;            //签名步骤二：在string后加入KEY
        $my_sign = strtoupper(md5($sign_true));  //签名步骤三：MD5加密 //签名步骤四：所有字符转为大写
        return $my_sign;
    }

    /**
     * 错误代码
     * @param $code
     * @return mixed
     */
    private function error_code($code)
    {
        $errList = array(
            'NOAUTH' => '商户未开通此接口权限',
            'NOTENOUGH' => '用户帐号余额不足',
            'ORDERNOTEXIST' => '订单号不存在',
            'ORDERPAID' => '商户订单已支付，无需重复操作',
            'ORDERCLOSED' => '当前订单已关闭，无法支付',
            'SYSTEMERROR' => '系统错误!系统超时',
            'APPID_NOT_EXIST' => '参数中缺少APPID',
            'MCHID_NOT_EXIST' => '参数中缺少MCHID',
            'APPID_MCHID_NOT_MATCH' => 'appid和mch_id不匹配',
            'LACK_PARAMS' => '缺少必要的请求参数',
            'OUT_TRADE_NO_USED' => '同一笔交易不能多次提交',
            'SIGNERROR' => '参数签名结果不正确',
            'XML_FORMAT_ERROR' => 'XML格式错误',
            'REQUIRE_POST_METHOD' => '未使用post传递参数 ',
            'POST_DATA_EMPTY' => 'post数据不能为空',
            'NOT_UTF8' => '未使用指定编码格式',
        );
        if (array_key_exists($code, $errList)) {
            return $errList[$code];
        }
    }


}