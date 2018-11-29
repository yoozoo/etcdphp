<?php
/**
 * @see  https://github.com/yoozoo/etcdphp
 * @author  chenfang<crossfire1103@gmail.com>
 */

namespace Yoozoo;

use agentApplicationService;
use Yoozoo\Etcd\EtcdClient;

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
     * will not read or set cache file if this flag is true
     */
    protected $disable_cache = false;

    /**
     * @var String
     */
    protected $app_token;

    /**
     * @var String
     */
    protected $app_name;

    /**
     * @var Bool
     */
    protected $read_from_local_flag;

    /**
     * @var String
     */
    protected $local_file_path;

    /**
     * template for php cache file
     */
    const template = "<?php\n// mod_revision = %s\n// version = %s\n\$val = %s;\n";

    public function __construct($cache_path = "", $etcd_endpoints = "", $etcd_user = "")
    {
        /** Priority: param > env > protoagent > default
         *  all variable cannot be empty
         * */

        /** set from param **/
        // set etcd config
        $this->etcd_endpoints = $etcd_endpoints;
        $this->etcd_user = $etcd_user;
        // set cache file
        $this->cache_path = $cache_path;

        /** set from env **/
        // set envKey
        $this->envKey = getenv("etcd_envkey");
        // set etcd config
        if (empty($this->etcd_endpoints)) {
            $this->etcd_endpoints = getenv("etcd_endpoints");
        }
        if (empty($this->etcd_user)) {
            $this->etcd_user = getenv("etcd_user");
        }
        // set disable cache file
        if (getenv("etcd_disable_cache")) {
            $this->disable_cache = true;
        }

        /** set from default **/
        // set envKey
        if (empty($this->envKey)) {
            $this->envKey = "default";
        }
        // set confcache path
        if (empty($this->cache_path)) {
            $this->cache_path = "/data/confcache";
        }
    }

    /**
     * Get etcd configuration from protoagent (version.uuzu.com/Merlion/protoagent)
     *
     * @return Agent\LogonInfoReply
     */
    public function getEtcdConfigFromAgent()
    {
        $agent = new agentApplicationService\AgentApplicationService("http://127.0.0.1:57582");
        $logonInfoRequest = new agentApplicationService\LogonInfoRequest();
        $logonInfoRequest->set_app_token($this->app_token);
        $logonInfoRequest->set_env($this->envKey);
        try {
            $logonInfoReply = $agent->getLogonInfo($logonInfoRequest);
            return $logonInfoReply;
        } catch (Exception $e) {
            echo $e->getMessage();
            return;
        }
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
     * Set app name
     *
     * @param String $app_name
     * @return void
     */
    public function setAppName($app_name)
    {
        $this->app_name = $app_name;
    }

    /**
     * Set app token
     *
     * @param String $app_token
     * @return void
     */
    public function setAppToken($app_token)
    {
        $this->app_token = $app_token;
    }

    /**
     * Set read_from_local_flag
     *
     * @param Bool $flag
     * @return void
     */
    public function setReadFromLocalFlag($flag)
    {
        $this->read_from_local_flag = $flag;
    }

    /**
     * Set Local File Path
     *
     * @param String $file_path
     * @return void
     */
    public function setLocalFilePath($file_path)
    {
        $this->local_file_path = $file_path;
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
        /** set from protoagent **/

        if (empty($this->etcd_endpoints) || empty($this->etcd_user)) {
            $agentResult = $this->getEtcdConfigFromAgent();
            if (isset($agentResult)) {
                $this->etcd_endpoints = $agentResult->get_endpoints();
                $this->etcd_user = $agentResult->get_user() . ":" . $agentResult->get_password();
            }
        }

        /** set from default **/
        // will not support default value in the furture
        if (empty($this->etcd_endpoints)) {
            $this->etcd_endpoints = "127.0.0.1:2379";
        }
        if (empty($this->etcd_user)) {
            $this->etcd_user = "root:root";
        }

        $node = $this->etcd_endpoints;
        //support multiple nodes. Use one of them
        $node = explode(",", $node);

        $user = $this->etcd_user;

        try {
            $this->client = new EtcdClient($node);
        } catch (Exception $e) {
            echo $e->getMessage();
            return "";
        }
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
    public function cache_set($key, $result_array)
    {
        $val = var_export($result_array['value'], true);
        $val = str_replace('stdClass::__set_state', '(object)', $val);

        $tmp = $this->cache_path . $key . "." . uniqid('', true) . '.tmp';
        $dir_path = substr($this->cache_path . $key, 0, strrpos($this->cache_path . $key, "/"));
        if (!is_dir($dir_path)) {
            mkdir($dir_path, 0755, true);
        }
        file_put_contents($tmp, sprintf(self::template, $result_array['mod_revision'], $result_array['version'], $val), LOCK_EX);
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
        if ($this->read_from_local_flag) {
            return $this->read_from_local($key);
        }

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

            $this->client->setPretty(false); // set pretty output to false to get full output
            $result = $this->client->get($key);
        } catch (Exception $e) {
            echo "etcdphp connection exception: " . $e;
            return "";
        }

        if (isset($result['kvs'])) {
            $result_array = $result['kvs'][0];
            if (!isset($result_array["value"])) {
                $result_array["value"] = ""; // set value to empty string is value is NULL
            }
        } else {
            $result_array = array(
                "value" => "",
                "mod_revision" => "-1",
                "version" => "-1",
            );
        }

        if (!$this->disable_cache) {
            try {
                self::cache_set($key, $result_array);
            } catch (Exception $e) {
                echo "etcdphp cache exception: " . $e;
            }
        }

        return $result_array["value"];
    }

    /**
     * Read Key From Local File
     *
     * @param String $key
     * @return void
     */
    private function read_from_local($key)
    {
        $content = file_get_contents($this->local_file_path);
        $jsonData = json_decode($content, true);

        // recursively find result
        $keys = explode("/", $key, 3)[2];
        $keys = explode("/", $keys);
        foreach ($keys as $k) {
            if (!isset($jsonData[$k])) {
                echo "etcdphp local file exception: Cannot find key " . $k . "\n" . $content;
                return "";
            }
            $jsonData = $jsonData[$k];
        }

        return $jsonData;
    }
}
