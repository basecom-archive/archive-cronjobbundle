<?php

namespace sweikenb\Library\Socket\Exceptions;

class SocketException extends \Exception
{
    public static function cantFindCallbacksFor($for)
    {
        return new self(\sprintf("Can't find callback for %s", $for));
    }

    public static function cantBind($addr, $port)
    {
        return new self(\sprintf("Can't bind to '%s:%s'", $addr, $port));
    }

    public static function cantConnect($addr, $port)
    {
        return new self(\sprintf("Can't connect to '%s:%s'", $addr, $port));
    }

    public static function acceptError($errNo, $errMsg)
    {
        return new self(\sprintf("Socket-accept error code %s: %s", $errNo, $errMsg));
    }

    public static function invalidCallbackFor($method)
    {
        return new self(\sprintf("Invalid callback for method '%s' given", $method));
    }

    public static function invalidSocketGiven()
    {
        return new self("The given socket is invalid or not an resource!");
    }

    public static function cantCreateUniqueId()
    {
        return new self("Can't create unique socket ID");
    }
}