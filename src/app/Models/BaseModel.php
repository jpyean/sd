<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-30
 * Time: 下午2:04
 */

namespace app\Models;


use app\OneException;
use Server\CoreBase\Model;

class BaseModel extends Model
{
    /**
     * 判断mysql是否有值
     * @param $result
     * @param $message
     * @throws OneException
     */
    public function judgeMySQLHaveValue($result,$message)
    {
        if(count($result['result'])==0){
            throw new OneException($message);
        }
    }
}