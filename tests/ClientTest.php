<?php
/**
 * @author chenfang<crossfire1103@gmail.com>
 */

namespace Youzusg\Tests;

use Youzusg\Client;

class ClientTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var \Youzusg\Client;
     */
    protected $client;

    public function setUp()
    {
        $this->client = new Client();
        putenv("ETCD_NODE=127.0.0.1:2379");
        putenv("ETCD_USERNAME=root");
        putenv("ETCD_PASSWORD=651225");
    }

    public function testGetKey()
    {
        $body = $this->client->get_key("/name");
        $this->assertNotEmpty($body);
    }

    public function testGetLongKey()
    {
        $body = $this->client->get_key("/config/name");
        $this->assertNotEmpty($body);
    }
}
