<?php

namespace sweikenb\Library\Threading;

class Deamon extends Thread
{
    /**
     * Gets the instance for the given optional $pid
     *
     * @param int|null $pid
     * @return \sweikenb\Library\Threading\Deamon
     */
    public static function getInstance($pid = null)
    {
        if(!isset(self::$instances["pid:$pid"])) {
            self::$instances["pid:$pid"] = new Deamon($pid);
        }
        return self::$instances["pid:$pid"];
    }

    /**
     * Gets the instance for the given $pidfile
     *
     * @param string $pidfile	Pidfile to use
     * @return \sweikenb\Library\Threading\Deamon
     */
    public static function getInstanceByPidFile($pidfile)
    {
        if(!isset(self::$instances["file:$pidfile"])) {
            self::$instances["file:$pidfile"] = new Deamon($pidfile, true);
        }
        return self::$instances["file:$pidfile"];
    }

    /**
     * Executes the thread untill $callback returns 'false'
     *
     * @param $callback
     * @param array $callArguments
     */
    protected function runThread($callback, array $callArguments)
	{
        // start an endless loop
		while(true)
		{
            // execute callback and check response
            if(\call_user_func_array($callback, $callArguments) === false) {
                return;
            }

            // prevent system dead-lock
			\usleep(1000);
		}
	}
}
