# etcdphp
Combine Etcd-client and temp file to build a kv store. Inpired by [etcd-php](https://github.com/ouqiang/etcd-php).

# Config
This package read config from constructor param or env.

Priority : set function > env > protoagent > default value.

name        | function | param name | env name | proto agent | default value | decription
----        |--- | --- | --- | --- | --- | ---
cache path  |setCachePath | cache_path | - | - | /tmp/confcache | Path of generated php tmp file
etcd endpoints | setEtcdConfig|  etcd_endpoints | etcd_endpoints | endpoints | 127.0.0.1:2379 | end points of etcd server
etcd user | setEtcdConfig| etcd_user | etcd_user | user, password | root:root | username:password
etcd envkey | - | etcd_envKey | - | default | indicate current env (for furture use)
disable cache flag | - | etcd_disable_cache | - | false | disable cache if set value
read from local flag |setReadFromLocalFlag | - | - | - | false | read value from local json file if set value
local file path |setLocalFilePath | - | - | - | - | filepath of local json file

Please use `setEtcdConfig` and `setCachePath` function instead of pass params. Will not support params in furture version.

# local Json File
>## Example
>proto file
>```protobuf
>syntax = "proto3";
>
>import "protoconf_common.proto";
>
>package com.yoozoo.protoconf;
>
>option (app_name)="红岸-test";
>
>message Configuration1 {
>    int32 id =2 [(watch) = true, (default)="23"];
>    float abc=3;
>    bool def=4;
>}
>
>message Configuration {
>    string name =1 [ (watch) = true, (default)="123"];
>    Configuration1 msg=2;
>}
>
>
>```
>json file
>```json
>{
>    "name":"test",
>    "msg":{
>        "id":2,
>        "abc":"abc",
>        "def":true
>    }
>}
>```
