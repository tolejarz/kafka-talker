<?php
namespace KafkaTalker\Tests\Request;

use KafkaTalker\Client;
use KafkaTalker\Request\OffsetFetchRequest;
use KafkaTalker\Tests\KafkaTalkerTest;

class OffsetFetchRequestTest extends KafkaTalkerTest
{
    public function testReceive()
    {
        $client = new Client($this->host, $this->port);
        $client->setKafkaVersion('0.8.2.2');
        $client->connect();

        $correlationId = mt_rand(-32768, 32767);

        $offsetFetchRequest = new OffsetFetchRequest($client);
        $offsetFetchRequest->setCorrelationId($correlationId);
        $offsetFetchRequest->send('toto', ['test1' => [0]]);
        $response = $offsetFetchRequest->receive();

        $this->assertInternalType('array', $response);
        $this->assertArrayHasKey('CorrelationId', $response);
        $this->assertSame($correlationId, $response['CorrelationId']);
    }
}
