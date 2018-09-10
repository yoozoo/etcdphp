# etcdphp
Combine Etcd-client and temp file to build a kv store. Inpired by [etcd-php](https://github.com/ouqiang/etcd-php).

# Config
This package read config from constructor param or env.

Priority : params > env.

name        | param name | env name | proto agent | default value | decription
----        | --- | --- | --- | --- | ---
cache path  | cache_path | - | - | /tmp/confcache | Path of generated php tmp file
etcd endpoints |  etcd_endpoints | etcd_endpoints | endpoints | 127.0.0.1:2379 | end points of etcd server
etcd user | etcd_user | etcd_user | user, password | root:root | username:password
etcd envkey | - | etcd_envKey | - | default | indicate current env (for furture use)
disable cache flag | - | etcd_disable_cache | - | false | disable cache if set value

Please use `setEtcdConfig` and `setCachePath` function instead of pass params. Will not support params in furture version.
