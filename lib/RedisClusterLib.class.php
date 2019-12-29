<?php
/**
 * Created by PhpStorm.
 * User: linton.cao
 * Date: 2019/12/10
 * Time: 14:07
 */
namespace lib;
use RedisCluster;

/**
 * Redis Cluster Lib
 * ��phpRedis���н�һ����װ��������ԭ��������ֱ�ӵ���
 * ����Ҫȥ����һλ��key����ͨ��keyConfig����setKeyInfo������key
 * ֧����ʽ����
 * Class RedisClusterLib
 * @package lib
 */
class RedisClusterLib {
    /**
     * redis��Ⱥ���������ļ�·��������
     * @var string
     */
    protected $INI_PATH = CONF_PATH . 'redis/redisCluster.ini.php';

    protected $INI_FILE = null;

    protected $INI_CONFIG = null;

    /**
     * redis��KEY�����ļ�·��������
     * @var string
     */
    protected $KEY_PATH = CONF_PATH . 'redis/redis.key.php';

    protected $KEY_FILE = null;

    protected $KEY_CONFIG = null;

    /**
     * key������ָ�����Ĭ��_
     * @var string
     */
    protected $KEY_DELIMITER = '_';

    /**
     * redisClusterʵ��
     * @var RedisCluster
     */
    protected $CLUSTER;

    /**
     * redisCluster���ƣ�ͨ��ini��ȡ
     * @var null
     */
    private $CLUSTER_NAME = null;

    /**
     * ��Ⱥ����ģʽ������setMode�޸�
     * @var int
     */
    private $MODE = 1;

    /**
     * RedisCluster constructor.
     * @param string $clusterName ��Ӧ�����ļ����ģ����
     * @param int $mode
     * 1 (default) ����������������ˣ����Զ��ӻ�
     * 2 �������
     * 3 ����ӻ�
     * 4 ֻ������
     */
    public function __construct($clusterName = 'cluster1', $mode = 1) {
        $this->INI_FILE = include ($this->INI_PATH);
        $this->KEY_FILE = include ($this->KEY_PATH);

        $this->connect($clusterName, $mode);
    }

    /**
     * ��������
     * @param $clusterName
     * @param int $mode
     * @return $this
     */
    public function connect($clusterName, $mode = 1) {
        $this->CLUSTER_NAME = trim($clusterName);
        $this->INI_CONFIG = $this->INI_FILE[$this->CLUSTER_NAME];

        try {
            if (!empty($this->INI_CONFIG['nodes'])) {
                // ����3�Σ��ɹ�������
                for ($i = 0; $i < 3; $i++) {
                    $this->CLUSTER = new RedisCluster(null, $this->INI_CONFIG['nodes'], null, null, false, $this->INI_CONFIG['auth'] ? $this->INI_CONFIG['auth'] : null);
                    $this->setMode($mode);
                    break;
                }
                // ���ӳɹ�������key�ֵ�
                $this->KEY_CONFIG = $this->KEY_FILE[$this->CLUSTER_NAME];
            } else {
                throw new \RedisClusterException('invalid redis cluster config', '010204');
            }
        } catch (\RedisClusterException $e) {
            if (RUN_ENV == 'dev') echo $e->getCode() . ':' . $e->getMessage() . "\n";
            // cluster����ʧ�ܺ��¼��־
            $this->Log([
                'return' => $e,
                'msg'    => 'connect redis cluster failed'
            ]);
            $this->setError($e->getCode());
        }

        return $this;
    }

    /**
     * �쳣��¼��־
     * @param $info
     * @return bool
     * ���Զ��壬����Ҫ��ע�͵�
     */
    private function Log ($info) {
        Log::error([
            'type' => 'redis_cluster',
            'call_file' => __FILE__,
            'call_function' => __FUNCTION__,
            'param' => [
                'time' => date("Y-m-d H:i:s")
            ],
            'return' => empty($info['return']) ? '' : $info['return'],
            'msg' => empty($info['msg']) ? '' : $info['msg']
        ]);
        return false;
    }

    /**
     * ���ü�Ⱥ����ģʽ
     * @param $mode
     * @return $this
     */
    public function setMode($mode) {
        $this->MODE = intval($mode);
        switch ($mode) {
            case 2:
                // Always distribute readonly commands between masters and slaves, at random
                $this->CLUSTER->setOption(RedisCluster::OPT_SLAVE_FAILOVER, RedisCluster::FAILOVER_DISTRIBUTE);
                break;
            case 3:
                // Always distribute readonly commands to the slaves, at random
                $this->CLUSTER->setOption(RedisCluster::OPT_SLAVE_FAILOVER, RedisCluster::FAILOVER_DISTRIBUTE_SLAVES);
                break;
            case 4:
                // only send commands to master nodes
                $this->CLUSTER->setOption(RedisCluster::OPT_SLAVE_FAILOVER, RedisCluster::FAILOVER_NONE);
                break;
            case 1:
            default:
                // In the event we can't reach a master, and it has slaves, failover for read commands
                $this->CLUSTER->setOption(RedisCluster::OPT_SLAVE_FAILOVER, RedisCluster::FAILOVER_ERROR);
        }
        return $this;
    }

    /**
     * ����
     */
    public function __destruct() {
        // �Ͽ�����
        if (is_object($this->CLUSTER)) {
            $this->CLUSTER->close();
        }
    }

    /**
     * ������ʱ����key�����Ϣ
     * @var
     */
    private $keyInfo;

    /**
     * �����Ҫ���Զ�key��������key������������ֱ����ʽ����
     * @param $keyConfigName
     * @param $postfixValue
     * @return $this
     */
    public function keyConfig($keyConfigName, $postfixValue = []) {
        try {
            if (!empty($this->KEY_CONFIG) && isset($this->KEY_CONFIG[$keyConfigName])) {
                $this->keyInfo = $this->KEY_CONFIG[$keyConfigName];
                // ���ú�׺
                if (!empty($postfixValue)) {
                    $tmpConfigPostfix = '';
                    // ��˳���ȡ�������postfix
                    foreach ($this->keyInfo['postfix'] as $defaultKey) {
                        if (key_exists($defaultKey, $postfixValue)){
                            $tmpConfigPostfix .= $this->KEY_DELIMITER . $defaultKey . $this->KEY_DELIMITER . $postfixValue[$defaultKey];
                        } else {
                            throw new \RedisClusterException('invalid postfix value', '010205');
                        }
                    }
                }
                // ����key���к�׺��׷���ں��棬û��׺��ֱ����key
                $this->keyInfo['key'] .= empty($tmpConfigPostfix) ? '' :$tmpConfigPostfix;
            } else {
                throw new \RedisClusterException('invalid key config or key name', '010203');
            }
        } catch (\RedisClusterException $e) {
            if (RUN_ENV == 'dev') echo $e->getCode() . ':' .$e->getMessage() . "\n";
            // key���ò���ȷ
            $this->Log([
                'return' => $e,
                'msg'    => 'set key config failed'
            ]);
            $this->setError($e->getCode());
        }
        return $this;
    }

    /**
     * ������ʱkey���ã���������ֱ����ʽ����
     * @param $keyName
     * @param null $ttl
     * @return $this
     */
    public function setKeyInfo($keyName, $ttl = null){
        $this->keyInfo = [
            'key' => $keyName,
            'ttl' => $ttl,
        ];
        return $this;
    }

    /**
     * ħ������
     * ʵ�ʵ���redisCluster�ĸ�����
     * @param $function
     * @param $args
     * @return $this
     */
    public function __call($function, $args){
        // ��ʽ����ʱ������д�ֱ�ӷ���error
        if (!empty($this->error)) return $this;
        try {
            if (is_object($this->CLUSTER)) {
                // keyδ����ֱ�ӱ���
                if (empty($this->keyInfo['key'])) throw new \RedisClusterException('invalid key', '010203');

                // ��һ����������key
                array_unshift($args, $this->keyInfo['key']);
                $function = ltrim($function, '_');
                $result = call_user_func_array([$this->CLUSTER, $function], $args);
                $this->setResult($result);
            } else {
                throw new \RedisClusterException('invalid redis cluster', '010001');
            }
        } catch (\RedisClusterException $e){
            if (RUN_ENV == 'dev') echo $e->getCode() . ':' . $e->getMessage() . "\n";
            // cluster����ʧ�ܺ��¼��־
            $this->Log([
                'return' => $e,
                'msg'    => 'call redis cluster function failed'
            ]);
            $this->setError($e->getCode());
        }
        return $this;
    }

    /**
     * �����ַ�����ֵ
     * @param string $value ֵ
     * @param null $timeout ����ʱ�䣬֧��formatTTLת��
     * Ĭ��null ��ȡ������ttl
     *     -1   ��������
     * @return $this
     */
    public function set($value, $timeout = null) {
        // Ĭ��ȡ����
        if (!isset($timeout) && !empty($this->keyInfo)) $timeout = $this->keyInfo['ttl'];
        // set�Ĺ���ʱ�䣬��null�Ŵ�������
        if ($timeout == -1) $timeout = null;
        // ������ʽ
        $timeout = $this->formatTTL($timeout);

        return $this->_set($value, $timeout);
    }

    /**
     * ��ȡ�ַ�����ֵ
     * �޴��Σ���keyConfig����setKeyInfo
     * @return $this
     */
    public function get() {
        return $this->_get();
    }

    /**
     * ���ù���ʱ�䣬������ˢ��
     * @param $timeout -����ʱ��, ֧��formatTTLת��
     * Ĭ��null ��ȡ������ttl
     *     -1   ��������
     * @return $this
     */
    public function expire($timeout = null) {
        // Ĭ��ȡ����
        if (!isset($timeout) && !empty($this->keyInfo)) $timeout = $this->keyInfo['ttl'];
        // ȡ��������Ϊnull����-1
        if (!isset($timeout)) $timeout = -1;
        // ������ʽ
        $timeout = $this->formatTTL($timeout);
        // ���Ϊ-1������persist��ȥ������ʱ�䣬�������ֵ���expire
        return $timeout === -1 ? $this->_persist() : $this->_expire($timeout);
    }

    /**
     * ����+value
     * @param $value -����Ĭ��+1
     * @return $this
     */
    public function incr($value = null) {
        return isset($value) ? $this->_incr( intval($value) ) : $this->_incr();
    }

    /**
     * �Լ�-value
     * @param $value -����Ĭ��-1
     * @return $this
     */
    public function decr($value = null) {
        return isset($value) ? $this->_decr( intval($value) ) : $this->_decr();
    }

    /**
     * ɾ��key
     * @return $this
     */
    public function del(){
        return $this->_del();
    }


//    public function scan(){
//        $it = NULL;
//        do {
//            // Scan for some keys
//            $arr_keys = $this->CLUSTER->scan($it, "10.100.2.11:7000", "*");
//
//            // Redis may return empty results, so protect against that
//            if ($arr_keys !== FALSE) {
//                foreach($arr_keys as $str_key) {
//                    echo "Here is a key: $str_key\n";
//                }
//            }
//        } while ($it > 0);
//        echo "No more keys to scan!\n";
//    }

    /**
     * TTLʱ��ת������
     * @param $type
     *  -1 ��������
     *  ������ ��������
     *  today �˿���23:59:59
     *  24h   24Сʱ��
     * @return false|float|int|null
     */
    private function formatTTL($type){
        $ttl = null;
        switch ($type) {
            case null:
                break;
            case -1:
                $ttl = -1;
                break;
            case is_numeric($type):
                $ttl = intval($type);
                break;
            case 'today':
                $ttl = strtotime(date("Y-m-d",strtotime("+1 day"))) - time();
                break;
            case '24h':
                $ttl = 3600 * 24;
                break;
            case '1h':
                $ttl = 3600;
                break;
        }
        return $ttl;
    }

    /**
     * ��������
     * ����д���error��û�д���result
     * @return $this->result|$this->error
     */
    public function end(){
        return empty($this->error) ? $this->result : $this->error;
    }

    /**
     * ���ؽ��
     * @var null
     */
    public $result = null;

    /**
     * ���÷��ؽ��
     * @param $result
     * @return array|null
     */
    private function setResult($result){
        return $this->result = returnResult($result);
    }

    /**
     * ���Ӵ������ԣ����ڷ��ش�����Ϣ
     * @var null
     */
    public $error = null;

    /**
     * ��������ʱ�����ô���CODE
     * @param $errorCode
     * @return array
     */
    private function setError($errorCode){
        if (empty($errorCode)) $errorCode = '999999';
        $this->result = null;
        $errorCode = str_pad($errorCode, 6, 0, STR_PAD_LEFT);

        return $this->error = errCode($errorCode);
    }
}