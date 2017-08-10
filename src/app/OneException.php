<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 17-3-30
 * Time: 下午2:13
 */

namespace app;


use Exception;

class OneException extends \Exception
{
    public function __construct($message, $code = -1, Exception $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}