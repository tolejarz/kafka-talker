<?php
namespace KafkaTalker\Request;

use KafkaTalker\Packer;

class OffsetRequest extends AbstractRequest
{
    const API_KEY = 2;

    public function send($replicaId = -1, $topics)
    {
        // Add header (ApiKey, ApiVersion, CorrelationId, ClientId)
        $data = $this->buildHeader();

        // Add ReplicaId
        $data .= Packer::packSignedInt32($replicaId);

        // Add Topic count
        $data .= Packer::packSignedInt32(count($topics));

        // Add Topics
        foreach ($topics as $topic => $partitions) {
            // Add Topic
            $data .= Packer::packStringSignedInt16($topic);

            // Add Partition count
            $data .= Packer::packSignedInt32(count($partitions));

            // Add Partitions
            foreach ($partitions as $partition => $partitionParams) {
                // Add Partition
                $data .= Packer::packSignedInt32($partition);

                // Add Time
                $data .= Packer::packSignedInt64($partitionParams['Time']);

                // Add MaxNumberOfOffsets
                $data .= Packer::packSignedInt32($partitionParams['MaxNumberOfOffsets']);
            }
        }

        // Concat data length (32 bits) and data
        $data = Packer::packStringSignedInt32($data);

        // Send data
        return $this->client->write($data);
    }

    public function receive()
    {
        // Read response length
        $responseLength = $this->client->read(4);
        $responseLength = Packer::unpackSignedInt32($responseLength);
        if ($this->debug) {
            printf("Response length: %s\n", var_export($responseLength, true));
        }

        // Read response
        $response = $this->client->read($responseLength);
        if ($this->debug) {
            printf("Response (packed): %s\n", var_export($response, true));
        }

        $cursor = 0;

        // Read CorrelationId
        $correlationId = Packer::unpackSignedInt32(substr($response, $cursor, 4));
        if ($this->debug) {
            printf("> CorrelationId: %s\n", var_export($correlationId, true));
        }
        $cursor += 4;

        // Read Topic count
        $topicCount = Packer::packSignedInt32(substr($response, $cursor, 4));
        if ($this->debug) {
            printf("> Topic count: %s\n", var_export($topicCount, true));
        }
        $cursor += 4;

        // Read Topics
        $topics = [];
        for ($i = 1; $i <= $topicCount; $i++) {
            if ($this->debug) {
                printf("    > [Topic #%d]\n", $i);
            }

            // Read Topic length
            $topicLength = Packer::unpackSignedInt16(substr($response, $cursor, 2));
            if ($this->debug) {
                printf("        > Topic length: %s\n", var_export($topicLength, true));
            }
            $cursor += 2;

            // Read Topic
            $topic = substr($response, $cursor, $topicLength);
            if ($this->debug) {
                printf("        > Topic: %s\n", var_export($topic, true));
            }
            $cursor += $topicLength;

            // Read Partition count
            $partitionCount = Packer::unpackSignedInt32(substr($response, $cursor, 4));
            if ($this->debug) {
                printf("        > Partition count: %s\n", var_export($partitionCount, true));
            }
            $cursor += 4;

            // Read partitions
            $partitions = [];
            for ($j = 1; $j <= $partitionCount; $j++) {
                if ($this->debug) {
                    printf("            > [Partition #%d]\n", $j);
                }

                // Read Partition
                $partitionId = Packer::unpackSignedInt32(substr($response, $cursor, 4));
                if ($this->debug) {
                    printf("                > PartitionId: %s\n", var_export($partition, true));
                }
                $cursor += 4;

                // Read ErrorCode
                $errorCode = Packer::unpackSignedInt16(substr($response, $cursor, 2));
                if ($this->debug) {
                    printf("                > ErrorCode: %s\n", var_export($errorCode, true));
                }
                $cursor += 2;

                // Read Offset count
                $offsetCount = Packer::unpackSignedInt32(substr($response, $cursor, 4));
                if ($this->debug) {
                    printf("                > Offset count: %s\n", var_export($offsetCount, true));
                }
                $cursor += 4;

                // Read offsets
                $offsets = [];
                for ($k = 1; $k <= $offsetCount; $k++) {
                    if ($this->debug) {
                        printf("                    > [Offset #%d]\n", $k);
                    }

                    $offset = Packer::unpackSignedInt64(substr($response, $cursor, 8));
                    if ($this->debug) {
                        printf("                        > Offset: %s\n", var_export($offset, true));
                    }
                    $cursor += 8;

                    $offsets[] = $offset;
                }

                $partitions[] = [
                    'PartitionId'   => $partitionId,
                    'ErrorCode'     => $errorCode,
                    'Offsets'       => $offsets,
                ];
            }

            $topics[] = [
                'Topic'         => $topic,
                'Partitions'    => $partitions,
            ];
        }

        return [
            'CorrelationId' => $correlationId,
            'Topics'        => $topics,
        ];
    }
}