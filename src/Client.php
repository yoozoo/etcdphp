<?php
/**
 * @see  https://github.com/youzusg/etcd-tmp
 * @author  chenfang<crossfire1103@gmail.com>
 */

namespace Youzusg;

use Etcd\Client as EtcdClient;

class Client
{
    /**
     * @var cache path
     */
    protected $cache_path;
    /**
     * @var \Etcd\Client
     */
    protected $client;

    public function __construct($cache_path = __DIR__ . '/tmp/confcache')
    {
        $this->cache_path = $cache_path;
    }
    
    /**
     * Use ENV variables to create ETCD connection.
     *
     * @return void
     */
    public function connect()
    {
        $node = getenv("ETCD_NODE");
        if (empty($node)) {
            $node = "127.0.0.1:2379";
        }

        $username = getenv("ETCD_USERNAME");
        $password = getenv("ETCD_PASSWORD");

        $this->client = new EtcdClient($node);
        $this->client->setPretty(true);

        if (!empty($username)) {
            $token = $this->client->authenticate($username, $password);
            $this->client->setToken($token);
        }

    }

    /**
     * create cache file
     *
     * @param string $key
     * @param string $val
     * @return void
     */
    public function cache_set($key, $val)
    {
        //echo "set key: $key";
        $val = var_export($val, true);

        $val = str_replace('stdClass::__set_state', '(object)', $val);

        $tmp = $this->cache_path . $key . "." . uniqid('', true) . '.tmp';
        $dir_path = substr($this->cache_path . $key, 0, strrpos($this->cache_path . $key, "/"));
        mkdir($dir_path, 0755, true);
        file_put_contents($tmp, '<?php $val = ' . $val . ';', LOCK_EX);
        rename($tmp, "$this->cache_path$key");
    }

    /**
     * Get key from cache file. If not found, get key from Etcd.
     *
     * @param string $key
     * @return void
     */
    public function get_key($key)
    {
        if (!substr($key, 0, 1) === "/") {
            throw new Exception('key must start with /');
        }
        @include "$this->cache_path$key";
        if (isset($val)) {
            return $val;
        }

        if (!isset($this->client)) {
            $this->connect();
        }
        $val = $this->client->get($key)[$key];
        if (isset($val)) {
            self::cache_set($key, $val);
            return $val;
        } else {
            throw new Exception('The value is empty.');
        }
    }
}
