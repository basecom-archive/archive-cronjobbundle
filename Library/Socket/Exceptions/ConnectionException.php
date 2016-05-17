<?php

namespace sweikenb\Library\Socket\Exceptions;


class ConnectionException extends \Exception
{
    /**
     * @param string $error
     * @return ConnectionException
     */
    public static function socketError($error)
    {
        return new self(\sprintf("Can't create socket: %s", $error));
    }

    /**
     * @return ConnectionException
     */
    public static function notConnected()
    {
        return new self("Not connected. Please call 'connect()' before you start interacting with the server.");
    }

    /**
     * @return ConnectionException
     */
    public static function writeError()
    {
        return new self("Can't wirte to socket.");
    }

    /**
     * @return ConnectionException
     */
    public static function readError()
    {
        return new self("Can't read from socket.");
    }
}