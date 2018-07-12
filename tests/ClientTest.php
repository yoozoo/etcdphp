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
        putenv("etcd_endpoints=127.0.0.1:2379");
        putenv("etcd_user=root:pwd");
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

    public function testGetNotExist()
    {
        $body = $this->client->get_key("/notexist");
        $this->assertEmpty($body);
    }
}
