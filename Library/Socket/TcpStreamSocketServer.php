<?php

namespace sweikenb\Library\Socket;

use sweikenb\Library\Socket\Exceptions\SocketException;

class TcpStreamSocketServer
{
    const BLOCKING_ON = true;
    const BLOCKING_OFF = false;


    /**
     * @var string
     */
    protected $address;

    /**
     * @var int
     */
    protected $port;

    /**
     * @var bool
     */
    protected $blocking;

    /**
     * @var Socket|null
     */
    protected $socket;

    /**
     * @var array
     */
    protected $onDataCallbacks;

    /**
     * @var array
     */
    protected $onErrorCallbacks;

    /**
     * @var array
     */
    protected $socketsConnected;

    /**
     * @param string $address
     * @param int $port
     * @param bool $blocking [default:false]
     */
    public function __construct($address, $port, $blocking = self::BLOCKING_OFF)
    {
        // save vars
        $this->address = $address;
        $this->port = $port;
        $this->blocking = $blocking;

        // initialize vars
        $this->onDataCallbacks = array();
        $this->onErrorCallbacks = array();
        $this->socketsConnected = array();
    }

    /**
     * @return bool
     */
    public function isBlocking()
    {
        return (self::BLOCKING_ON === $this->blocking);
    }

    /**
     * @param $callback
     * @throws
     * @return $this
     */
    public function onData($callback)
    {
        // check callback
        if(!\is_callable($callback)) {
            throw SocketException::invalidCallbackFor(__METHOD__);
        }

        // register
        $this->onDataCallbacks[] = $callback;
        return $this;
    }

    /**
     * @param $callback
     * @throws Exceptions\SocketException
     * @return $this
     */
    public function onError($callback)
    {
        // check callback
        if(!\is_callable($callback)) {
            throw SocketException::invalidCallbackFor(__METHOD__);
        }

        // register
        $this->onErrorCallbacks[] = $callback;
        return $this;
    }

    /**
     * @param int $maxLoops [default:-1]
     * @param int $usleep [default:100]
     * @throws Exceptions\SocketException
     * @throws \Exception|Exceptions\SocketException
     */
    public function start($maxLoops = -1, $usleep = 100)
    {
        try
        {
            // onData-callbacks given?
            if(empty($this->onDataCallbacks)) {
                throw SocketException::cantFindCallbacksFor('onData');
            }

            // need to count loops?
            $countLoops = (0 < $maxLoops);

            // start (endless) loop
            $loops = 0;
            while(false === $countLoops || $maxLoops < $loops)
            {
                // increment loop count if required
                if(true === $countLoops) {
                    ++$loops;
                }

                // accept incoming requests
                $server = $this->connect();
                $client = $server->accept();

                // connection available?
                if($client && $client instanceof Socket && !isset($this->socketsConnected[$client->getId()]))
                {
                    // register socket
                    $this->socketsConnected[$client->getId()] = true;

                    // dispatch data to registerd callbacks
                    $handled = false;
                    foreach($this->onDataCallbacks as $callback)
                    {
                        $handled = \call_user_func($callback, $server, $client);
                        if(true === $handled) {
                            // callback handled request?
                            break;
                        }
                    }

                    // need to close client connection?
                    if(true !== $handled) {
                        $client->close();
                    }
                }

                // sleep some time to prevent system lock
                \usleep((int)$usleep);

                // only keep the last 200 connections
                while(\count($this->socketsConnected) > 200) {
                    \array_shift($this->socketsConnected);
                }
            }

            // close connection
            $this->disconnect();
        }
        catch(SocketException $e)
        {
            // close connection
            $this->disconnect();

            // dispatch data to callbacks
            foreach($this->onErrorCallbacks as $callback)
            {
                \call_user_func($callback, $e);
            }

            // throw on
            throw $e;
        }
        catch(\Exception $e)
        {
            // close connection
            $this->disconnect();

            // throw on
            throw $e;
        }
    }

    /**
     * @return Socket
     */
    protected function connect()
    {
        // socket not yet connected?
        if(!$this->socket || !$this->socket->hasValidSocket())
        {
            // create new socket wrapper instance
            $this->socket = new Socket(\socket_create(\AF_INET, \SOCK_STREAM, \SOL_TCP));

            // bind and listen to socket
            $this->socket
                ->bind($this->address, $this->port)
                ->listen();

            // (non) blocking mode?
            if(true === $this->isBlocking()) {
                $this->socket->setBlocking();
            }
            else {
                $this->socket->setNonBlocking();
            }
        }

        // return socket wrapper
        return $this->socket;
    }

    /**
     * @return $this
     */
    protected function disconnect()
    {
        // socket set?
        if($this->socket) {
            $this->socket->close();
        }
        return $this;
    }
}