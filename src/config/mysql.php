<?php
/**
 * Created by PhpStorm.
 * User: zhangjincheng
 * Date: 16-7-15
 * Time: 下午4:49
 */
$config['mysql']['active'] = 'online';
$config['mysql']['local']['host'] = '127.0.0.1';
$config['mysql']['local']['port'] = '3306';
$config['mysql']['local']['user'] = 'root';
$config['mysql']['local']['password'] = '123456';
$config['mysql']['local']['database'] = 'one';
$config['mysql']['local']['charset'] = 'utf8';
$config['mysql']['asyn_max_count'] = 10;

$config['mysql']['online']['host'] = 'localhost';
$config['mysql']['online']['port'] = '3306';
$config['mysql']['online']['user'] = 'tmtbe';
$config['mysql']['online']['password'] = '9tm6nt0Ty05zE2ul';
$config['mysql']['online']['database'] = 'one';
$config['mysql']['online']['charset'] = 'utf8';
$config['mysql']['asyn_max_count'] = 10;
return $config;
