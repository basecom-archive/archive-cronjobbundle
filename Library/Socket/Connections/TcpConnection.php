<?php

namespace sweikenb\Library\Socket\Connections;

use sweikenb\Library\Socket\Exceptions\ConnectionException;
use sweikenb\Library\Socket\Socket;

class TcpConnection
{
    /**
     * @var string
     */
    private $host;

    /**
     * @var int
     */
    private $port;

    /**
     * @var null|Socket
     */
    private $socket;

    /**
     * @param string $host
     * @param int $port
     */
    public function __construct($host, $port)
    {
        $this->host = $host;
        $this->port = $port;
    }

    /**
     * @return Socket
     * @throws ConnectionException
     */
    private function getSocket()
    {
        // connected?
        if(null === $this->socket) {
            throw ConnectionException::notConnected();
        }

        // return socket
        return $this->socket;
    }

    /**
     * @return $this
     * @throws ConnectionException
     * @throws ConnectionException
     */
    public function connect()
    {
        if(null === $this->socket)
        {
            // create socket
            $socket = \socket_create(\AF_INET, \SOCK_STREAM, \SOL_TCP);
            if(!$socket) {
                throw ConnectionException::socketError(\socket_strerror(\socket_last_error()));
            }

            // create socket wrapper
            $socket = new Socket($socket);
            $socket->connect($this->host, $this->port);

            // keep the socket
            $this->socket = $socket;
        }
        return $this;
    }

    /**
     * @return $this
     */
    public function disconnect()
    {
        if(null !== $this->socket) {
            $this->socket->close();
            $this->socket = null;
        }
        return $this;
    }

    /**
     * @param string $data
     * @throws \sweikenb\Library\Socket\Exceptions\ConnectionException
     * @return int
     */
    public function write($data)
    {
        if(0 === $this->getSocket()->write((string)$data)) {
            throw ConnectionException::writeError();
        }
        return $this;
    }

    /**
     * @param int $readBytes
     * @param int $mode
     * @throws \sweikenb\Library\Socket\Exceptions\ConnectionException
     * @return string
     */
    public function read($readBytes = 4098, $mode = \PHP_NORMAL_READ)
    {
        $data = $this->getSocket()->read($readBytes, $mode);
        if(false === $data) {
            throw ConnectionException::readError();
        }
        return $data;
    }
}