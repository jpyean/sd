<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-30
 * Time: 下午5:54
 */

namespace app\Models;


/**
 * 用户行为模块
 * Class User
 * @package app\Models
 */
class User extends BaseModel
{
    public function initialization(&$context)
    {
        parent::initialization($context);
    }

    /**
     * 通知中奖
     * @param $uid
     */
    public function noticeWinning($uid)
    {
        var_dump($uid.'中奖了！');
    }
}