<?php
namespace KafkaTalker\Tests\Request;

use KafkaTalker\Client;
use KafkaTalker\Request\ListGroupsRequest;
use KafkaTalker\Tests\KafkaTalkerTest;

class ListGroupsRequestTest extends KafkaTalkerTest
{
    public function testOne()
    {
        $client = new Client($this->host, $this->port, ['debug' => $this->debug, 'kafka_version' => '0.8.2.2']);

        $correlationId = mt_rand(-32768, 32767);

        $listGroupsRequest = new ListGroupsRequest($client, ['debug' => $this->debug]);
        $listGroupsRequest->setCorrelationId($correlationId);
        $listGroupsRequest->send();
        $response = $listGroupsRequest->receive();

        $this->assertInternalType('array', $response);
        $this->assertArrayHasKey('CorrelationId', $response);
        $this->assertSame($correlationId, $response['CorrelationId']);
    }
}