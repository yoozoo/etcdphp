<?php
/**
 * @see  https://github.com/yoozoo/etcdphp
 * @author  chenfang<crossfire1103@gmail.com>
 */

namespace Yoozoo;

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

    /**
     * @var String
     */
    protected $etcd_endpoints;

    /**
     * @var String
     */
    protected $etcd_user;

    /**
     * @var Bool
     * will not read and set cache file if this flag is true
     */
    protected $disable_cache = false;

    public function __construct($cache_path = '/tmp/confcache', $etcd_endpoints = "127.0.0.1:2379", $etcd_user = "root:root")
    {
        // Priority: param > env > default

        // set envKey
        $this->envKey = getenv("etcd_envkey");
        if (empty($this->envKey)) {
            $this->envKey = "default";
        }

        // set etcd endpoints
        if (empty($etcd_endpoints)) {
            $etcd_endpoints = getenv("etcd_endpoints");
            if (empty($etcd_endpoints)) {
                $etcd_endpoints = "127.0.0.1:2379";
            }
        }
        $this->etcd_endpoints = $etcd_endpoints;

        // set etcd user
        if (empty($etcd_user)) {
            $etcd_user = getenv("etcd_user");
            if (empty($etcd_user)) {
                $etcd_user = "root:root";
            }
        }
        $this->etcd_user = $etcd_user;

        // check if disable cache flag is set.
        if (getenv("etcd_disable_cache")) {
            $this->disable_cache = true;
        }

        $this->cache_path = $cache_path;
    }

    /**
     * Set etcd_endpoints and etcd_user
     *
     * @param String $etcd_endpoints
     * @param String $etcd_user
     * @return void
     */
    public function setEtcdConfig($etcd_endpoints, $etcd_user)
    {
        $this->etcd_endpoints = $etcd_endpoints;
        $this->etcd_user = $etcd_user;
    }

    /**
     * Set cahce path
     *
     * @param String $cache_path
     * @return void
     */
    public function setCachePath($cache_path)
    {
        $this->cache_path = $cache_path;
    }

    /**
     * Return complete key: /env/appname/key
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
        $node = $this->etcd_endpoints;
        //support multiple nodes. Use one of them
        $node = explode(",", $node)[0];

        $user = $this->etcd_user;

        $this->client = new EtcdClient($node);
        $this->client->setPretty(true);

        if (!empty($user)) {
            $user = explode(":", $user, 2);
            $username = $user[0];
            $password = $user[1];
            $token = $this->client->authenticate($username, $password);
            $this->client->setToken($token);
        } else {
            echo "etcdphp connection exception: User is empty.";
            return "";
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

        if (!$this->disable_cache) {
            @include $this->cache_path . $key;
            if (isset($val)) {
                return $val;
            }
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

        if (!$this->disable_cache) {
            try {
                self::cache_set($key, $val);
            } catch (Exception $e) {
                echo "etcdphp cache exception: " . $e;
            }
        }

        return $val;
    }
}
