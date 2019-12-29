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
 * 对phpRedis进行进一步封装，对于其原生函数可直接调用
 * 但需要去除第一位的key，并通过keyConfig或者setKeyInfo进行设key
 * 支持链式调用
 * Class RedisClusterLib
 * @package lib
 */
class RedisClusterLib {
    /**
     * redis集群机器配置文件路径及配置
     * @var string
     */
    protected $INI_PATH = CONF_PATH . 'redis/redisCluster.ini.php';

    protected $INI_FILE = null;

    protected $INI_CONFIG = null;

    /**
     * redis各KEY配置文件路径及配置
     * @var string
     */
    protected $KEY_PATH = CONF_PATH . 'redis/redis.key.php';

    protected $KEY_FILE = null;

    protected $KEY_CONFIG = null;

    /**
     * key名各项分隔符，默认_
     * @var string
     */
    protected $KEY_DELIMITER = '_';

    /**
     * redisCluster实例
     * @var RedisCluster
     */
    protected $CLUSTER;

    /**
     * redisCluster名称，通过ini读取
     * @var null
     */
    private $CLUSTER_NAME = null;

    /**
     * 集群访问模式，可用setMode修改
     * @var int
     */
    private $MODE = 1;

    /**
     * RedisCluster constructor.
     * @param string $clusterName 对应配置文件里的模块名
     * @param int $mode
     * 1 (default) 读主机，如果主挂了，可以读从机
     * 2 随机主从
     * 3 随机从机
     * 4 只读主机
     */
    public function __construct($clusterName = 'cluster1', $mode = 1) {
        $this->INI_FILE = include ($this->INI_PATH);
        $this->KEY_FILE = include ($this->KEY_PATH);

        $this->connect($clusterName, $mode);
    }

    /**
     * 主动连接
     * @param $clusterName
     * @param int $mode
     * @return $this
     */
    public function connect($clusterName, $mode = 1) {
        $this->CLUSTER_NAME = trim($clusterName);
        $this->INI_CONFIG = $this->INI_FILE[$this->CLUSTER_NAME];

        try {
            if (!empty($this->INI_CONFIG['nodes'])) {
                // 尝试3次，成功即跳出
                for ($i = 0; $i < 3; $i++) {
                    $this->CLUSTER = new RedisCluster(null, $this->INI_CONFIG['nodes'], null, null, false, $this->INI_CONFIG['auth'] ? $this->INI_CONFIG['auth'] : null);
                    $this->setMode($mode);
                    break;
                }
                // 连接成功后设置key字典
                $this->KEY_CONFIG = $this->KEY_FILE[$this->CLUSTER_NAME];
            } else {
                throw new \RedisClusterException('invalid redis cluster config', '010204');
            }
        } catch (\RedisClusterException $e) {
            if (RUN_ENV == 'dev') echo $e->getCode() . ':' . $e->getMessage() . "\n";
            // cluster连接失败后记录日志
            $this->Log([
                'return' => $e,
                'msg'    => 'connect redis cluster failed'
            ]);
            $this->setError($e->getCode());
        }

        return $this;
    }

    /**
     * 异常记录日志
     * @param $info
     * @return bool
     * 可自定义，不需要可注释掉
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
     * 设置集群访问模式
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
     * 析构
     */
    public function __destruct() {
        // 断开连接
        if (is_object($this->CLUSTER)) {
            $this->CLUSTER->close();
        }
    }

    /**
     * 用于暂时存贮key相关信息
     * @var
     */
    private $keyInfo;

    /**
     * 如果需要可以读key配置生产key名，设置完后可直接链式操作
     * @param $keyConfigName
     * @param $postfixValue
     * @return $this
     */
    public function keyConfig($keyConfigName, $postfixValue = []) {
        try {
            if (!empty($this->KEY_CONFIG) && isset($this->KEY_CONFIG[$keyConfigName])) {
                $this->keyInfo = $this->KEY_CONFIG[$keyConfigName];
                // 设置后缀
                if (!empty($postfixValue)) {
                    $tmpConfigPostfix = '';
                    // 按顺序读取配置里的postfix
                    foreach ($this->keyInfo['postfix'] as $defaultKey) {
                        if (key_exists($defaultKey, $postfixValue)){
                            $tmpConfigPostfix .= $this->KEY_DELIMITER . $defaultKey . $this->KEY_DELIMITER . $postfixValue[$defaultKey];
                        } else {
                            throw new \RedisClusterException('invalid postfix value', '010205');
                        }
                    }
                }
                // 设置key，有后缀的追加在后面，没后缀的直接用key
                $this->keyInfo['key'] .= empty($tmpConfigPostfix) ? '' :$tmpConfigPostfix;
            } else {
                throw new \RedisClusterException('invalid key config or key name', '010203');
            }
        } catch (\RedisClusterException $e) {
            if (RUN_ENV == 'dev') echo $e->getCode() . ':' .$e->getMessage() . "\n";
            // key配置不正确
            $this->Log([
                'return' => $e,
                'msg'    => 'set key config failed'
            ]);
            $this->setError($e->getCode());
        }
        return $this;
    }

    /**
     * 设置临时key配置，设置完后可直接链式操作
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
     * 魔术方法
     * 实际调用redisCluster的各方法
     * @param $function
     * @param $args
     * @return $this
     */
    public function __call($function, $args){
        // 链式操作时，如果有错直接返回error
        if (!empty($this->error)) return $this;
        try {
            if (is_object($this->CLUSTER)) {
                // key未设置直接报错
                if (empty($this->keyInfo['key'])) throw new \RedisClusterException('invalid key', '010203');

                // 第一个参数插入key
                array_unshift($args, $this->keyInfo['key']);
                $function = ltrim($function, '_');
                $result = call_user_func_array([$this->CLUSTER, $function], $args);
                $this->setResult($result);
            } else {
                throw new \RedisClusterException('invalid redis cluster', '010001');
            }
        } catch (\RedisClusterException $e){
            if (RUN_ENV == 'dev') echo $e->getCode() . ':' . $e->getMessage() . "\n";
            // cluster操作失败后记录日志
            $this->Log([
                'return' => $e,
                'msg'    => 'call redis cluster function failed'
            ]);
            $this->setError($e->getCode());
        }
        return $this;
    }

    /**
     * 设置字符串型值
     * @param string $value 值
     * @param null $timeout 过期时间，支持formatTTL转换
     * 默认null 读取配置内ttl
     *     -1   永不过期
     * @return $this
     */
    public function set($value, $timeout = null) {
        // 默认取配置
        if (!isset($timeout) && !empty($this->keyInfo)) $timeout = $this->keyInfo['ttl'];
        // set的过期时间，给null才代表永久
        if ($timeout == -1) $timeout = null;
        // 解析格式
        $timeout = $this->formatTTL($timeout);

        return $this->_set($value, $timeout);
    }

    /**
     * 获取字符串型值
     * 无传参，用keyConfig或者setKeyInfo
     * @return $this
     */
    public function get() {
        return $this->_get();
    }

    /**
     * 设置过期时间，可用于刷新
     * @param $timeout -过期时间, 支持formatTTL转换
     * 默认null 读取配置内ttl
     *     -1   永不过期
     * @return $this
     */
    public function expire($timeout = null) {
        // 默认取配置
        if (!isset($timeout) && !empty($this->keyInfo)) $timeout = $this->keyInfo['ttl'];
        // 取完配置仍为null，给-1
        if (!isset($timeout)) $timeout = -1;
        // 解析格式
        $timeout = $this->formatTTL($timeout);
        // 如果为-1，调用persist，去除过期时间，正常数字调用expire
        return $timeout === -1 ? $this->_persist() : $this->_expire($timeout);
    }

    /**
     * 自增+value
     * @param $value -不填默认+1
     * @return $this
     */
    public function incr($value = null) {
        return isset($value) ? $this->_incr( intval($value) ) : $this->_incr();
    }

    /**
     * 自减-value
     * @param $value -不填默认-1
     * @return $this
     */
    public function decr($value = null) {
        return isset($value) ? $this->_decr( intval($value) ) : $this->_decr();
    }

    /**
     * 删除key
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
     * TTL时间转换函数
     * @param $type
     *  -1 永不过期
     *  纯数字 过期秒数
     *  today 此刻至23:59:59
     *  24h   24小时整
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
     * 结束函数
     * 如果有错返回error，没有错返回result
     * @return $this->result|$this->error
     */
    public function end(){
        return empty($this->error) ? $this->result : $this->error;
    }

    /**
     * 返回结果
     * @var null
     */
    public $result = null;

    /**
     * 设置返回结果
     * @param $result
     * @return array|null
     */
    private function setResult($result){
        return $this->result = returnResult($result);
    }

    /**
     * 增加错误属性，用于返回错误信息
     * @var null
     */
    public $error = null;

    /**
     * 发生错误时，设置错误CODE
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