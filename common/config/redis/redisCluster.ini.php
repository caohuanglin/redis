<?php
/**
 * 集群地址、秘钥配置
 * Created by PhpStorm.
 * User: linton.cao
 * Date: 2019/12/10
 * Time: 15:54
 */

if (RUN_ENV == 'ONLINE') {
    //线上配置
    return [

    ];
}
else
{
    return [
        'cluster1' => [
            // 集群auth 123456
            'auth' => '123456',
            'nodes' => [
                '127.0.0.1:7000',
                '127.0.0.1:7001',
                '127.0.0.1:7002',
                '127.0.0.1:7003',
                '127.0.0.1:7004',
                '127.0.0.1:7005',
            ]
        ]
    ];
}