<?php

namespace sweikenb\Library\Socket;

use sweikenb\Library\Socket\Exceptions\SocketException;

class Socket
{
    /**
     * @var string
     */
    protected $id;

    /**
     * @var resource
     */
    protected $socket;

    /**
     * @param resource $socket The socket to wrap
     * @throws Exceptions\SocketException
     */
    public function __construct($socket)
    {
        // check for proper resource
        if(!\is_resource($socket)) {
            throw SocketException::invalidSocketGiven();
        }
        $this->socket = $socket;

        // create socket id
        if(true === @\socket_getpeername($this->socket, $addr, $port)) {
            $this->id = ("$addr:$port");
        }
        else {
            $this->id = 'unknown';
        }
    }

    /**
     * @return resource
     */
    public function getSocket()
    {
        return $this->socket;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @return bool
     */
    public function hasValidSocket()
    {
        return \is_resource($this->getSocket());
    }

    /**
     * @param string $addr
     * @param int $port
     * @return $this
     * @throws Exceptions\SocketException
     */
    public function bind($addr, $port)
    {
        if(!\socket_bind($this->getSocket(), $addr, $port)) {
            throw SocketException::cantBind($addr, $port);
        }
        return $this;
    }

    /**
     * @param string $addr
     * @param int $port
     * @return $this
     * @throws Exceptions\SocketException
     */
    public function connect($addr, $port)
    {
        if(!\socket_connect($this->getSocket(), $addr, $port)) {
            throw SocketException::cantConnect($addr, $port);
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function listen()
    {
        return \socket_listen($this->getSocket());
    }

    /**
     * @return bool
     */
    public function setBlocking()
    {
        return \socket_set_block($this->getSocket());
    }

    /**
     * @return bool
     */
    public function setNonBlocking()
    {
        return \socket_set_nonblock($this->getSocket());
    }

    /**
     * @return bool|Socket
     */
    public function accept()
    {
        $client = @\socket_accept($this->getSocket());
        if(false === $client) {
            return false;
        }
        return new Socket($client);
    }

    /**
     * @param int $readBytes [default:4098]
     * @param int $mode [default:PHP_NORMAL_READ]
     * @return string|bool
     */
    public function read($readBytes = 4098, $mode = \PHP_NORMAL_READ)
    {
        return \socket_read($this->getSocket(), $readBytes, $mode);
    }

    /**
     * @param string $data
     * @return int
     */
    public function write($data)
    {
        return \socket_write($this->getSocket(), (string)$data, \strlen($data));
    }

    /**
     * @return $this
     */
    public function close()
    {
        if(\is_resource($this->getSocket())) {
            \socket_close($this->getSocket());
            $this->socket = null;
        }
        return $this;
    }
}