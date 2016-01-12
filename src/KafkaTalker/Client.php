<?php
namespace KafkaTalker;

use KafkaTalker\Exception\KafkaTalkerException;

class Client
{
    const MAX_RECONNECT = 5;
    const MAX_WRITE_SIZE = 8192;

    private $apiVersion = 0;
    private $debug = false;
    private $kafkaVersion = null;
    private $socket;

    public function __construct($host, $port, $options = [])
    {
        $this->debug = !empty($options['debug']);

        if (empty($host)) {
            throw new KafkaTalkerException('Missing host');
        }
        if (empty($port)) {
            throw new KafkaTalkerException('Missing port');
        }

        $this->kafkaVersion = !empty($options['kafka_version']) ? $options['kafka_version'] : null;
        $this->apiVersion = 0;
        if ($this->kafkaVersion) {
            if (version_compare($this->kafkaVersion, '0.8.3', '>=')) {
                $this->apiVersion = 2;
            } elseif (version_compare($this->kafkaVersion, '0.8.2', '>=')) {
                $this->apiVersion = 1;
            }
        }

        $this->socket = fsockopen($host, $port, $errno, $errstr, 6000);

        if ($this->socket === false) {
            // Error
        }

        stream_set_blocking($this->socket, 0);
    }

    public function close()
    {
        if ($this->debug) {
            printf("[Client::close()] Closing socket handler...\n");
        }

        $close = fclose($this->socket);

        if ($this->debug) {
            printf("[Client::close()]     > fclose returned: %s\n", var_export($close, true));
        }

        return $close;
    }

    public function getApiVersion()
    {
        return $this->apiVersion;
    }

    public function getDebug()
    {
        return $this->debug;
    }

    public function getKafkaVersion()
    {
        return $this->kafkaVersion;
    }

    public function read($length)
    {
        $read = [$this->socket];
        $readable = stream_select($read, $null, $null, 3000, 3000);

        if ($this->debug) {
            printf("[Client::read()] Reading %d bytes from socket...\n", var_export($length, true));
        }

        $retry = 0;

        $data = '';
        while ($length) {
            $buffer = fread($this->socket, $length);
            if ($buffer === false) {
                // Error
            } elseif ($buffer) {
                $data .= $buffer;
                $length -= strlen($buffer);
            }
        }

        if ($this->debug) {
            printf("[Client::read()]    > fread returned: %s\n", var_export($data, true));
        }

        return $data;
    }

    public function setApiVersion($apiVersion)
    {
        $this->apiVersion = (int) $apiVersion;

        return $this;
    }

    public function setKafkaVersion($kafkaVersion)
    {
        $this->kafkaVersion = $kafkaVersion;

        return $this;
    }

    public function setDebug($debug)
    {
        $this->debug = (bool) $debug;

        return $this;
    }

    public function write($data)
    {
        if ($this->debug) {
            printf("[Client::write()] Sending data into socket...\n");
        }

        $write = [$this->socket];
        $dataSize = strlen($data);
        $written = 0;

        while ($written < $dataSize) {
            $writable = stream_select($null, $write, $null, 3000, 3000);
            if ($writable === false) {
                // Stream is not writable
            } elseif ($writable >= 0) {
                $w = fwrite($this->socket, substr($data, $written), self::MAX_WRITE_SIZE);
                if ($w === false) {
                    // Write error
                }
                $written += $w;
            } else {
                // No stream changed
            }
        }

        if ($this->debug) {
            printf("[Client::write()]     > %d on %d sent bytes\n", $written, strlen($data));
        }

        return $written;
    }

}