<?php
/**
 * @see  https://github.com/yoozoo/etcdphp
 * @author  chenfang<crossfire1103@gmail.com>
 */

namespace Youzusg;

use Etcd\Client as EtcdClient;
use Exception;

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
    /**
     * @var String
     */
    protected $envKey;

    public function __construct($cache_path = __DIR__ . '/tmp/confcache')
    {
        $this->envKey = getenv("etcd_envKey");
        if (empty($this->envKey)) {
            $this->envKey = "default";
        }

        $this->cache_path = $cache_path;
    }

    /**
     * Return complete key
     *
     * @param String $key
     * @return String
     */
    private function buildKey($key)
    {
        //key must start with "/"
        if (!substr($key, 0, 1) === "/") {
            $key = "/" . $key;
        }

        $result = "/" . $this->envKey . $key;

        return $result;
    }

    /**
     * Use ENV variables to create ETCD connection.
     *
     * @return void
     */
    public function connect()
    {
        $node = getenv("etcd_endpoints");
        if (empty($node)) {
            $node = "127.0.0.1:2379";
        }
        //support multiple nodes. Use one of them
        $node = explode(",", $node)[0];

        $user = getenv("etcd_user");

        $this->client = new EtcdClient($node);
        $this->client->setPretty(true);

        if (!empty($user)) {
            $user = explode(":", $user, 2);
            $username = $user[0];
            $password = $user[1];
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
        if (!is_dir($dir_path)) {
            mkdir($dir_path, 0755, true);
        }
        file_put_contents($tmp, '<?php $val = ' . $val . ';', LOCK_EX);
        rename($tmp, $this->cache_path . $key);
    }

    /**
     * Get key from cache file. If not found, get key from Etcd.
     *
     * @param string $key
     * @return void
     */
    public function get_key($key)
    {
        $key = $this->buildKey($key);

        @include $this->cache_path . $key;
        if (isset($val)) {
            return $val;
        }

        try {
            if (!isset($this->client)) {
                $this->connect();
            }
            $result = $this->client->get($key);
        } catch (Exception $e) {
            echo "etcdphp connection exception: " . $e;
            return "";
        }

        $val = array_key_exists($key, $result) ? $result[$key] : "";

        try {
            self::cache_set($key, $val);
        } catch (Exception $e) {
            echo "etcdphp cache exception: " . $e;
        }

        return $val;
    }
}
