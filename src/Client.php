<?php
/**
 * @see  https://github.com/youzusg/etcd-tmp
 * @author  chenfang<crossfire1103@gmail.com>
 */

namespace Youzusg;

use Etcd\Client as EtcdClient;

class Client {

	protected $cache_path = __DIR__ . '/tmp/confcache';
	protected $client;

	function __contruct($cache_path){
		$this->cache_path = $cache_path;
	}

	function connect(){
		$node = getenv("ETCD_NODE");
		$username = getenv("ETCD_USERNAME");
		$password = getenv("ETCD_PASSWORD");

		$this->client = new EtcdClient($this->node);
		$this->client->setPretty(true);

		if(!empty($username)){
			$token = $this->client->authenticate($username, $password);
			$this->client->setToken($token);
		}

	}

	function cache_set($key, $val) {
		//echo "set key: $key";
		$val = var_export($val, true);
		
		$val = str_replace('stdClass::__set_state', '(object)', $val);
		
		$tmp = $this->cache_path . $key . "." . uniqid('', true) . '.tmp';
		$dir_path = substr($this->cache_path . $key, 0, strrpos($this->cache_path . $key, "/"));
		mkdir($dir_path, 0755, true);
		file_put_contents($tmp, '<?php $val = ' . $val . ';', LOCK_EX);
		rename($tmp, "$this->cache_path$key");
	}

	function get_key($client, $key) {
		if(!substr($key, 0, 1) === "/") {
			throw new Exception('key must start with /');
		}
		@include "$this->cache_path$key";
		if(isset($val)) {
			return $val;
		}

		if(!isset($this->client)){
			$this->connect();
		}
		$val = $this->client->get($key)[$key];
		cache_set($key, $val);
		return $val;
	}
}