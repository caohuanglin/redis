<?php
/**
 * key配置文件
 * Created by PhpStorm.
 * User: linton.cao
 * Date: 2019/12/11
 * Time: 16:03
 */

return [
    'cluster1' => [
        // 例子：短信验证码缓存
        'phoneCode' => [
            // key值
            'key' => 'phone_code',
            // key值后缀
            'postfix' => [
                'phone',
            ],
            // 过期时间
            'ttl' => 60,
        ],
    ],
];