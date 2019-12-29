
<?php
/**
 * 例子
 * Created by PhpStorm.
 * User: linton.cao
 * Date: 2019/12/6
 * Time: 15:47
 */
$oCluster = new \lib\RedisClusterLib();

/**
 * 两种key模式
 */

// 读取配置文件
$result = $oCluster
    // 对应key值phone_code_phone_18888888888
    ->keyConfig('phoneCode', [
        'phone' => '18888888888'
    ])
    ->get()
    ->end();

// 不读取配置文件，自定义key名
// 对应key值key1，过期时间10秒
$oCluster->setKeyInfo('key1', 10)->get()->end();

var_dump($result->result);
var_dump($result->error);
var_dump($result);