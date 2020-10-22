<?php
// +----------------------------------------------------------------------
// | ThinkPHP [ WE CAN DO IT JUST THINK IT ]
// +----------------------------------------------------------------------
// | Copyright (c) 2006-2014 http://thinkphp.cn All rights reserved.
// +----------------------------------------------------------------------
// | Licensed ( http://www.apache.org/licenses/LICENSE-2.0 )
// +----------------------------------------------------------------------
// | Author: liu21st <liu21st@gmail.com>
// +----------------------------------------------------------------------
namespace Think\Cache\Driver;

use Think\Cache;
use Think\Exception;

defined('THINK_PATH') or exit();

/**
 * Redis缓存驱动
 * 要求安装phpredis扩展：https://github.com/nicolasff/phpredis
 */
class Redis extends Cache
{

    const AFTER = 'after';
    const BEFORE = 'before';

    /**
     * Options
     */
    const OPT_SERIALIZER = 1;
    const OPT_PREFIX = 2;
    const OPT_READ_TIMEOUT = 3;
    const OPT_SCAN = 4;

    /**
     * Serializers
     */
    const SERIALIZER_NONE = 0;
    const SERIALIZER_PHP = 1;
    const SERIALIZER_IGBINARY = 2;

    /**
     * Multi
     */
    const ATOMIC = 0;
    const MULTI = 1;
    const PIPELINE = 2;

    /**
     * Type
     */
    const REDIS_NOT_FOUND = 0;
    const REDIS_STRING = 1;
    const REDIS_SET = 2;
    const REDIS_LIST = 3;
    const REDIS_ZSET = 4;
    const REDIS_HASH = 5;

    /**
     * Scan
     */
    const SCAN_NORETRY = 0;
    const SCAN_RETRY = 1;

    /**
     * Key Position
     */
    const KEY_NO_KEY = -1;
    const KEY_FIRST = 0;
    const KEY_NOT_FIRST = 1;
    const KEY_EVERY = 2;
    const KEY_FIRST_AND_SECOND = 3;
    const KEY_SECOND = 4;
    const KEY_THIRD = 5;

    // 保存全局实例
    private static $_instance = null;

    // 保存所有操作实例
    private static $_handler = [];

    /**
     * 架构函数
     * @param array $options 缓存参数
     * @access public
     */
    public function __construct($options = array())
    {
        if (!extension_loaded('redis')) {
            E(L('_NOT_SUPPORT_') . ':redis');
        }
        $options = array_merge(array(
            'host' => C('REDIS_HOST') ?: '127.0.0.1',
            'port' => C('REDIS_PORT') ?: '6379',
            'timeout' => C('DATA_CACHE_TIMEOUT') ?: false,
            'persistent' => C('REDIS_PERSISTENT') ?: false,
            'redis_auth' => C('REDIS_AUTH') ?: '',
        ), $options);

        $options['host'] = explode(',', $options['host']);
        $options['port'] = explode(',', $options['port']);
        $options['redis_auth'] = explode(',', $options['redis_auth']);
        $this->options = $options;
        $this->options['expire'] = isset($options['expire']) ? $options['expire'] : C('DATA_CACHE_TIME');
        $this->options['prefix'] = isset($options['prefix']) ? $options['prefix'] : C('DATA_CACHE_PREFIX');
        $this->options['length'] = isset($options['length']) ? $options['length'] : 0;
        $func = $this->options['persistent'] ? 'pconnect' : 'connect';

        // hj 2017-11-12 14:07:26 支持读写分离
        $count = count($this->options['host']);
        for ($i = 0; $i < $count; $i++) {
            self::$_handler[$i] = new \Redis;
            $options['timeout'] === false ?
                self::$_handler[$i]->$func($this->options['host'][$i], $this->options['port'][$i]) :
                self::$_handler[$i]->$func($this->options['host'][$i], $this->options['port'][$i], $this->options['timeout'][$i]);
            if (!empty($this->options['redis_auth'][$i])) {
                self::$_handler[$i]->auth($this->options['redis_auth'][$i]);
            }
        }
        // 默认使用主库
        $this->handler = self::$_handler[0];
    }

    /**
     * @param $type
     * @param $options
     * @return $this
     * User: hj
     * Desc: 单例模式
     * Date: 2017-11-12 13:39:58
     * Update: 2017-11-12 13:39:59
     * Version: 1.0
     */
    static public function getInstance($type = '', $options = array())
    {
        if (empty(self::$_instance)) self::$_instance = new self($options);
        return self::$_instance;
    }

    /**
     * @param bool $bool
     * @return object
     * User: hj
     * Desc: 判断是否master/slave,调用不同的master或者slave实例
     * Date: 2017-11-12 13:54:11
     * Update: 2017-11-12 13:54:11
     * Version: 1.0
     */
    public function isMaster($bool = true)
    {
        $count = count(self::$_handler);
        $i = $bool || 1 == $count ? 0 : rand(1, $count - 1);
        return self::$_handler[$i];
    }

    /**
     * @param int $dbIndex
     * @return bool true|false
     * User: hj
     * Desc: 选择数据库 0-15. 每个操作实例都需要切换到相同的数据库(分布式)
     * Date: 2017-11-12 19:51:00
     * Update: 2017-11-12 19:51:02
     * Version: 1.0
     */
    public function select($dbIndex = 0)
    {
        $count = count(self::$_handler);
        $result = false;
        for ($i = 0; $i < $count; $i++) {
            $result = self::$_handler[$i]->select($dbIndex);
        }
        return $result;
    }

    /**
     * @return bool true|false
     * User: hj
     * Desc: 测试连通 每个实例都测试一遍
     * Date: 2017-11-12 20:02:52
     * Update: 2017-11-12 20:02:53
     * Version: 1.0
     */
    public function ping()
    {
        $count = count(self::$_handler);
        $result = false;
        for ($i = 0; $i < $count; $i++) {
            $result = self::$_handler[$i]->ping();
            if ('+PONG' !== $result) return false;
        }
        return $result === '+PONG';
    }

    /**
     * 读取缓存
     * @access public
     * @param string $name 缓存变量名
     * @return mixed
     */
    public function get($name)
    {
        N('cache_read', 1);
        $this->handler = $this->isMaster(false);
        $value = $this->handler->get($this->options['prefix'] . $name);
        $jsonData = json_decode($value, true);
        return ($jsonData === NULL) ? $value : $jsonData;    //检测是否为JSON数据 true 返回JSON解析数组, false返回源数据
    }

    /**
     * 写入缓存
     * @access public
     * @param string $name 缓存变量名
     * @param mixed $value 存储数据
     * @param integer $expire 有效时间（秒）
     * @return boolean
     */
    public function set($name, $value, $expire = null)
    {
        N('cache_write', 1);
        $this->handler = $this->isMaster(true);
        if (is_null($expire)) {
            $expire = $this->options['expire'];
        }
        $name = $this->options['prefix'] . $name;
        //对数组/对象数据进行缓存处理，保证数据完整性
        $value = (is_object($value) || is_array($value)) ? json_encode($value) : $value;
        if (is_int($expire) && $expire) {
            $result = $this->handler->setex($name, $expire, $value);
        } else {
            $result = $this->handler->set($name, $value);
        }
        if ($result && $this->options['length'] > 0) {
            // 记录缓存队列
            $this->queue($name);
        }
        return $result;
    }

    /**
     * 删除缓存
     * @access public
     * @param string $name 缓存变量名
     * @return boolean
     */
    public function rm($name)
    {
        $this->handler = $this->isMaster(true);
        return $this->handler->delete($this->options['prefix'] . $name);
    }

    /**
     * 清除缓存
     * @access public
     * @return boolean
     */
    public function clear()
    {
        $this->handler = $this->isMaster(true);
        return $this->handler->flushDB();
    }

    /**
     * Set the string value in argument as value of the key, with a time to live.
     *
     * @param   string $key
     * @param   int $ttl in milliseconds
     * @param   string $value
     * @return  bool    TRUE if the command is successful.
     * @link    http://redis.io/commands/setex
     * @throws Exception
     * $redis->psetex('key', 100, 'value'); // sets key → value, with 0.1 sec TTL.
     */
    public function psetex($key, $ttl, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Scan a set for members.
     * 不知道怎么用 有空看文档 不常用
     * @throws Exception
     * @see scan()
     * @param   string $key
     * @param   int $iterator
     * @param   string $pattern
     * @param   int $count
     * @return  array|bool
     */
    public function sScan($key, $iterator, $pattern = '', $count = 0)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Scan the keyspace for keys.
     * 不知道怎么用 有空看文档 不常用
     * @param  int $iterator Iterator, initialized to NULL.
     * @param  string $pattern Pattern to match.
     * @param  int $count Count of keys per iteration (only a suggestion to Redis).
     * @return array|bool       This function will return an array of keys or FALSE if there are no more keys.
     * @link   http://redis.io/commands/scan
     * @example
     * <pre>
     * $iterator = null;
     * while($keys = $redis->scan($iterator)) {
     *     foreach($keys as $key) {
     *         echo $key . PHP_EOL;
     *     }
     * }
     * </pre>
     * @throws Exception
     */
    public function scan(&$iterator, $pattern = null, $count = 0)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_NO_KEY);
    }

    /**
     * Scan a sorted set for members, with optional pattern and count.
     * 不知道怎么用 有空看文档 不常用
     * @see scan()
     * @param   string $key
     * @param   int $iterator
     * @param   string $pattern
     * @param   int $count
     * @return  array|bool
     */
    public function zScan($key, $iterator, $pattern = '', $count = 0)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Scan a HASH value for members, with an optional pattern and count.
     * 不知道怎么用 有空看文档 不常用
     * @see scan()
     * @param   string $key
     * @param   int $iterator
     * @param   string $pattern
     * @param   int $count
     * @return  array
     */
    public function hScan($key, $iterator, $pattern = '', $count = 0)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Issue the CLIENT command with various arguments.
     *  不知道怎么用 有空看文档 不常用
     * @param   string $command list | getname | setname | kill
     * @param   string $arg
     * @return  mixed
     * @link    http://redis.io/commands/client-list
     * @link    http://redis.io/commands/client-getname
     * @link    http://redis.io/commands/client-setname
     * @link    http://redis.io/commands/client-kill
     * <pre>
     * $redis->client('list');
     * $redis->client('getname');
     * $redis->client('setname', 'somename');
     * $redis->client('kill', <ip:port>);
     * </pre>
     *
     *
     * CLIENT LIST will return an array of arrays with client information.
     * CLIENT GETNAME will return the client name or false if none has been set
     * CLIENT SETNAME will return true if it can be set and false if not
     * CLIENT KILL will return true if the client can be killed, and false if not
     */
    public function client($command, $arg = '')
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Access the Redis slow log.
     * 不知道怎么用 有空看文档 不常用
     * @param   string $command get | len | reset
     * @return  mixed
     * @link    http://redis.io/commands/slowlog
     * <pre>
     * // Get ten slowlog entries
     * $redis->slowlog('get', 10);
     *
     * // Get the default number of slowlog entries
     * $redis->slowlog('get');
     *
     * // Reset our slowlog
     * $redis->slowlog('reset');
     *
     * // Retrieve slowlog length
     * $redis->slowlog('len');
     * </pre>
     */
    public function slowlog($command)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Set the string value in argument as value of the key, with a time to live.
     *
     * @param   string $key
     * @param   int $ttl
     * @param   string $value
     * @return  bool    TRUE if the command is successful.
     * @link    http://redis.io/commands/setex
     * @example $redis->setex('key', 3600, 'value'); // sets key → value, with 1h TTL.
     */
    public function setex($key, $ttl, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Set the string value in argument as value of the key if the key doesn't already exist in the database.
     *
     * @param   string $key
     * @param   string $value
     * @return  bool    TRUE in case of success, FALSE in case of failure.
     * @link    http://redis.io/commands/setnx
     * @example
     * <pre>
     * $redis->setnx('key', 'value');   // return TRUE
     * $redis->setnx('key', 'value');   // return FALSE
     * </pre>
     */
    public function setnx($key, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Remove specified keys.
     *
     * @param   int|array $key1 An array of keys, or an undefined number of parameters, each a key: key1 key2 key3 ... keyN
     * @param   string $key2 ...
     * @param   string $key3 ...
     * @return int Number of keys deleted.
     * @link    http://redis.io/commands/del
     * @example
     * <pre>
     * $redis->set('key1', 'val1');
     * $redis->set('key2', 'val2');
     * $redis->set('key3', 'val3');
     * $redis->set('key4', 'val4');
     * $redis->delete('key1', 'key2');          // return 2
     * $redis->delete(array('key3', 'key4'));   // return 2
     * </pre>
     */
    public function del($key1, $key2 = null, $key3 = null)
    {
        $oldArgs = func_get_args();
        $args = [];
        if (is_array($oldArgs[0])) {
            foreach ($oldArgs[0] as $key => $val) {
                $args[] = $val;
            }
        } else {
            $args = $oldArgs;
        }
        return $this->_call(__FUNCTION__, $args, true, self::KEY_EVERY);
    }

    /**
     * @see del()
     * @param $key1
     * @param null $key2
     * @param null $key3
     * @return int Number of keys deleted.
     */
    public function delete($key1, $key2 = null, $key3 = null)
    {
        $oldArgs = func_get_args();
        $args = [];
        if (is_array($oldArgs[0])) {
            foreach ($oldArgs[0] as $val) {
                $args[] = $val;
            }
        }
        return $this->_call(__FUNCTION__, $args, true, self::KEY_EVERY);
    }

    /**
     * Enter and exit transactional mode.
     *
     * @param int self::MULTI|Redis::PIPELINE
     * Defaults to Redis::MULTI.
     * A Redis::MULTI block of commands runs as a single transaction;
     * a Redis::PIPELINE block is simply transmitted faster to the server, but without any guarantee of atomicity.
     * discard cancels a transaction.
     * @return \Redis returns the Redis instance and enters multi-mode.
     * Once in multi-mode, all subsequent method calls return the same object until exec() is called.
     * @link    http://redis.io/commands/multi
     * @example
     * <pre>
     * $ret = $redis->multi()
     *      ->set('key1', 'val1')
     *      ->get('key1')
     *      ->set('key2', 'val2')
     *      ->get('key2')
     *      ->exec();
     *
     * //$ret == array (
     * //    0 => TRUE,
     * //    1 => 'val1',
     * //    2 => TRUE,
     * //    3 => 'val2');
     * </pre>
     */
    public function multi($mode = self::MULTI)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * @see multi()
     * @link    http://redis.io/commands/exec
     */
    public function exec()
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * @see multi()
     * @link    http://redis.io/commands/discard
     */
    public function discard()
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Watches a key for modifications by another client. If the key is modified between WATCH and EXEC,
     * the MULTI/EXEC transaction will fail (return FALSE). unwatch cancels all the watching of all keys by this client.
     * @param string | array $key : a list of keys
     * @return mixed void
     * @link    http://redis.io/commands/watch
     * @example
     * <pre>
     * $redis->watch('x');
     * // long code here during the execution of which other clients could well modify `x`
     * $ret = $redis->multi()
     *          ->incr('x')
     *          ->exec();
     * // $ret = FALSE if x has been modified between the call to WATCH and the call to EXEC.
     * </pre>
     */
    public function watch($key)
    {
        $args = func_get_args();
        if (is_array($args[0])) {
            foreach ($args[0] as $key => $value) {
                $args[0][$key] = $this->options['prefix'] . $value;
            }
        } else {
            $args[0] = $this->options['prefix'] . $args[0];
        }
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * @see watch()
     * @link    http://redis.io/commands/unwatch
     */
    public function unwatch()
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Verify if the specified key exists.
     *
     * @param   string $key
     * @return  bool  If the key exists, return TRUE, otherwise return FALSE.
     * @link    http://redis.io/commands/exists
     * @example
     * <pre>
     * $redis->set('key', 'value');
     * $redis->exists('key');               //  TRUE
     * $redis->exists('NonExistingKey');    // FALSE
     * </pre>
     */
    public function exists($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Increment the number stored at key by one.
     *
     * @param   string $key
     * @return  int    the new value
     * @link    http://redis.io/commands/incr
     * @example
     * <pre>
     * $redis->incr('key1'); // key1 didn't exists, set to 0 before the increment and now has the value 1
     * $redis->incr('key1'); // 2
     * $redis->incr('key1'); // 3
     * $redis->incr('key1'); // 4
     * </pre>
     */
    public function incr($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Increment the float value of a key by the given amount
     *
     * @param   string $key
     * @param   float $increment
     * @return  float
     * @link    http://redis.io/commands/incrbyfloat
     * @example
     * <pre>
     * $redis = new Redis();
     * $redis->connect('127.0.0.1');
     * $redis->set('x', 3);
     * var_dump( $redis->incrByFloat('x', 1.5) );   // float(4.5)
     *
     * // ! SIC
     * var_dump( $redis->get('x') );                // string(3) "4.5"
     * </pre>
     */
    public function incrByFloat($key, $increment)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Increment the number stored at key by one. If the second argument is filled, it will be used as the integer
     * value of the increment.
     *
     * @param   string $key key
     * @param   int $value value that will be added to key (only for incrBy)
     * @return  int         the new value
     * @link    http://redis.io/commands/incrby
     * @example
     * <pre>
     * $redis->incr('key1');        // key1 didn't exists, set to 0 before the increment and now has the value 1
     * $redis->incr('key1');        // 2
     * $redis->incr('key1');        // 3
     * $redis->incr('key1');        // 4
     * $redis->incrBy('key1', 10);  // 14
     * </pre>
     */
    public function incrBy($key, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Decrement the number stored at key by one.
     *
     * @param   string $key
     * @return  int    the new value
     * @link    http://redis.io/commands/decr
     * @example
     * <pre>
     * $redis->decr('key1'); // key1 didn't exists, set to 0 before the increment and now has the value -1
     * $redis->decr('key1'); // -2
     * $redis->decr('key1'); // -3
     * </pre>
     */
    public function decr($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Decrement the number stored at key by one. If the second argument is filled, it will be used as the integer
     * value of the decrement.
     *
     * @param   string $key
     * @param   int $value that will be substracted to key (only for decrBy)
     * @return  int       the new value
     * @link    http://redis.io/commands/decrby
     * @example
     * <pre>
     * $redis->decr('key1');        // key1 didn't exists, set to 0 before the increment and now has the value -1
     * $redis->decr('key1');        // -2
     * $redis->decr('key1');        // -3
     * $redis->decrBy('key1', 10);  // -13
     * </pre>
     */
    public function decrBy($key, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Get the values of all the specified keys. If one or more keys dont exist, the array will contain FALSE at the
     * position of the key.
     *
     * @param   array $keys Array containing the list of the keys
     * @return  array Array containing the values related to keys in argument
     * @example
     * <pre>
     * $redis->set('key1', 'value1');
     * $redis->set('key2', 'value2');
     * $redis->set('key3', 'value3');
     * $redis->getMultiple(array('key1', 'key2', 'key3')); // array('value1', 'value2', 'value3');
     * $redis->getMultiple(array('key0', 'key1', 'key5')); // array(`FALSE`, 'value2', `FALSE`);
     * </pre>
     */
    public function getMultiple(array $keys)
    {
        $args = func_get_args();
        foreach ($args[0] as $key => $value) {
            $args[0][$key] = $this->options['prefix'] . $value;
        }
        return $this->_call(__FUNCTION__, $args, false, self::KEY_NO_KEY);
    }

    /**
     * Adds the string values to the head (left) of the list. Creates the list if the key didn't exist.
     * If the key exists and is not a list, FALSE is returned.
     *
     * @param   string $key
     * @param   string $value1 String, value to push in key
     * @param   string $value2 Optional
     * @param   string $valueN Optional
     * @return  int    The new length of the list in case of success, FALSE in case of Failure.
     * @link    http://redis.io/commands/lpush
     * @example
     * <pre>
     * $redis->lPush('l', 'v1', 'v2', 'v3', 'v4')   // int(4)
     * var_dump( $redis->lRange('l', 0, -1) );
     * //// Output:
     * // array(4) {
     * //   [0]=> string(2) "v4"
     * //   [1]=> string(2) "v3"
     * //   [2]=> string(2) "v2"
     * //   [3]=> string(2) "v1"
     * // }
     * </pre>
     */
    public function lPush($key, $value1, $value2 = null, $valueN = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Adds the string values to the tail (right) of the list. Creates the list if the key didn't exist.
     * If the key exists and is not a list, FALSE is returned.
     *
     * @param   string $key
     * @param   string $value1 String, value to push in key
     * @param   string $value2 Optional
     * @param   string $valueN Optional
     * @return  int     The new length of the list in case of success, FALSE in case of Failure.
     * @link    http://redis.io/commands/rpush
     * @example
     * <pre>
     * $redis->rPush('l', 'v1', 'v2', 'v3', 'v4');    // int(4)
     * var_dump( $redis->lRange('l', 0, -1) );
     * //// Output:
     * // array(4) {
     * //   [0]=> string(2) "v1"
     * //   [1]=> string(2) "v2"
     * //   [2]=> string(2) "v3"
     * //   [3]=> string(2) "v4"
     * // }
     * </pre>
     */
    public function rPush($key, $value1, $value2 = null, $valueN = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Adds the string value to the head (left) of the list if the list exists.
     *
     * @param   string $key
     * @param   string $value String, value to push in key
     * @return  int     The new length of the list in case of success, FALSE in case of Failure.
     * @link    http://redis.io/commands/lpushx
     * @example
     * <pre>
     * $redis->delete('key1');
     * $redis->lPushx('key1', 'A');     // returns 0
     * $redis->lPush('key1', 'A');      // returns 1
     * $redis->lPushx('key1', 'B');     // returns 2
     * $redis->lPushx('key1', 'C');     // returns 3
     * // key1 now points to the following list: [ 'A', 'B', 'C' ]
     * </pre>
     */
    public function lPushx($key, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Adds the string value to the tail (right) of the list if the ist exists. FALSE in case of Failure.
     *
     * @param   string $key
     * @param   string $value String, value to push in key
     * @return  int     The new length of the list in case of success, FALSE in case of Failure.
     * @link    http://redis.io/commands/rpushx
     * @example
     * <pre>
     * $redis->delete('key1');
     * $redis->rPushx('key1', 'A'); // returns 0
     * $redis->rPush('key1', 'A'); // returns 1
     * $redis->rPushx('key1', 'B'); // returns 2
     * $redis->rPushx('key1', 'C'); // returns 3
     * // key1 now points to the following list: [ 'A', 'B', 'C' ]
     * </pre>
     */
    public function rPushx($key, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Returns and removes the first element of the list.
     *
     * @param   string $key
     * @return  string if command executed successfully BOOL FALSE in case of failure (empty list)
     * @link    http://redis.io/commands/lpop
     * @example
     * <pre>
     * $redis->rPush('key1', 'A');
     * $redis->rPush('key1', 'B');
     * $redis->rPush('key1', 'C');  // key1 => [ 'A', 'B', 'C' ]
     * $redis->lPop('key1');        // key1 => [ 'B', 'C' ]
     * </pre>
     */
    public function lPop($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Returns and removes the last element of the list.
     *
     * @param   string $key
     * @return  string if command executed successfully BOOL FALSE in case of failure (empty list)
     * @link    http://redis.io/commands/rpop
     * @example
     * <pre>
     * $redis->rPush('key1', 'A');
     * $redis->rPush('key1', 'B');
     * $redis->rPush('key1', 'C');  // key1 => [ 'A', 'B', 'C' ]
     * $redis->rPop('key1');        // key1 => [ 'A', 'B' ]
     * </pre>
     */
    public function rPop($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Is a blocking lPop primitive. If at least one of the lists contains at least one element,
     * the element will be popped from the head of the list and returned to the caller.
     * Il all the list identified by the keys passed in arguments are empty, blPop will block
     * during the specified timeout until an element is pushed to one of those lists. This element will be popped.
     *
     * @param array $keys Array containing the keys of the lists
     * Or STRING Key1 STRING Key2 STRING Key3 ... STRING Keyn
     * @param int $timeout Timeout
     *
     * @return  array array('listName', 'element')
     * @link    http://redis.io/commands/blpop
     * @example
     * <pre>
     * // Non blocking feature
     * $redis->lPush('key1', 'A');
     * $redis->delete('key2');
     *
     * $redis->blPop('key1', 'key2', 10); // array('key1', 'A')
     * // OR
     * $redis->blPop(array('key1', 'key2'), 10); // array('key1', 'A')
     *
     * $redis->brPop('key1', 'key2', 10); // array('key1', 'A')
     * // OR
     * $redis->brPop(array('key1', 'key2'), 10); // array('key1', 'A')
     *
     * // Blocking feature
     *
     * // process 1
     * $redis->delete('key1');
     * $redis->blPop('key1', 10);
     * // blocking for 10 seconds
     *
     * // process 2
     * $redis->lPush('key1', 'A');
     *
     * // process 1
     * // array('key1', 'A') is returned
     * </pre>
     */
    public function blPop(array $keys, $timeout)
    {
        $args = func_get_args();
        foreach ($args[0] as $key => $value) {
            $args[0][$key] = $this->options['prefix'] . $value;
        }
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Is a blocking rPop primitive. If at least one of the lists contains at least one element,
     * the element will be popped from the head of the list and returned to the caller.
     * Il all the list identified by the keys passed in arguments are empty, brPop will
     * block during the specified timeout until an element is pushed to one of those lists. T
     * his element will be popped.
     *
     * @param array $keys Array containing the keys of the lists
     * Or STRING Key1 STRING Key2 STRING Key3 ... STRING Keyn
     * @param int $timeout Timeout
     * @return  array array('listName', 'element')
     * @link    http://redis.io/commands/brpop
     * @example
     * <pre>
     * // Non blocking feature
     * $redis->lPush('key1', 'A');
     * $redis->delete('key2');
     *
     * $redis->blPop('key1', 'key2', 10); // array('key1', 'A')
     * // OR
     * $redis->blPop(array('key1', 'key2'), 10); // array('key1', 'A')
     *
     * $redis->brPop('key1', 'key2', 10); // array('key1', 'A')
     * // OR
     * $redis->brPop(array('key1', 'key2'), 10); // array('key1', 'A')
     *
     * // Blocking feature
     *
     * // process 1
     * $redis->delete('key1');
     * $redis->blPop('key1', 10);
     * // blocking for 10 seconds
     *
     * // process 2
     * $redis->lPush('key1', 'A');
     *
     * // process 1
     * // array('key1', 'A') is returned
     * </pre>
     */
    public function brPop(array $keys, $timeout)
    {
        $args = func_get_args();
        foreach ($args[0] as $key => $value) {
            $args[0][$key] = $this->options['prefix'] . $value;
        }
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Returns the size of a list identified by Key. If the list didn't exist or is empty,
     * the command returns 0. If the data type identified by Key is not a list, the command return FALSE.
     *
     * @param   string $key
     * @return  int     The size of the list identified by Key exists.
     * bool FALSE if the data type identified by Key is not list
     * @link    http://redis.io/commands/llen
     * @example
     * <pre>
     * $redis->rPush('key1', 'A');
     * $redis->rPush('key1', 'B');
     * $redis->rPush('key1', 'C');  // key1 => [ 'A', 'B', 'C' ]
     * $redis->lLen('key1');       // 3
     * $redis->rPop('key1');
     * $redis->lLen('key1');       // 2
     * </pre>
     */
    public function lLen($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * @see     lLen()
     * @param   string $key
     * @link    http://redis.io/commands/llen
     * @return int
     */
    public function lSize($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Return the specified element of the list stored at the specified key.
     * 0 the first element, 1 the second ... -1 the last element, -2 the penultimate ...
     * Return FALSE in case of a bad index or a key that doesn't point to a list.
     * @param string $key
     * @param int $index
     * @return String the element at this index
     * Bool FALSE if the key identifies a non-string data type, or no value corresponds to this index in the list Key.
     * @link    http://redis.io/commands/lindex
     * @example
     * <pre>
     * $redis->rPush('key1', 'A');
     * $redis->rPush('key1', 'B');
     * $redis->rPush('key1', 'C');  // key1 => [ 'A', 'B', 'C' ]
     * $redis->lGet('key1', 0);     // 'A'
     * $redis->lGet('key1', -1);    // 'C'
     * $redis->lGet('key1', 10);    // `FALSE`
     * </pre>
     */
    public function lIndex($key, $index)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * @see lIndex()
     * @param   string $key
     * @param   int $index
     * @link    http://redis.io/commands/lindex
     * @return string
     */
    public function lGet($key, $index)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Set the list at index with the new value.
     *
     * @param string $key
     * @param int $index
     * @param string $value
     * @return BOOL TRUE if the new value is setted. FALSE if the index is out of range, or data type identified by key
     * is not a list.
     * @link    http://redis.io/commands/lset
     * @example
     * <pre>
     * $redis->rPush('key1', 'A');
     * $redis->rPush('key1', 'B');
     * $redis->rPush('key1', 'C');  // key1 => [ 'A', 'B', 'C' ]
     * $redis->lGet('key1', 0);     // 'A'
     * $redis->lSet('key1', 0, 'X');
     * $redis->lGet('key1', 0);     // 'X'
     * </pre>
     */
    public function lSet($key, $index, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Returns the specified elements of the list stored at the specified key in
     * the range [start, end]. start and stop are interpretated as indices: 0 the first element,
     * 1 the second ... -1 the last element, -2 the penultimate ...
     * @param   string $key
     * @param   int $start
     * @param   int $end
     * @return  array containing the values in specified range.
     * @link    http://redis.io/commands/lrange
     * @example
     * <pre>
     * $redis->rPush('key1', 'A');
     * $redis->rPush('key1', 'B');
     * $redis->rPush('key1', 'C');
     * $redis->lRange('key1', 0, -1); // array('A', 'B', 'C')
     * </pre>
     */
    public function lRange($key, $start, $end)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * @see lRange()
     * @link http://redis.io/commands/lrange
     * @param string $key
     * @param int $start
     * @param int $end
     * @return array
     */
    public function lGetRange($key, $start, $end)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Trims an existing list so that it will contain only a specified range of elements.
     *
     * @param string $key
     * @param int $start
     * @param int $stop
     * @return array    Bool return FALSE if the key identify a non-list value.
     * @link        http://redis.io/commands/ltrim
     * @example
     * <pre>
     * $redis->rPush('key1', 'A');
     * $redis->rPush('key1', 'B');
     * $redis->rPush('key1', 'C');
     * $redis->lRange('key1', 0, -1); // array('A', 'B', 'C')
     * $redis->lTrim('key1', 0, 1);
     * $redis->lRange('key1', 0, -1); // array('A', 'B')
     * </pre>
     */
    public function lTrim($key, $start, $stop)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * @see lTrim()
     * @link  http://redis.io/commands/ltrim
     * @param string $key
     * @param int $start
     * @param int $stop
     * @return array
     */
    public function listTrim($key, $start, $stop)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Removes the first count occurences of the value element from the list.
     * If count is zero, all the matching elements are removed. If count is negative,
     * elements are removed from tail to head.
     *
     * @param   string $key
     * @param   string $value
     * @param   int $count
     * @return  int     the number of elements to remove
     * bool FALSE if the value identified by key is not a list.
     * @link    http://redis.io/commands/lrem
     * @example
     * <pre>
     * $redis->lPush('key1', 'A');
     * $redis->lPush('key1', 'B');
     * $redis->lPush('key1', 'C');
     * $redis->lPush('key1', 'A');
     * $redis->lPush('key1', 'A');
     *
     * $redis->lRange('key1', 0, -1);   // array('A', 'A', 'C', 'B', 'A')
     * $redis->lRem('key1', 'A', 2);    // 2
     * $redis->lRange('key1', 0, -1);   // array('C', 'B', 'A')
     * </pre>
     */
    public function lRem($key, $value, $count)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * @see lRem
     * @link    http://redis.io/commands/lremove
     * @param string $key
     * @param string $value
     * @param int $count
     * @return int
     */
    public function lRemove($key, $value, $count)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Insert value in the list before or after the pivot value. the parameter options
     * specify the position of the insert (before or after). If the list didn't exists,
     * or the pivot didn't exists, the value is not inserted.
     *
     * @param   string $key
     * @param   int $position Redis::BEFORE | Redis::AFTER
     * @param   string $pivot
     * @param   string $value
     * @return  int     The number of the elements in the list, -1 if the pivot didn't exists.
     * @link    http://redis.io/commands/linsert
     * @example
     * <pre>
     * $redis->delete('key1');
     * $redis->lInsert('key1', Redis::AFTER, 'A', 'X');     // 0
     *
     * $redis->lPush('key1', 'A');
     * $redis->lPush('key1', 'B');
     * $redis->lPush('key1', 'C');
     *
     * $redis->lInsert('key1', Redis::BEFORE, 'C', 'X');    // 4
     * $redis->lRange('key1', 0, -1);                       // array('A', 'B', 'X', 'C')
     *
     * $redis->lInsert('key1', Redis::AFTER, 'C', 'Y');     // 5
     * $redis->lRange('key1', 0, -1);                       // array('A', 'B', 'X', 'C', 'Y')
     *
     * $redis->lInsert('key1', Redis::AFTER, 'W', 'value'); // -1
     * </pre>
     */
    public function lInsert($key, $position, $pivot, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Adds a values to the set value stored at key.
     * If this value is already in the set, FALSE is returned.
     *
     * @param   string $key Required key
     * @param   string $value1 Required value
     * @param   string $value2 Optional value
     * @param   string $valueN Optional value
     * @return  int     The number of elements added to the set
     * @link    http://redis.io/commands/sadd
     * @example
     * <pre>
     * $redis->sAdd('k', 'v1');                // int(1)
     * $redis->sAdd('k', 'v1', 'v2', 'v3');    // int(2)
     * </pre>
     */
    public function sAdd($key, $value1, $value2 = null, $valueN = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Adds a values to the set value stored at key.
     *
     * @param   string $key Required key
     * @param   array $values Required values
     * @return  boolean The number of elements added to the set
     * @link    http://redis.io/commands/sadd
     * @link    https://github.com/phpredis/phpredis/commit/3491b188e0022f75b938738f7542603c7aae9077
     * @since   phpredis 2.2.8
     * @example
     * <pre>
     * $redis->sAddArray('k', array('v1'));                // boolean
     * $redis->sAddArray('k', array('v1', 'v2', 'v3'));    // boolean
     * </pre>
     */
    public function sAddArray($key, array $values)
    {
        array_unshift($values, $key);
        return $this->_call('sAdd', $values, true, self::KEY_FIRST);
    }

    /**
     * Removes the specified members from the set value stored at key.
     *
     * @param   string $key
     * @param   string $member1
     * @param   string $member2
     * @param   string $memberN
     * @return  int     The number of elements removed from the set.
     * @link    http://redis.io/commands/srem
     * @example
     * <pre>
     * var_dump( $redis->sAdd('k', 'v1', 'v2', 'v3') );    // int(3)
     * var_dump( $redis->sRem('k', 'v2', 'v3') );          // int(2)
     * var_dump( $redis->sMembers('k') );
     * //// Output:
     * // array(1) {
     * //   [0]=> string(2) "v1"
     * // }
     * </pre>
     */
    public function sRem($key, $member1, $member2 = null, $memberN = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * @see sRem()
     * @link    http://redis.io/commands/srem
     * @param   string $key
     * @param   string $member1
     * @param   string $member2
     * @param   string $memberN
     * @return int
     */
    public function sRemove($key, $member1, $member2 = null, $memberN = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Moves the specified member from the set at srcKey to the set at dstKey.
     *
     * @param   string $srcKey
     * @param   string $dstKey
     * @param   string $member
     * @return  bool    If the operation is successful, return TRUE.
     * If the srcKey and/or dstKey didn't exist, and/or the member didn't exist in srcKey, FALSE is returned.
     * @link    http://redis.io/commands/smove
     * @example
     * <pre>
     * $redis->sAdd('key1' , 'set11');
     * $redis->sAdd('key1' , 'set12');
     * $redis->sAdd('key1' , 'set13');          // 'key1' => {'set11', 'set12', 'set13'}
     * $redis->sAdd('key2' , 'set21');
     * $redis->sAdd('key2' , 'set22');          // 'key2' => {'set21', 'set22'}
     * $redis->sMove('key1', 'key2', 'set13');  // 'key1' =>  {'set11', 'set12'}
     *                                          // 'key2' =>  {'set21', 'set22', 'set13'}
     * </pre>
     */
    public function sMove($srcKey, $dstKey, $member)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST_AND_SECOND);
    }

    /**
     * Checks if value is a member of the set stored at the key key.
     *
     * @param   string $key
     * @param   string $value
     * @return  bool    TRUE if value is a member of the set at key key, FALSE otherwise.
     * @link    http://redis.io/commands/sismember
     * @example
     * <pre>
     * $redis->sAdd('key1' , 'set1');
     * $redis->sAdd('key1' , 'set2');
     * $redis->sAdd('key1' , 'set3'); // 'key1' => {'set1', 'set2', 'set3'}
     *
     * $redis->sIsMember('key1', 'set1'); // TRUE
     * $redis->sIsMember('key1', 'setX'); // FALSE
     * </pre>
     */
    public function sIsMember($key, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * @see sIsMember()
     * @link    http://redis.io/commands/sismember
     * @param   string $key
     * @param   string $value
     * @return bool
     */
    public function sContains($key, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Returns the cardinality of the set identified by key.
     *
     * @param   string $key
     * @return  int     the cardinality of the set identified by key, 0 if the set doesn't exist.
     * @link    http://redis.io/commands/scard
     * @example
     * <pre>
     * $redis->sAdd('key1' , 'set1');
     * $redis->sAdd('key1' , 'set2');
     * $redis->sAdd('key1' , 'set3');   // 'key1' => {'set1', 'set2', 'set3'}
     * $redis->sCard('key1');           // 3
     * $redis->sCard('keyX');           // 0
     * </pre>
     */
    public function sCard($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Removes and returns a random element from the set value at Key.
     *
     * @param   string $key
     * @return  string  "popped" value
     * bool FALSE if set identified by key is empty or doesn't exist.
     * @link    http://redis.io/commands/spop
     * @example
     * <pre>
     * $redis->sAdd('key1' , 'set1');
     * $redis->sAdd('key1' , 'set2');
     * $redis->sAdd('key1' , 'set3');   // 'key1' => {'set3', 'set1', 'set2'}
     * $redis->sPop('key1');            // 'set1', 'key1' => {'set3', 'set2'}
     * $redis->sPop('key1');            // 'set3', 'key1' => {'set2'}
     * </pre>
     */
    public function sPop($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Returns a random element(s) from the set value at Key, without removing it.
     *
     * @param   string $key
     * @param   int $count [optional]
     * @return  string|array  value(s) from the set
     * bool FALSE if set identified by key is empty or doesn't exist and count argument isn't passed.
     * @link    http://redis.io/commands/srandmember
     * @example
     * <pre>
     * $redis->sAdd('key1' , 'one');
     * $redis->sAdd('key1' , 'two');
     * $redis->sAdd('key1' , 'three');              // 'key1' => {'one', 'two', 'three'}
     *
     * var_dump( $redis->sRandMember('key1') );     // 'key1' => {'one', 'two', 'three'}
     *
     * // string(5) "three"
     *
     * var_dump( $redis->sRandMember('key1', 2) );  // 'key1' => {'one', 'two', 'three'}
     *
     * // array(2) {
     * //   [0]=> string(2) "one"
     * //   [1]=> string(2) "three"
     * // }
     * </pre>
     */
    public function sRandMember($key, $count = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Returns the members of a set resulting from the intersection of all the sets
     * held at the specified keys. If just a single key is specified, then this command
     * produces the members of this set. If one of the keys is missing, FALSE is returned.
     *
     * @param   string $key1 keys identifying the different sets on which we will apply the intersection.
     * @param   string $key2 ...
     * @param   string $keyN ...
     * @return  array  contain the result of the intersection between those keys.
     * If the intersection between the different sets is empty, the return value will be empty array.
     * @link    http://redis.io/commands/sinterstore
     * @example
     * <pre>
     * $redis->sAdd('key1', 'val1');
     * $redis->sAdd('key1', 'val2');
     * $redis->sAdd('key1', 'val3');
     * $redis->sAdd('key1', 'val4');
     *
     * $redis->sAdd('key2', 'val3');
     * $redis->sAdd('key2', 'val4');
     *
     * $redis->sAdd('key3', 'val3');
     * $redis->sAdd('key3', 'val4');
     *
     * var_dump($redis->sInter('key1', 'key2', 'key3'));
     *
     * //array(2) {
     * //  [0]=>
     * //  string(4) "val4"
     * //  [1]=>
     * //  string(4) "val3"
     * //}
     * </pre>
     */
    public function sInter($key1, $key2, $keyN = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_EVERY);
    }

    /**
     * Performs a sInter command and stores the result in a new set.
     *
     * @param   string $dstKey the key to store the diff into.
     * @param   string $key1 are intersected as in sInter.
     * @param   string $key2 ...
     * @param   string $keyN ...
     * @return  int     The cardinality of the resulting set, or FALSE in case of a missing key.
     * @link    http://redis.io/commands/sinterstore
     * @example
     * <pre>
     * $redis->sAdd('key1', 'val1');
     * $redis->sAdd('key1', 'val2');
     * $redis->sAdd('key1', 'val3');
     * $redis->sAdd('key1', 'val4');
     *
     * $redis->sAdd('key2', 'val3');
     * $redis->sAdd('key2', 'val4');
     *
     * $redis->sAdd('key3', 'val3');
     * $redis->sAdd('key3', 'val4');
     *
     * var_dump($redis->sInterStore('output', 'key1', 'key2', 'key3'));
     * var_dump($redis->sMembers('output'));
     *
     * //int(2)
     * //
     * //array(2) {
     * //  [0]=>
     * //  string(4) "val4"
     * //  [1]=>
     * //  string(4) "val3"
     * //}
     * </pre>
     */
    public function sInterStore($dstKey, $key1, $key2, $keyN = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_EVERY);
    }

    /**
     * Performs the union between N sets and returns it.
     *
     * @param   string $key1 Any number of keys corresponding to sets in redis.
     * @param   string $key2 ...
     * @param   string $keyN ...
     * @return  array   of strings: The union of all these sets.
     * @link    http://redis.io/commands/sunionstore
     * @example
     * <pre>
     * $redis->delete('s0', 's1', 's2');
     *
     * $redis->sAdd('s0', '1');
     * $redis->sAdd('s0', '2');
     * $redis->sAdd('s1', '3');
     * $redis->sAdd('s1', '1');
     * $redis->sAdd('s2', '3');
     * $redis->sAdd('s2', '4');
     *
     * var_dump($redis->sUnion('s0', 's1', 's2'));
     *
     * array(4) {
     * //  [0]=>
     * //  string(1) "3"
     * //  [1]=>
     * //  string(1) "4"
     * //  [2]=>
     * //  string(1) "1"
     * //  [3]=>
     * //  string(1) "2"
     * //}
     * </pre>
     */
    public function sUnion($key1, $key2, $keyN = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_EVERY);
    }

    /**
     * Performs the same action as sUnion, but stores the result in the first key
     *
     * @param   string $dstKey the key to store the diff into.
     * @param   string $key1 Any number of keys corresponding to sets in redis.
     * @param   string $key2 ...
     * @param   string $keyN ...
     * @return  int     Any number of keys corresponding to sets in redis.
     * @link    http://redis.io/commands/sunionstore
     * @example
     * <pre>
     * $redis->delete('s0', 's1', 's2');
     *
     * $redis->sAdd('s0', '1');
     * $redis->sAdd('s0', '2');
     * $redis->sAdd('s1', '3');
     * $redis->sAdd('s1', '1');
     * $redis->sAdd('s2', '3');
     * $redis->sAdd('s2', '4');
     *
     * var_dump($redis->sUnionStore('dst', 's0', 's1', 's2'));
     * var_dump($redis->sMembers('dst'));
     *
     * //int(4)
     * //array(4) {
     * //  [0]=>
     * //  string(1) "3"
     * //  [1]=>
     * //  string(1) "4"
     * //  [2]=>
     * //  string(1) "1"
     * //  [3]=>
     * //  string(1) "2"
     * //}
     * </pre>
     */
    public function sUnionStore($dstKey, $key1, $key2, $keyN = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_EVERY);
    }

    /**
     * Performs the difference between N sets and returns it.
     *
     * @param   string $key1 Any number of keys corresponding to sets in redis.
     * @param   string $key2 ...
     * @param   string $keyN ...
     * @return  array   of strings: The difference of the first set will all the others.
     * @link    http://redis.io/commands/sdiff
     * @example
     * <pre>
     * $redis->delete('s0', 's1', 's2');
     *
     * $redis->sAdd('s0', '1');
     * $redis->sAdd('s0', '2');
     * $redis->sAdd('s0', '3');
     * $redis->sAdd('s0', '4');
     *
     * $redis->sAdd('s1', '1');
     * $redis->sAdd('s2', '3');
     *
     * var_dump($redis->sDiff('s0', 's1', 's2'));
     *
     * //array(2) {
     * //  [0]=>
     * //  string(1) "4"
     * //  [1]=>
     * //  string(1) "2"
     * //}
     * </pre>
     */
    public function sDiff($key1, $key2, $keyN = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_EVERY);
    }

    /**
     * Performs the same action as sDiff, but stores the result in the first key
     *
     * @param   string $dstKey the key to store the diff into.
     * @param   string $key1 Any number of keys corresponding to sets in redis
     * @param   string $key2 ...
     * @param   string $keyN ...
     * @return  int     The cardinality of the resulting set, or FALSE in case of a missing key.
     * @link    http://redis.io/commands/sdiffstore
     * @example
     * <pre>
     * $redis->delete('s0', 's1', 's2');
     *
     * $redis->sAdd('s0', '1');
     * $redis->sAdd('s0', '2');
     * $redis->sAdd('s0', '3');
     * $redis->sAdd('s0', '4');
     *
     * $redis->sAdd('s1', '1');
     * $redis->sAdd('s2', '3');
     *
     * var_dump($redis->sDiffStore('dst', 's0', 's1', 's2'));
     * var_dump($redis->sMembers('dst'));
     *
     * //int(2)
     * //array(2) {
     * //  [0]=>
     * //  string(1) "4"
     * //  [1]=>
     * //  string(1) "2"
     * //}
     * </pre>
     */
    public function sDiffStore($dstKey, $key1, $key2, $keyN = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_EVERY);
    }

    /**
     * Returns the contents of a set.
     *
     * @param   string $key
     * @return  array   An array of elements, the contents of the set.
     * @link    http://redis.io/commands/smembers
     * @example
     * <pre>
     * $redis->delete('s');
     * $redis->sAdd('s', 'a');
     * $redis->sAdd('s', 'b');
     * $redis->sAdd('s', 'a');
     * $redis->sAdd('s', 'c');
     * var_dump($redis->sMembers('s'));
     *
     * //array(3) {
     * //  [0]=>
     * //  string(1) "c"
     * //  [1]=>
     * //  string(1) "a"
     * //  [2]=>
     * //  string(1) "b"
     * //}
     * // The order is random and corresponds to redis' own internal representation of the set structure.
     * </pre>
     */
    public function sMembers($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * @see sMembers()
     * @param   string $key
     * @return array
     * @link    http://redis.io/commands/smembers
     */
    public function sGetMembers($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Sets a value and returns the previous entry at that key.
     *
     * @param   string $key
     * @param   string $value
     * @return  string  A string, the previous value located at this key.
     * @link    http://redis.io/commands/getset
     * @example
     * <pre>
     * $redis->set('x', '42');
     * $exValue = $redis->getSet('x', 'lol');   // return '42', replaces x by 'lol'
     * $newValue = $redis->get('x')'            // return 'lol'
     * </pre>
     */
    public function getSet($key, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Returns a random key.
     *
     * @return string  an existing key in redis.
     * @link    http://redis.io/commands/randomkey
     * @example
     * <pre>
     * $key = $redis->randomKey();
     * $surprise = $redis->get($key);  // who knows what's in there.
     * </pre>
     */
    public function randomKey()
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_NO_KEY);
    }

    /**
     * Moves a key to a different database.
     *
     * @param   string $key
     * @param   int $dbindex
     * @return  bool    TRUE in case of success, FALSE in case of failure.
     * @link    http://redis.io/commands/move
     * @example
     * <pre>
     * $redis->select(0);       // switch to DB 0
     * $redis->set('x', '42');  // write 42 to x
     * $redis->move('x', 1);    // move to DB 1
     * $redis->select(1);       // switch to DB 1
     * $redis->get('x');        // will return 42
     * </pre>
     */
    public function move($key, $dbindex)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Renames a key.
     *
     * @param   string $srcKey
     * @param   string $dstKey
     * @return  bool    TRUE in case of success, FALSE in case of failure.
     * @link    http://redis.io/commands/rename
     * @example
     * <pre>
     * $redis->set('x', '42');
     * $redis->rename('x', 'y');
     * $redis->get('y');   // → 42
     * $redis->get('x');   // → `FALSE`
     * </pre>
     */
    public function rename($srcKey, $dstKey)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_EVERY);
    }

    /**
     * @see rename()
     * @link    http://redis.io/commands/rename
     * @param   string $srcKey
     * @param   string $dstKey
     * @return bool
     */
    public function renameKey($srcKey, $dstKey)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_EVERY);
    }

    /**
     * Renames a key.
     *
     * Same as rename, but will not replace a key if the destination already exists.
     * This is the same behaviour as setNx.
     *
     * @param   string $srcKey
     * @param   string $dstKey
     * @return  bool    TRUE in case of success, FALSE in case of failure.
     * @link    http://redis.io/commands/renamenx
     * @example
     * <pre>
     * $redis->set('x', '42');
     * $redis->rename('x', 'y');
     * $redis->get('y');   // → 42
     * $redis->get('x');   // → `FALSE`
     * </pre>
     */
    public function renameNx($srcKey, $dstKey)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_EVERY);
    }

    /**
     * Sets an expiration date (a timeout) on an item.
     *
     * @param   string $key The key that will disappear.
     * @param   int $ttl The key's remaining Time To Live, in seconds.
     * @return  bool    TRUE in case of success, FALSE in case of failure.
     * @link    http://redis.io/commands/expire
     * @example
     * <pre>
     * $redis->set('x', '42');
     * $redis->setTimeout('x', 3);  // x will disappear in 3 seconds.
     * sleep(5);                    // wait 5 seconds
     * $redis->get('x');            // will return `FALSE`, as 'x' has expired.
     * </pre>
     */
    public function expire($key, $ttl)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Sets an expiration date (a timeout in milliseconds) on an item.
     *
     * @param   string $key The key that will disappear.
     * @param   int $ttl The key's remaining Time To Live, in milliseconds.
     * @return  bool    TRUE in case of success, FALSE in case of failure.
     * @link    http://redis.io/commands/pexpire
     * @example
     * <pre>
     * $redis->set('x', '42');
     * $redis->pExpire('x', 11500); // x will disappear in 11500 milliseconds.
     * $redis->ttl('x');            // 12
     * $redis->pttl('x');           // 11500
     * </pre>
     */
    public function pExpire($key, $ttl)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * @see expire()
     * @param   string $key
     * @param   int $ttl
     * @return bool
     * @link    http://redis.io/commands/expire
     */
    public function setTimeout($key, $ttl)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Sets an expiration date (a timestamp) on an item.
     *
     * @param   string $key The key that will disappear.
     * @param   int $timestamp Unix timestamp. The key's date of death, in seconds from Epoch time.
     * @return  bool    TRUE in case of success, FALSE in case of failure.
     * @link    http://redis.io/commands/expireat
     * @example
     * <pre>
     * $redis->set('x', '42');
     * $now = time(NULL);               // current timestamp
     * $redis->expireAt('x', $now + 3); // x will disappear in 3 seconds.
     * sleep(5);                        // wait 5 seconds
     * $redis->get('x');                // will return `FALSE`, as 'x' has expired.
     * </pre>
     */
    public function expireAt($key, $timestamp)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Sets an expiration date (a timestamp) on an item. Requires a timestamp in milliseconds
     *
     * @param   string $key The key that will disappear.
     * @param   int $timestamp Unix timestamp. The key's date of death, in seconds from Epoch time.
     * @return  bool    TRUE in case of success, FALSE in case of failure.
     * @link    http://redis.io/commands/pexpireat
     * @example
     * <pre>
     * $redis->set('x', '42');
     * $redis->pExpireAt('x', 1555555555005);
     * echo $redis->ttl('x');                       // 218270121
     * echo $redis->pttl('x');                      // 218270120575
     * </pre>
     */
    public function pExpireAt($key, $timestamp)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Returns the keys that match a certain pattern.
     *
     * @param   string $pattern pattern, using '*' as a wildcard.
     * @param   bool $strict ignore prefix
     * @return  array   of STRING: The keys that match a certain pattern.
     * @link    http://redis.io/commands/keys
     * @example
     * <pre>
     * $allKeys = $redis->keys('*');   // all keys will match this.
     * $keyWithUserPrefix = $redis->keys('user*');
     * </pre>
     */
    public function keys($pattern, $strict = false)
    {
        $args = func_get_args();
        $keyPosition = $strict ? self::KEY_NO_KEY : self::KEY_FIRST;
        return $this->_call(__FUNCTION__, $args, false, $keyPosition);
    }

    /**
     * @see keys()
     * @param   string $pattern
     * @param   bool $strict
     * @return array
     * @link    http://redis.io/commands/keys
     */
    public function getKeys($pattern, $strict = false)
    {
        $args = func_get_args();
        $keyPosition = $strict ? self::KEY_NO_KEY : self::KEY_FIRST;
        return $this->_call(__FUNCTION__, $args, false, $keyPosition);
    }

    /**
     * Returns the current database's size.
     *
     * @return int     DB size, in number of keys.
     * @link    http://redis.io/commands/dbsize
     * @example
     * <pre>
     * $count = $redis->dbSize();
     * echo "Redis has $count keys\n";
     * </pre>
     */
    public function dbSize()
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_NO_KEY);
    }

    /**
     * Starts the background rewrite of AOF (Append-Only File)
     *
     * @return  bool    TRUE in case of success, FALSE in case of failure.
     * @link    http://redis.io/commands/bgrewriteaof
     * @example $redis->bgrewriteaof();
     */
    public function bgrewriteaof()
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Changes the slave status
     * Either host and port, or no parameter to stop being a slave.
     *
     * @param   string $host [optional]
     * @param   int $port [optional]
     * @return  bool    TRUE in case of success, FALSE in case of failure.
     * @link    http://redis.io/commands/slaveof
     * @example
     * <pre>
     * $redis->slaveof('10.0.1.7', 6379);
     * // ...
     * $redis->slaveof();
     * </pre>
     */
    public function slaveof($host = '127.0.0.1', $port = 6379)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_NO_KEY);
    }

    /**
     * Describes the object pointed to by a key.
     * The information to retrieve (string) and the key (string).
     * Info can be one of the following:
     * - "encoding"
     * - "refcount"
     * - "idletime"
     *
     * @param   string $string
     * @param   string $key
     * @return  string  for "encoding", int for "refcount" and "idletime", FALSE if the key doesn't exist.
     * @link    http://redis.io/commands/object
     * @example
     * <pre>
     * $redis->object("encoding", "l"); // → ziplist
     * $redis->object("refcount", "l"); // → 1
     * $redis->object("idletime", "l"); // → 400 (in seconds, with a precision of 10 seconds).
     * </pre>
     */
    public function object($string = '', $key = '')
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Performs a synchronous save.
     *
     * @return  bool    TRUE in case of success, FALSE in case of failure.
     * If a save is already running, this command will fail and return FALSE.
     * @link    http://redis.io/commands/save
     * @example $redis->save();
     */
    public function save()
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Performs a background save.
     *
     * @return  bool     TRUE in case of success, FALSE in case of failure.
     * If a save is already running, this command will fail and return FALSE.
     * @link    http://redis.io/commands/bgsave
     * @example $redis->bgSave();
     */
    public function bgsave()
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Returns the timestamp of the last disk save.
     *
     * @return  int     timestamp.
     * @link    http://redis.io/commands/lastsave
     * @example $redis->lastSave();
     */
    public function lastSave()
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Blocks the current client until all the previous write commands are successfully transferred and
     * acknowledged by at least the specified number of slaves.
     * @param   int $numSlaves Number of slaves that need to acknowledge previous write commands.
     * @param   int $timeout Timeout in milliseconds.
     * @return  int The command returns the number of slaves reached by all the writes performed in the
     *              context of the current connection.
     * @link    http://redis.io/commands/wait
     * @example $redis->wait(2, 1000);
     */
    public function wait($numSlaves, $timeout)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Returns the type of data pointed by a given key.
     *
     * @param   string $key
     * @return  string
     *
     * Depending on the type of the data pointed by the key,
     * this method will return the following value:
     * - string: Redis::REDIS_STRING
     * - set:   Redis::REDIS_SET
     * - list:  Redis::REDIS_LIST
     * - zset:  Redis::REDIS_ZSET
     * - hash:  Redis::REDIS_HASH
     * - other: Redis::REDIS_NOT_FOUND
     * @link    http://redis.io/commands/type
     * @example $redis->type('key');
     */
    public function type($key)
    {
        $args = func_get_args();
        $type = $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
        $typeName = ['other', 'string', 'set', 'list', 'zset', 'hash'];
        return $typeName[$type];
    }

    /**
     * Append specified string to the string stored in specified key.
     *
     * @param   string $key
     * @param   string $value
     * @return  int     Size of the value after the append
     * @link    http://redis.io/commands/append
     * @example
     * <pre>
     * $redis->set('key', 'value1');
     * $redis->append('key', 'value2'); // 12
     * $redis->get('key');              // 'value1value2'
     * </pre>
     */
    public function append($key, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Return a substring of a larger string
     *
     * @param   string $key
     * @param   int $start
     * @param   int $end
     * @return  string  the substring
     * @link    http://redis.io/commands/getrange
     * @example
     * <pre>
     * $redis->set('key', 'string value');
     * $redis->getRange('key', 0, 5);   // 'string'
     * $redis->getRange('key', -5, -1); // 'value'
     * </pre>
     */
    public function getRange($key, $start, $end)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Return a substring of a larger string
     *
     * @deprecated
     * @param   string $key
     * @param   int $start
     * @param   int $end
     * @return string
     */
    public function substr($key, $start, $end)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Changes a substring of a larger string.
     *
     * @param   string $key
     * @param   int $offset
     * @param   string $value
     * @return  string  the length of the string after it was modified.
     * @link    http://redis.io/commands/setrange
     * @example
     * <pre>
     * $redis->set('key', 'Hello world');
     * $redis->setRange('key', 6, "redis"); // returns 11
     * $redis->get('key');                  // "Hello redis"
     * </pre>
     */
    public function setRange($key, $offset, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Get the length of a string value.
     *
     * @param   string $key
     * @return  int
     * @link    http://redis.io/commands/strlen
     * @example
     * <pre>
     * $redis->set('key', 'value');
     * $redis->strlen('key'); // 5
     * </pre>
     */
    public function strlen($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Removes all entries from the current database.
     *
     * @return  bool  Always TRUE.
     * @link    http://redis.io/commands/flushdb
     * @example $redis->flushDB();
     */
    public function flushDB()
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Removes all entries from all databases.
     *
     * @return  bool  Always TRUE.
     * @link    http://redis.io/commands/flushall
     * @example $redis->flushAll();
     */
    public function flushAll()
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Sort
     *
     * @param   string $key
     * @param   array $option array(key => value, ...) - optional, with the following keys and values:
     * - 'by' => 'some_pattern_*',
     * - 'limit' => array(0, 1),
     * - 'get' => 'some_other_pattern_*' or an array of patterns,
     * - 'sort' => 'asc' or 'desc',
     * - 'alpha' => TRUE,
     * - 'store' => 'external-key'
     * @return  array
     * An array of values, or a number corresponding to the number of elements stored if that was used.
     * @link    http://redis.io/commands/sort
     * @example
     * <pre>
     * $redis->delete('s');
     * $redis->sadd('s', 5);
     * $redis->sadd('s', 4);
     * $redis->sadd('s', 2);
     * $redis->sadd('s', 1);
     * $redis->sadd('s', 3);
     *
     * var_dump($redis->sort('s')); // 1,2,3,4,5
     * var_dump($redis->sort('s', array('sort' => 'desc'))); // 5,4,3,2,1
     * var_dump($redis->sort('s', array('sort' => 'desc', 'store' => 'out'))); // (int)5
     * </pre>
     */
    public function sort($key, $option = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Returns an associative array of strings and integers
     * @param   string $option Optional. The option to provide redis.
     * SERVER | CLIENTS | MEMORY | PERSISTENCE | STATS | REPLICATION | CPU | CLASTER | KEYSPACE | COMANDSTATS
     *
     * Returns an associative array of strings and integers, with the following keys:
     * - redis_version
     * - redis_git_sha1
     * - redis_git_dirty
     * - arch_bits
     * - multiplexing_api
     * - process_id
     * - uptime_in_seconds
     * - uptime_in_days
     * - lru_clock
     * - used_cpu_sys
     * - used_cpu_user
     * - used_cpu_sys_children
     * - used_cpu_user_children
     * - connected_clients
     * - connected_slaves
     * - client_longest_output_list
     * - client_biggest_input_buf
     * - blocked_clients
     * - used_memory
     * - used_memory_human
     * - used_memory_peak
     * - used_memory_peak_human
     * - mem_fragmentation_ratio
     * - mem_allocator
     * - loading
     * - aof_enabled
     * - changes_since_last_save
     * - bgsave_in_progress
     * - last_save_time
     * - total_connections_received
     * - total_commands_processed
     * - expired_keys
     * - evicted_keys
     * - keyspace_hits
     * - keyspace_misses
     * - hash_max_zipmap_entries
     * - hash_max_zipmap_value
     * - pubsub_channels
     * - pubsub_patterns
     * - latest_fork_usec
     * - vm_enabled
     * - role
     * @link    http://redis.io/commands/info
     * @return string
     * @example
     * <pre>
     * $redis->info();
     *
     * or
     *
     * $redis->info("COMMANDSTATS"); //Information on the commands that have been run (>=2.6 only)
     * $redis->info("CPU"); // just CPU information from Redis INFO
     * </pre>
     */
    public function info($option = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Resets the statistics reported by Redis using the INFO command (`info()` function).
     * These are the counters that are reset:
     *      - Keyspace hits
     *      - Keyspace misses
     *      - Number of commands processed
     *      - Number of connections received
     *      - Number of expired keys
     *
     * @return bool  `TRUE` in case of success, `FALSE` in case of failure.
     * @example $redis->resetStat();
     * @link http://redis.io/commands/config-resetstat
     */
    public function resetStat()
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Returns the time to live left for a given key, in seconds. If the key doesn't exist, FALSE is returned.
     *
     * @param   string $key
     * @return  int     the time left to live in seconds.
     * @link    http://redis.io/commands/ttl
     * @example $redis->ttl('key');
     */
    public function ttl($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Returns a time to live left for a given key, in milliseconds.
     *
     * If the key doesn't exist, FALSE is returned.
     *
     * @param   string $key
     * @return  int     the time left to live in milliseconds.
     * @link    http://redis.io/commands/pttl
     * @example $redis->pttl('key');
     */
    public function pttl($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Remove the expiration timer from a key.
     *
     * @param   string $key
     * @return  bool    TRUE if a timeout was removed, FALSE if the key didn’t exist or didn’t have an expiration timer.
     * @link    http://redis.io/commands/persist
     * @example $redis->persist('key');
     */
    public function persist($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Sets multiple key-value pairs in one atomic command.
     * MSETNX only returns TRUE if all the keys were set (see SETNX).
     *
     * @param   array $array Pairs: array(key => value, ...)
     * @return  bool    TRUE in case of success, FALSE in case of failure.
     * @link    http://redis.io/commands/mset
     * @example
     * <pre>
     * $redis->mset(array('key0' => 'value0', 'key1' => 'value1'));
     * var_dump($redis->get('key0'));
     * var_dump($redis->get('key1'));
     * // Output:
     * // string(6) "value0"
     * // string(6) "value1"
     * </pre>
     */
    public function mset(array $array)
    {
        $oldArgs = func_get_args();
        $args = [];
        $item = [];
        foreach ($oldArgs[0] as $key => $value) {
            $key = $this->options['prefix'] . $key;
            $item[$key] = $value;
        }
        $args[] = $item;
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Returns the values of all specified keys.
     *
     * For every key that does not hold a string value or does not exist,
     * the special value false is returned. Because of this, the operation never fails.
     *
     * @param array $array
     * @return array
     * @link http://redis.io/commands/mget
     * @example
     * <pre>
     * $redis->delete('x', 'y', 'z', 'h');    // remove x y z
     * $redis->mset(array('x' => 'a', 'y' => 'b', 'z' => 'c'));
     * $redis->hset('h', 'field', 'value');
     * var_dump($redis->mget(array('x', 'y', 'z', 'h')));
     * // Output:
     * // array(3) {
     * // [0]=>
     * // string(1) "a"
     * // [1]=>
     * // string(1) "b"
     * // [2]=>
     * // string(1) "c"
     * // [3]=>
     * // bool(false)
     * // }
     * </pre>
     */
    public function mget(array $array)
    {
        $args = func_get_args();
        foreach ($args[0] as $key => $value) {
            $args[0][$key] = $this->options['prefix'] . $value;
        }
        return $this->_call(__FUNCTION__, $args, false, self::KEY_NO_KEY);
    }

    /**
     * @see mset()
     * @param   array $array
     * @return  int 1 (if the keys were set) or 0 (no key was set)
     * @link    http://redis.io/commands/msetnx
     */
    public function msetnx(array $array)
    {
        $oldArgs = func_get_args();
        $args = [];
        $item = [];
        foreach ($oldArgs[0] as $key => $value) {
            $key = $this->options['prefix'] . $key;
            $item[$key] = $value;
        }
        $args[] = $item;
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Pops a value from the tail of a list, and pushes it to the front of another list.
     * Also return this value.
     *
     * @since   redis >= 1.1
     * @param   string $srcKey
     * @param   string $dstKey
     * @return  string  The element that was moved in case of success, FALSE in case of failure.
     * @link    http://redis.io/commands/rpoplpush
     * @example
     * <pre>
     * $redis->delete('x', 'y');
     *
     * $redis->lPush('x', 'abc');
     * $redis->lPush('x', 'def');
     * $redis->lPush('y', '123');
     * $redis->lPush('y', '456');
     *
     * // move the last of x to the front of y.
     * var_dump($redis->rpoplpush('x', 'y'));
     * var_dump($redis->lRange('x', 0, -1));
     * var_dump($redis->lRange('y', 0, -1));
     *
     * //Output:
     * //
     * //string(3) "abc"
     * //array(1) {
     * //  [0]=>
     * //  string(3) "def"
     * //}
     * //array(3) {
     * //  [0]=>
     * //  string(3) "abc"
     * //  [1]=>
     * //  string(3) "456"
     * //  [2]=>
     * //  string(3) "123"
     * //}
     * </pre>
     */
    public function rpoplpush($srcKey, $dstKey)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_EVERY);
    }

    /**
     * A blocking version of rpoplpush, with an integral timeout in the third parameter.
     *
     * @param   string $srcKey
     * @param   string $dstKey
     * @param   int $timeout
     * @return  string  The element that was moved in case of success, FALSE in case of timeout.
     * @link    http://redis.io/commands/brpoplpush
     */
    public function brpoplpush($srcKey, $dstKey, $timeout)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST_AND_SECOND);
    }

    /**
     * Adds the specified member with a given score to the sorted set stored at key.
     *
     * @param   string $key Required key
     * @param   float $score1 Required score
     * @param   string $value1 Required value
     * @param   float $score2 Optional score
     * @param   string $value2 Optional value
     * @param   float $scoreN Optional score
     * @param   string $valueN Optional value
     * @return  int     Number of values added
     * @link    http://redis.io/commands/zadd
     * @example
     * <pre>
     * <pre>
     * $redis->zAdd('z', 1, 'v2', 2, 'v2', 3, 'v3', 4, 'v4' );  // int(2)
     * $redis->zRem('z', 'v2', 'v3');                           // int(2)
     * var_dump( $redis->zRange('z', 0, -1) );
     * //// Output:
     * // array(2) {
     * //   [0]=> string(2) "v1"
     * //   [1]=> string(2) "v4"
     * // }
     * </pre>
     * </pre>
     */
    public function zAdd($key, $score1, $value1, $score2 = null, $value2 = null, $scoreN = null, $valueN = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Returns a range of elements from the ordered set stored at the specified key,
     * with values in the range [start, end]. start and stop are interpreted as zero-based indices:
     * 0 the first element,
     * 1 the second ...
     * -1 the last element,
     * -2 the penultimate ...
     *
     * @param   string $key
     * @param   int $start
     * @param   int $end
     * @param   bool $withscores
     * @return  array   Array containing the values in specified range.
     * @link    http://redis.io/commands/zrange
     * @example
     * <pre>
     * $redis->zAdd('key1', 0, 'val0');
     * $redis->zAdd('key1', 2, 'val2');
     * $redis->zAdd('key1', 10, 'val10');
     * $redis->zRange('key1', 0, -1); // array('val0', 'val2', 'val10')
     * // with scores
     * $redis->zRange('key1', 0, -1, true); // array('val0' => 0, 'val2' => 2, 'val10' => 10)
     * </pre>
     */
    public function zRange($key, $start, $end, $withscores = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Deletes a specified member from the ordered set.
     *
     * @param   string $key
     * @param   string $member1
     * @param   string $member2
     * @param   string $memberN
     * @return  int     Number of deleted values
     * @link    http://redis.io/commands/zrem
     * @example
     * <pre>
     * $redis->zAdd('z', 1, 'v2', 2, 'v2', 3, 'v3', 4, 'v4' );  // int(2)
     * $redis->zRem('z', 'v2', 'v3');                           // int(2)
     * var_dump( $redis->zRange('z', 0, -1) );
     * //// Output:
     * // array(2) {
     * //   [0]=> string(2) "v1"
     * //   [1]=> string(2) "v4"
     * // }
     * </pre>
     */
    public function zRem($key, $member1, $member2 = null, $memberN = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * @see zRem()
     * @param   string $key
     * @param   string $member1
     * @param   string $member2
     * @param   string $memberN
     * @return  int     Number of deleted values
     * @link    http://redis.io/commands/zrem
     */
    public function zDelete($key, $member1, $member2 = null, $memberN = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Returns the elements of the sorted set stored at the specified key in the range [start, end]
     * in reverse order. start and stop are interpretated as zero-based indices:
     * 0 the first element,
     * 1 the second ...
     * -1 the last element,
     * -2 the penultimate ...
     *
     * @param   string $key
     * @param   int $start
     * @param   int $end
     * @param   bool $withscore
     * @return  array   Array containing the values in specified range.
     * @link    http://redis.io/commands/zrevrange
     * @example
     * <pre>
     * $redis->zAdd('key', 0, 'val0');
     * $redis->zAdd('key', 2, 'val2');
     * $redis->zAdd('key', 10, 'val10');
     * $redis->zRevRange('key', 0, -1); // array('val10', 'val2', 'val0')
     *
     * // with scores
     * $redis->zRevRange('key', 0, -1, true); // array('val10' => 10, 'val2' => 2, 'val0' => 0)
     * </pre>
     */
    public function zRevRange($key, $start, $end, $withscore = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Returns the elements of the sorted set stored at the specified key which have scores in the
     * range [start,end]. Adding a parenthesis before start or end excludes it from the range.
     * +inf and -inf are also valid limits.
     *
     * zRevRangeByScore returns the same items in reverse order, when the start and end parameters are swapped.
     *
     * @param   string $key
     * @param   int $start
     * @param   int $end
     * @param   array $options Two options are available:
     *                      - withscores => TRUE,
     *                      - and limit => array($offset, $count)
     * @return  array   Array containing the values in specified range.
     * @link    http://redis.io/commands/zrangebyscore
     * @example
     * <pre>
     * $redis->zAdd('key', 0, 'val0');
     * $redis->zAdd('key', 2, 'val2');
     * $redis->zAdd('key', 10, 'val10');
     * $redis->zRangeByScore('key', 0, 3);                                          // array('val0', 'val2')
     * $redis->zRangeByScore('key', 0, 3, array('withscores' => TRUE);              // array('val0' => 0, 'val2' => 2)
     * $redis->zRangeByScore('key', 0, 3, array('limit' => array(1, 1));                        // array('val2' => 2)
     * $redis->zRangeByScore('key', 0, 3, array('limit' => array(1, 1));                        // array('val2')
     * $redis->zRangeByScore('key', 0, 3, array('withscores' => TRUE, 'limit' => array(1, 1));  // array('val2' => 2)
     * </pre>
     */
    public function zRangeByScore($key, $start, $end, array $options = array())
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * @see zRangeByScore()
     * @param   string $key
     * @param   int $start
     * @param   int $end
     * @param   array $options
     *
     * @return    array
     */
    public function zRevRangeByScore($key, $start, $end, array $options = array())
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Returns a lexigraphical range of members in a sorted set, assuming the members have the same score. The
     * min and max values are required to start with '(' (exclusive), '[' (inclusive), or be exactly the values
     * '-' (negative inf) or '+' (positive inf).  The command must be called with either three *or* five
     * arguments or will return FALSE.
     * @param   string $key The ZSET you wish to run against.
     * @param   int $min The minimum alphanumeric value you wish to get.
     * @param   int $max The maximum alphanumeric value you wish to get.
     * @param   int $offset Optional argument if you wish to start somewhere other than the first element.
     * @param   int $limit Optional argument if you wish to limit the number of elements returned.
     * @return  array   Array containing the values in the specified range.
     * @link    http://redis.io/commands/zrangebylex
     * @example
     * <pre>
     * foreach (array('a', 'b', 'c', 'd', 'e', 'f', 'g') as $char) {
     *     $redis->zAdd('key', $char);
     * }
     *
     * $redis->zRangeByLex('key', '-', '[c'); // array('a', 'b', 'c')
     * $redis->zRangeByLex('key', '-', '(c'); // array('a', 'b')
     * $redis->zRangeByLex('key', '-', '[c'); // array('b', 'c')
     * </pre>
     */
    public function zRangeByLex($key, $min, $max, $offset = null, $limit = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * @see zRangeByLex()
     * @param   string $key
     * @param   int $min
     * @param   int $max
     * @param   int $offset
     * @param   int $limit
     * @return  array
     * @link    http://redis.io/commands/zrevrangebylex
     */
    public function zRevRangeByLex($key, $min, $max, $offset = null, $limit = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Returns the number of elements of the sorted set stored at the specified key which have
     * scores in the range [start,end]. Adding a parenthesis before start or end excludes it
     * from the range. +inf and -inf are also valid limits.
     *
     * @param   string $key
     * @param   string $start
     * @param   string $end
     * @return  int     the size of a corresponding zRangeByScore.
     * @link    http://redis.io/commands/zcount
     * @example
     * <pre>
     * $redis->zAdd('key', 0, 'val0');
     * $redis->zAdd('key', 2, 'val2');
     * $redis->zAdd('key', 10, 'val10');
     * $redis->zCount('key', 0, 3); // 2, corresponding to array('val0', 'val2')
     * </pre>
     */
    public function zCount($key, $start, $end)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Deletes the elements of the sorted set stored at the specified key which have scores in the range [start,end].
     *
     * @param   string $key
     * @param   float|string $start double or "+inf" or "-inf" string
     * @param   float|string $end double or "+inf" or "-inf" string
     * @return  int             The number of values deleted from the sorted set
     * @link    http://redis.io/commands/zremrangebyscore
     * @example
     * <pre>
     * $redis->zAdd('key', 0, 'val0');
     * $redis->zAdd('key', 2, 'val2');
     * $redis->zAdd('key', 10, 'val10');
     * $redis->zRemRangeByScore('key', 0, 3); // 2
     * </pre>
     */
    public function zRemRangeByScore($key, $start, $end)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * @see zRemRangeByScore()
     * @param string $key
     * @param float $start
     * @param float $end
     * @return int
     */
    public function zDeleteRangeByScore($key, $start, $end)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Deletes the elements of the sorted set stored at the specified key which have rank in the range [start,end].
     *
     * @param   string $key
     * @param   int $start
     * @param   int $end
     * @return  int     The number of values deleted from the sorted set
     * @link    http://redis.io/commands/zremrangebyrank
     * @example
     * <pre>
     * $redis->zAdd('key', 1, 'one');
     * $redis->zAdd('key', 2, 'two');
     * $redis->zAdd('key', 3, 'three');
     * $redis->zRemRangeByRank('key', 0, 1); // 2
     * $redis->zRange('key', 0, -1, array('withscores' => TRUE)); // array('three' => 3)
     * </pre>
     */
    public function zRemRangeByRank($key, $start, $end)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * @see zRemRangeByRank()
     * @param   string $key
     * @param   int $start
     * @param   int $end
     * @return int
     * @link    http://redis.io/commands/zremrangebyscore
     */
    public function zDeleteRangeByRank($key, $start, $end)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Returns the cardinality of an ordered set.
     *
     * @param   string $key
     * @return  int     the set's cardinality
     * @link    http://redis.io/commands/zsize
     * @example
     * <pre>
     * $redis->zAdd('key', 0, 'val0');
     * $redis->zAdd('key', 2, 'val2');
     * $redis->zAdd('key', 10, 'val10');
     * $redis->zCard('key');            // 3
     * </pre>
     */
    public function zCard($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * @see zCard()
     * @param string $key
     * @return int
     */
    public function zSize($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Returns the score of a given member in the specified sorted set.
     *
     * @param   string $key
     * @param   string $member
     * @return  float
     * @link    http://redis.io/commands/zscore
     * @example
     * <pre>
     * $redis->zAdd('key', 2.5, 'val2');
     * $redis->zScore('key', 'val2'); // 2.5
     * </pre>
     */
    public function zScore($key, $member)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Returns the rank of a given member in the specified sorted set, starting at 0 for the item
     * with the smallest score. zRevRank starts at 0 for the item with the largest score.
     *
     * @param   string $key
     * @param   string $member
     * @return  int     the item's score.
     * @link    http://redis.io/commands/zrank
     * @example
     * <pre>
     * $redis->delete('z');
     * $redis->zAdd('key', 1, 'one');
     * $redis->zAdd('key', 2, 'two');
     * $redis->zRank('key', 'one');     // 0
     * $redis->zRank('key', 'two');     // 1
     * $redis->zRevRank('key', 'one');  // 1
     * $redis->zRevRank('key', 'two');  // 0
     * </pre>
     */
    public function zRank($key, $member)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * @see zRank()
     * @param  string $key
     * @param  string $member
     * @return int    the item's score
     * @link   http://redis.io/commands/zrevrank
     */
    public function zRevRank($key, $member)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Increments the score of a member from a sorted set by a given amount.
     *
     * @param   string $key
     * @param   float $value (double) value that will be added to the member's score
     * @param   string $member
     * @return  float   the new value
     * @link    http://redis.io/commands/zincrby
     * @example
     * <pre>
     * $redis->delete('key');
     * $redis->zIncrBy('key', 2.5, 'member1');  // key or member1 didn't exist, so member1's score is to 0
     *                                          // before the increment and now has the value 2.5
     * $redis->zIncrBy('key', 1, 'member1');    // 3.5
     * </pre>
     */
    public function zIncrBy($key, $value, $member)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Creates an union of sorted sets given in second argument.
     * The result of the union will be stored in the sorted set defined by the first argument.
     * The third optionnel argument defines weights to apply to the sorted sets in input.
     * In this case, the weights will be multiplied by the score of each element in the sorted set
     * before applying the aggregation. The forth argument defines the AGGREGATE option which
     * specify how the results of the union are aggregated.
     *
     * @param string $Output
     * @param array $ZSetKeys
     * @param array $Weights
     * @param string $aggregateFunction Either "SUM", "MIN", or "MAX": defines the behaviour to use on
     * duplicate entries during the zUnion.
     * @return int The number of values in the new sorted set.
     * @link    http://redis.io/commands/zunionstore
     * @example
     * <pre>
     * $redis->delete('k1');
     * $redis->delete('k2');
     * $redis->delete('k3');
     * $redis->delete('ko1');
     * $redis->delete('ko2');
     * $redis->delete('ko3');
     *
     * $redis->zAdd('k1', 0, 'val0');
     * $redis->zAdd('k1', 1, 'val1');
     *
     * $redis->zAdd('k2', 2, 'val2');
     * $redis->zAdd('k2', 3, 'val3');
     *
     * $redis->zUnion('ko1', array('k1', 'k2')); // 4, 'ko1' => array('val0', 'val1', 'val2', 'val3')
     *
     * // Weighted zUnion
     * $redis->zUnion('ko2', array('k1', 'k2'), array(1, 1)); // 4, 'ko2' => array('val0', 'val1', 'val2', 'val3')
     * $redis->zUnion('ko3', array('k1', 'k2'), array(5, 1)); // 4, 'ko3' => array('val0', 'val2', 'val3', 'val1')
     * </pre>
     */
    public function zUnion($Output, $ZSetKeys, array $Weights = null, $aggregateFunction = 'SUM')
    {
        $args = func_get_args();
        foreach ($args[1] as $key => $value) {
            $args[1][$key] = $this->options['prefix'] . $value;
        }
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Creates an intersection of sorted sets given in second argument.
     * The result of the union will be stored in the sorted set defined by the first argument.
     * The third optional argument defines weights to apply to the sorted sets in input.
     * In this case, the weights will be multiplied by the score of each element in the sorted set
     * before applying the aggregation. The forth argument defines the AGGREGATE option which
     * specify how the results of the union are aggregated.
     *
     * @param   string $Output
     * @param   array $ZSetKeys
     * @param   array $Weights
     * @param   string $aggregateFunction Either "SUM", "MIN", or "MAX":
     * defines the behaviour to use on duplicate entries during the zInter.
     * @return  int     The number of values in the new sorted set.
     * @link    http://redis.io/commands/zinterstore
     * @example
     * <pre>
     * $redis->delete('k1');
     * $redis->delete('k2');
     * $redis->delete('k3');
     *
     * $redis->delete('ko1');
     * $redis->delete('ko2');
     * $redis->delete('ko3');
     * $redis->delete('ko4');
     *
     * $redis->zAdd('k1', 0, 'val0');
     * $redis->zAdd('k1', 1, 'val1');
     * $redis->zAdd('k1', 3, 'val3');
     *
     * $redis->zAdd('k2', 2, 'val1');
     * $redis->zAdd('k2', 3, 'val3');
     *
     * $redis->zInter('ko1', array('k1', 'k2'));               // 2, 'ko1' => array('val1', 'val3')
     * $redis->zInter('ko2', array('k1', 'k2'), array(1, 1));  // 2, 'ko2' => array('val1', 'val3')
     *
     * // Weighted zInter
     * $redis->zInter('ko3', array('k1', 'k2'), array(1, 5), 'min'); // 2, 'ko3' => array('val1', 'val3')
     * $redis->zInter('ko4', array('k1', 'k2'), array(1, 5), 'max'); // 2, 'ko4' => array('val3', 'val1')
     * </pre>
     */
    public function zInter($Output, $ZSetKeys, array $Weights = null, $aggregateFunction = 'SUM')
    {
        $args = func_get_args();
        foreach ($args[1] as $key => $value) {
            $args[1][$key] = $this->options['prefix'] . $value;
        }
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Adds a value to the hash stored at key. If this value is already in the hash, FALSE is returned.
     *
     * @param string $key
     * @param string $hashKey
     * @param string $value
     * @return int
     * 1 if value didn't exist and was added successfully,
     * 0 if the value was already present and was replaced, FALSE if there was an error.
     * @link    http://redis.io/commands/hset
     * @example
     * <pre>
     * $redis->delete('h')
     * $redis->hSet('h', 'key1', 'hello');  // 1, 'key1' => 'hello' in the hash at "h"
     * $redis->hGet('h', 'key1');           // returns "hello"
     *
     * $redis->hSet('h', 'key1', 'plop');   // 0, value was replaced.
     * $redis->hGet('h', 'key1');           // returns "plop"
     * </pre>
     */
    public function hSet($key, $hashKey, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Adds a value to the hash stored at key only if this field isn't already in the hash.
     *
     * @param   string $key
     * @param   string $hashKey
     * @param   string $value
     * @return  bool    TRUE if the field was set, FALSE if it was already present.
     * @link    http://redis.io/commands/hsetnx
     * @example
     * <pre>
     * $redis->delete('h')
     * $redis->hSetNx('h', 'key1', 'hello'); // TRUE, 'key1' => 'hello' in the hash at "h"
     * $redis->hSetNx('h', 'key1', 'world'); // FALSE, 'key1' => 'hello' in the hash at "h". No change since the field
     * wasn't replaced.
     * </pre>
     */
    public function hSetNx($key, $hashKey, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Gets a value from the hash stored at key.
     * If the hash table doesn't exist, or the key doesn't exist, FALSE is returned.
     *
     * @param   string $key
     * @param   string $hashKey
     * @return  string  The value, if the command executed successfully BOOL FALSE in case of failure
     * @link    http://redis.io/commands/hget
     */
    public function hGet($key, $hashKey)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Returns the length of a hash, in number of items
     *
     * @param   string $key
     * @return  int     the number of items in a hash, FALSE if the key doesn't exist or isn't a hash.
     * @link    http://redis.io/commands/hlen
     * @example
     * <pre>
     * $redis->delete('h')
     * $redis->hSet('h', 'key1', 'hello');
     * $redis->hSet('h', 'key2', 'plop');
     * $redis->hLen('h'); // returns 2
     * </pre>
     */
    public function hLen($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Removes a values from the hash stored at key.
     * If the hash table doesn't exist, or the key doesn't exist, FALSE is returned.
     *
     * @param   string $key
     * @param   string $hashKey1
     * @param   string $hashKey2
     * @param   string $hashKeyN
     * @return  int     Number of deleted fields
     * @link    http://redis.io/commands/hdel
     * @example
     * <pre>
     * $redis->hMSet('h',
     *               array(
     *                    'f1' => 'v1',
     *                    'f2' => 'v2',
     *                    'f3' => 'v3',
     *                    'f4' => 'v4',
     *               ));
     *
     * var_dump( $redis->hDel('h', 'f1') );        // int(1)
     * var_dump( $redis->hDel('h', 'f2', 'f3') );  // int(2)
     * s
     * var_dump( $redis->hGetAll('h') );
     * //// Output:
     * //  array(1) {
     * //    ["f4"]=> string(2) "v4"
     * //  }
     * </pre>
     */
    public function hDel($key, $hashKey1, $hashKey2 = null, $hashKeyN = null)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Returns the keys in a hash, as an array of strings.
     *
     * @param   string $key
     * @return  array   An array of elements, the keys of the hash. This works like PHP's array_keys().
     * @link    http://redis.io/commands/hkeys
     * @example
     * <pre>
     * $redis->delete('h');
     * $redis->hSet('h', 'a', 'x');
     * $redis->hSet('h', 'b', 'y');
     * $redis->hSet('h', 'c', 'z');
     * $redis->hSet('h', 'd', 't');
     * var_dump($redis->hKeys('h'));
     *
     * // Output:
     * // array(4) {
     * // [0]=>
     * // string(1) "a"
     * // [1]=>
     * // string(1) "b"
     * // [2]=>
     * // string(1) "c"
     * // [3]=>
     * // string(1) "d"
     * // }
     * // The order is random and corresponds to redis' own internal representation of the set structure.
     * </pre>
     */
    public function hKeys($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Returns the values in a hash, as an array of strings.
     *
     * @param   string $key
     * @return  array   An array of elements, the values of the hash. This works like PHP's array_values().
     * @link    http://redis.io/commands/hvals
     * @example
     * <pre>
     * $redis->delete('h');
     * $redis->hSet('h', 'a', 'x');
     * $redis->hSet('h', 'b', 'y');
     * $redis->hSet('h', 'c', 'z');
     * $redis->hSet('h', 'd', 't');
     * var_dump($redis->hVals('h'));
     *
     * // Output
     * // array(4) {
     * //   [0]=>
     * //   string(1) "x"
     * //   [1]=>
     * //   string(1) "y"
     * //   [2]=>
     * //   string(1) "z"
     * //   [3]=>
     * //   string(1) "t"
     * // }
     * // The order is random and corresponds to redis' own internal representation of the set structure.
     * </pre>
     */
    public function hVals($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Returns the whole hash, as an array of strings indexed by strings.
     *
     * @param   string $key
     * @return  array   An array of elements, the contents of the hash.
     * @link    http://redis.io/commands/hgetall
     * @example
     * <pre>
     * $redis->delete('h');
     * $redis->hSet('h', 'a', 'x');
     * $redis->hSet('h', 'b', 'y');
     * $redis->hSet('h', 'c', 'z');
     * $redis->hSet('h', 'd', 't');
     * var_dump($redis->hGetAll('h'));
     *
     * // Output:
     * // array(4) {
     * //   ["a"]=>
     * //   string(1) "x"
     * //   ["b"]=>
     * //   string(1) "y"
     * //   ["c"]=>
     * //   string(1) "z"
     * //   ["d"]=>
     * //   string(1) "t"
     * // }
     * // The order is random and corresponds to redis' own internal representation of the set structure.
     * </pre>
     */
    public function hGetAll($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Verify if the specified member exists in a key.
     *
     * @param   string $key
     * @param   string $hashKey
     * @return  bool    If the member exists in the hash table, return TRUE, otherwise return FALSE.
     * @link    http://redis.io/commands/hexists
     * @example
     * <pre>
     * $redis->hSet('h', 'a', 'x');
     * $redis->hExists('h', 'a');               //  TRUE
     * $redis->hExists('h', 'NonExistingKey');  // FALSE
     * </pre>
     */
    public function hExists($key, $hashKey)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Increments the value of a member from a hash by a given amount.
     *
     * @param   string $key
     * @param   string $hashKey
     * @param   int $value (integer) value that will be added to the member's value
     * @return  int     the new value
     * @link    http://redis.io/commands/hincrby
     * @example
     * <pre>
     * $redis->delete('h');
     * $redis->hIncrBy('h', 'x', 2); // returns 2: h[x] = 2 now.
     * $redis->hIncrBy('h', 'x', 1); // h[x] ← 2 + 1. Returns 3
     * </pre>
     */
    public function hIncrBy($key, $hashKey, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Increment the float value of a hash field by the given amount
     * @param   string $key
     * @param   string $field
     * @param   float $increment
     * @return  float
     * @link    http://redis.io/commands/hincrbyfloat
     * @example
     * <pre>
     * $redis = new Redis();
     * $redis->connect('127.0.0.1');
     * $redis->hset('h', 'float', 3);
     * $redis->hset('h', 'int',   3);
     * var_dump( $redis->hIncrByFloat('h', 'float', 1.5) ); // float(4.5)
     *
     * var_dump( $redis->hGetAll('h') );
     *
     * // Output
     *  array(2) {
     *    ["float"]=>
     *    string(3) "4.5"
     *    ["int"]=>
     *    string(1) "3"
     *  }
     * </pre>
     */
    public function hIncrByFloat($key, $field, $increment)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Fills in a whole hash. Non-string values are converted to string, using the standard (string) cast.
     * NULL values are stored as empty strings
     *
     * @param   string $key
     * @param   array $hashKeys key → value array
     * @return  bool
     * @link    http://redis.io/commands/hmset
     * @example
     * <pre>
     * $redis->delete('user:1');
     * $redis->hMset('user:1', array('name' => 'Joe', 'salary' => 2000));
     * $redis->hIncrBy('user:1', 'salary', 100); // Joe earns 100 more now.
     * </pre>
     */
    public function hMset($key, $hashKeys)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Retirieve the values associated to the specified fields in the hash.
     *
     * @param   string $key
     * @param   array $hashKeys
     * @return  array   Array An array of elements, the values of the specified fields in the hash,
     * with the hash keys as array keys.
     * @link    http://redis.io/commands/hmget
     * @example
     * <pre>
     * $redis->delete('h');
     * $redis->hSet('h', 'field1', 'value1');
     * $redis->hSet('h', 'field2', 'value2');
     * $redis->hmGet('h', array('field1', 'field2')); // returns array('field1' => 'value1', 'field2' => 'value2')
     * </pre>
     */
    public function hMGet($key, $hashKeys)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * The last error message (if any)
     * @return  string  A string with the last returned script based error message, or NULL if there is no error
     * @example
     * <pre>
     * $redis->eval('this-is-not-lua');
     * $err = $redis->getLastError();
     * // "ERR Error compiling script (new function): user_script:1: '=' expected near '-'"
     * </pre>
     */
    public function getLastError()
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Clear the last error message
     *
     * @return bool true
     * @example
     * <pre>
     * $redis->set('x', 'a');
     * $redis->incr('x');
     * $err = $redis->getLastError();
     * // "ERR value is not an integer or out of range"
     * $redis->clearLastError();
     * $err = $redis->getLastError();
     * // NULL
     * </pre>
     */
    public function clearLastError()
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_NO_KEY);
    }

    /**
     * Dump a key out of a redis database, the value of which can later be passed into redis using the RESTORE command.
     * The data that comes out of DUMP is a binary representation of the key as Redis stores it.
     * @param   string $key
     * @return  string  The Redis encoded value of the key, or FALSE if the key doesn't exist
     * @link    http://redis.io/commands/dump
     * @example
     * <pre>
     * $redis->set('foo', 'bar');
     * $val = $redis->dump('foo'); // $val will be the Redis encoded key value
     * </pre>
     */
    public function dump($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Restore a key from the result of a DUMP operation.
     *
     * @param   string $key The key name
     * @param   int $ttl How long the key should live (if zero, no expire will be set on the key)
     * @param   string $value (binary).  The Redis encoded key value (from DUMP)
     * @return  bool
     * @link    http://redis.io/commands/restore
     * @example
     * <pre>
     * $redis->set('foo', 'bar');
     * $val = $redis->dump('foo');
     * $redis->restore('bar', 0, $val); // The key 'bar', will now be equal to the key 'foo'
     * </pre>
     */
    public function restore($key, $ttl, $value)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * Migrates a key to a different Redis instance.
     *
     * @param   string $host The destination host
     * @param   int $port The TCP port to connect to.
     * @param   string $key The key to migrate.
     * @param   int $db The target DB.
     * @param   int $timeout The maximum amount of time given to this transfer.
     * @param   bool $copy Should we send the COPY flag to redis.
     * @param   bool $replace Should we send the REPLACE flag to redis.
     * @return  bool
     * @link    http://redis.io/commands/migrate
     * @example
     * <pre>
     * $redis->migrate('backup', 6379, 'foo', 0, 3600);
     * </pre>
     */
    public function migrate($host, $port, $key, $db, $timeout, $copy = false, $replace = false)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_THIRD);
    }

    /**
     * Return the current Redis server time.
     * @return  array If successfull, the time will come back as an associative array with element zero being the
     * unix timestamp, and element one being microseconds.
     * @link    http://redis.io/commands/time
     * <pre>
     * var_dump( $redis->time() );
     * // array(2) {
     * //   [0] => string(10) "1342364352"
     * //   [1] => string(6) "253002"
     * // }
     * </pre>
     */
    public function time()
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_NO_KEY);
    }

    /**
     * Adds all the element arguments to the HyperLogLog data structure stored at the key.
     * @param   string $key
     * @param   array $elements
     * @return  bool
     * @link    http://redis.io/commands/pfadd
     * @example $redis->pfAdd('key', array('elem1', 'elem2'))
     */
    public function pfAdd($key, array $elements)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * When called with a single key, returns the approximated cardinality computed by the HyperLogLog data
     * structure stored at the specified variable, which is 0 if the variable does not exist.
     * @param   string|array $key
     * @return  int
     * @link    http://redis.io/commands/pfcount
     * @example
     * <pre>
     * $redis->pfAdd('key1', array('elem1', 'elem2'));
     * $redis->pfAdd('key2', array('elem3', 'elem2'));
     * $redis->pfCount('key1'); // int(2)
     * $redis->pfCount(array('key1', 'key2')); // int(3)
     */
    public function pfCount($key)
    {
        $args = func_get_args();
        return $this->_call(__FUNCTION__, $args, false, self::KEY_FIRST);
    }

    /**
     * Merge multiple HyperLogLog values into an unique value that will approximate the cardinality
     * of the union of the observed Sets of the source HyperLogLog structures.
     * @param   string $destkey
     * @param   array $sourcekeys
     * @return  bool
     * @link    http://redis.io/commands/pfmerge
     * @example
     * <pre>
     * $redis->pfAdd('key1', array('elem1', 'elem2'));
     * $redis->pfAdd('key2', array('elem3', 'elem2'));
     * $redis->pfMerge('key3', array('key1', 'key2'));
     * $redis->pfCount('key3'); // int(3)
     */
    public function pfMerge($destkey, array $sourcekeys)
    {
        $args = func_get_args();
        foreach ($args[1] as $key => $value) {
            $args[1][$key] = $this->options['prefix'] . $value;
        }
        return $this->_call(__FUNCTION__, $args, true, self::KEY_FIRST);
    }

    /**
     * @param string $method 方法名
     * @param array $args 参数数组
     * @param bool $isMaster 是否是主库操作
     * @param int $keyPosition key的位置 为了将key加上前缀
     * @return mixed 返回方法原本的返回
     * Author: hj
     * Desc: 调用缓存方法的 处理函数
     * Date: 2017-11-13 12:55:33
     * Update: 2017-11-13 12:55:33
     * Version: 1.0
     */
    private function _call($method, $args, $isMaster = true, $keyPosition = self::KEY_FIRST)
    {
        //调用缓存类型自己的方法
        if (method_exists($this->handler, $method)) {
            $this->handler = $this->isMaster($isMaster);
            switch ((int)$keyPosition) {
                case self::KEY_FIRST:
                    $args[0] = $this->options['prefix'] . $args[0];
                    break;
                case self::KEY_NOT_FIRST:
                    foreach ($args as $k => $val) {
                        if ($k === 0) continue;
                        $args[$k] = $this->options['prefix'] . $val;
                    }
                    break;
                case self::KEY_EVERY:
                    foreach ($args as $k => $val) {
                        $args[$k] = $this->options['prefix'] . $val;
                    }
                    break;
                case self::KEY_FIRST_AND_SECOND:
                    $args[0] = $this->options['prefix'] . $args[0];
                    $args[1] = $this->options['prefix'] . $args[1];
                    break;
                case self::KEY_THIRD:
                    $args[2] = $this->options['prefix'] . $args[2];
                    break;
                default:
                    break;
            }
            return call_user_func_array(array($this->handler, $method), $args);
        } else {
            E(__CLASS__ . ':' . $method . L('_METHOD_NOT_EXIST_'));
            return false;
        }
    }

    /**
     * 设置缓存前缀
     * @param string $prefix
     * @return string ['code'=>200, 'msg'=>'', 'data'=>null]
     * User: hjun
     * Date: 2017-12-04 17:35:18
     * Update: 2017-12-04 17:35:18
     * Version: 1.00
     */
    public function setPrefix($prefix = '')
    {
        return $this->options['prefix'] = $prefix;
    }

    /**
     * 生命周期结束
     */
    public function __destruct()
    {

    }

}
