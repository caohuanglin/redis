
<?php
/**
 * ����
 * Created by PhpStorm.
 * User: linton.cao
 * Date: 2019/12/6
 * Time: 15:47
 */
$oCluster = new \lib\RedisClusterLib();

/**
 * ����keyģʽ
 */

// ��ȡ�����ļ�
$result = $oCluster
    // ��Ӧkeyֵphone_code_phone_18888888888
    ->keyConfig('phoneCode', [
        'phone' => '18888888888'
    ])
    ->get()
    ->end();

// ����ȡ�����ļ����Զ���key��
// ��Ӧkeyֵkey1������ʱ��10��
$oCluster->setKeyInfo('key1', 10)->get()->end();

var_dump($result->result);
var_dump($result->error);
var_dump($result);