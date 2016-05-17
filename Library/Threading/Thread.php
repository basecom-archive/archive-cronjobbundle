<?php

declare(ticks = 1);
namespace sweikenb\Library\Threading;

class Thread
{
	/**
	 * Instance-types
	 * @var integer
	 */
	const TYPE_PARENT = 1;
	const TYPE_THREAD = 2;

	/**
	 * Singletion thread instances by pidfile
	 *
	 * @var array
	 */
	protected static $instances = array();

    /**
     * Enables the script to dispatch signals to its childs
     *
     * @var array
     */
    protected static $processGraph = array();

	/**
	 * Process ID of the current thread
	 *
	 * @var integer|null
	 */
	protected $pid = null;

	/**
	 * Storage-file for the process ID of the current thread
	 *
	 * @var string|false
	 */
	protected $pidfile = false;

	/**
	 * Type of the current instance
	 *
	 * @var integer		TYPE_PARENT or TYPE_THREAD
	 */
	protected $type = null;

	/**
	 * Registred shutdown callbacks
	 *
	 * @var array
	 */
	protected $shutdownCallbacks = array();

    /**
     * @var array
     */
    protected $onSpawnErrorEvents = array();

    /**
     * @var array
     */
    protected $onSpawnThreadEvents = array();

    /**
     * @var array
     */
    protected $onSpawnParentEvents = array();

    /**
     * Contains the exit status of the process (only available in the parent-thread while using the ThreadRegistry)
     *
     * @var null|int
     */
    protected $exitStatus = null;

    /**
     * Gets the instance for the given optional $pid
     *
     * @param int|null $pid
     * @return \sweikenb\Library\Threading\Thread
     */
    public static function getInstance($pid = null)
    {
        // pid given?
        if(null !== $pid)
        {
            // reuse-instance (singleton)
            if(!isset(self::$instances["pid:$pid"])) {
                self::$instances["pid:$pid"] = new Thread($pid);
            }

            // reuturn singletion instance
            return self::$instances["pid:$pid"];
        }

        // create blank instance
        return new Thread();
    }

	/**
	 * Gets the instance for the given $pidfile
	 *
	 * @param string $pidfile	Pidfile to use
	 * @return \sweikenb\Library\Threading\Thread
	 */
	public static function getInstanceByPidFile($pidfile)
	{
		if(!isset(self::$instances["file:$pidfile"])) {
			self::$instances["file:$pidfile"] = new Thread($pidfile, true);
		}
		return self::$instances["file:$pidfile"];
	}

    /**
     * Create singleton instance with the given $pidfile
     *
     * @param string|int|null $pid
     * @param bool $usePidfile [default:false]
     * @internal param string $pidfile Pidfile to use
     */
	protected function __construct($pid = null, $usePidfile = false)
	{
        if(true == $usePidfile) {
            $this->pid = null;
            $this->pidfile = $pid;
        }
        else {
            $this->pid = $pid;
            $this->pidfile = false;
        }
	}

    /**
     * Prevent cloning a single instance
     */
    final private function __clone() {}

    /**
     * Returns the current pid
     *
     * @param bool $silentOnError
     * @return int|null
     */
	public function getPid($silentOnError = false)
	{
		return $this->getThreadPid($silentOnError);
	}

	/**
	 * Returns the current pidfile
	 *
	 * @return string|false
	 */
	public function getPidfile()
	{
		return $this->pidfile;
	}

    /**
     * @param int|null $exitStatus
     */
    public function setExitStatus($exitStatus = null)
    {
        $this->exitStatus = $exitStatus;
    }

    /**
     * @return int|null
     */
    public function getExitStatus()
    {
        return $this->exitStatus;
    }

    /**
     * @param callable|array $callback
     * @return $this
     * @throws \Exception
     */
    public function onSpawnError($callback)
    {
        if(!\is_callable($callback)) {
            throw new \Exception("onSpawnError(): Invalid callback");
        }
        $this->onSpawnErrorEvents[] = $callback;
        return $this;
    }

    /**
     * @param $callback
     * @return $this
     * @throws \Exception
     */
    public function onSpawnTherad($callback)
    {
        if(!\is_callable($callback)) {
            throw new \Exception("onSpawnTherad(): Invalid callback");
        }
        $this->onSpawnThreadEvents[] = $callback;
        return $this;
    }

    /**
     * @param $callback
     * @return $this
     * @throws \Exception
     */
    public function onSpawnParent($callback)
    {
        if(!\is_callable($callback)) {
            throw new \Exception("onSpawnParent(): Invalid callback");
        }
        $this->onSpawnParentEvents[] = $callback;
        return $this;
    }

    /**
     * Spawn the thread with the gieven $callback
     *
     * @param $callback
     * @param array|null $callArguments
     * @param bool $force [default:true]
     * @return $this
     * @throws \Exception
     */
    public function spawn($callback, array $callArguments = null, $force = true)
	{
		// check if the given callable is valid
		if(!\is_callable($callback)) {
			throw new \Exception(sprintf("Can't spawn thread, 'callable/function' expected, '%s' given.", gettype($callback)));
		}

		// check pidfile
		if(false === $force && false !== $this->pidfile && @\file_exists($this->pidfile)) {
			throw new \Exception("Thread already running.");
		}

		// spawn thread
        $pid = pcntl_fork();
		if(-1 === $pid) {
			foreach($this->onSpawnErrorEvents as $eventCallback)
            {
                \call_user_func_array($eventCallback, array($this));
            }
            throw new \Exception("Can't fork thread process.");
		}
		else if($pid) {
			// we are the parent
			$this->markAsParent($pid);
			return $this;
		}
		else {

		    // WARNING: this is very important to prevent php-fpm to kill the main process too!
            @register_shutdown_function(create_function('$pars', 'posix_kill(getmypid(), SIGKILL);'), array());

			// we are the thread
			$this->markAsThread(\getmypid());

            // normalize arguments
            if(null === $callArguments) {
                $callArguments = array();
            }

            // add this isntance as the first argument
            \array_unshift($callArguments, $this);

			// trigger the callback
			$this->runThread($callback, $callArguments);

			// try to remove pidfile
            if(false !== $this->pidfile) {
                @\file_exists($this->pidfile) && @\unlink($this->pidfile);
            }

			// kill the thread after the callback finished
			exit;
		}
	}

    /**
     * Executes the Thread
     *
     * @param $callback
     * @param array $callArguments
     */
    protected function runThread($callback, array $callArguments)
	{
        \call_user_func_array($callback, $callArguments);
	}

	/**
	 * Returns the process-id of the current thread
	 *
	 * @param boolean $silentOnError	Don't throw exceptions?
	 * @return integer|null Process id
	 * @throws \Exception
	 */
	public function getThreadPid($silentOnError = false)
	{
		if(null === $this->pid && false !== $this->pidfile)
        {
			$this->pid = @\file_get_contents($this->pidfile);
			if(false === $this->pid) {
				if(true === $silentOnError) {
					return false;
				}
				throw new \Exception("Can't find thread process-id.");
			}
		}
		return $this->pid;
	}

    /**
     * Register the default signal handlers
     */
    protected function registerSignalHandler()
    {
        // register signal-handler
        \pcntl_signal(\SIGTERM, array($this, 'signalTerminate'));
        \pcntl_signal(\SIGINT, array($this, 'signalTerminate'));
    }

	/**
	 * Marks this instance as parent
	 *
	 * @param integer $pid	Process id of the thread
	 */
	protected function markAsParent($pid)
	{
		// set type and pid
		$this->type = self::TYPE_PARENT;
		$this->pid = $pid;

        // register childs of this pid
        $parentPid = \getmypid();
        if(!isset(self::$processGraph[$parentPid])) {
            self::$processGraph[$parentPid] = array();
        }
        self::$processGraph[$parentPid][] = $pid;

        // register
        self::$instances["pid:$pid"] = $this;

		// write pidfile?
        if(false !== $this->pidfile) {
            \file_put_contents($this->pidfile, $this->pid);
        }

        // register signal-handler
        $this->registerSignalHandler();

        // dispatch event
        foreach($this->onSpawnParentEvents as $eventCallback)
        {
            \call_user_func_array($eventCallback, array($this));
        }
	}

	/**
	 * Marks this instance as thread
	 *
	 * @param int $pid Process id of the thread
	 */
	protected function markAsThread($pid)
	{
		// set type and pid
		$this->type = self::TYPE_THREAD;
		$this->pid = $pid;

        // register
        self::$instances["pid:$pid"] = $this;

		// register signal-handler
        $this->registerSignalHandler();

        // dispatch events
        foreach($this->onSpawnThreadEvents as $eventCallback)
        {
            \call_user_func_array($eventCallback, array($this));
        }
	}

    /**
     * @param $signal [deprecated param]
     * @return int
     */
    public function signalWait($signal)
    {
        return \pcntl_waitpid(-1, $status);
    }

	/**
	 * Handles the given $sigal
	 *
	 * @param int $signal
	 */
	public function signalTerminate($signal)
	{
        // dispatch signal local
        $this->dispatchSignalEvent();

        // remove pid-file
        if(false !== $this->pidfile) {
            @\file_exists($this->pidfile) && @\unlink($this->pidfile);
        }

        // dispatch signal to childs
        if(self::TYPE_PARENT === $this->type)
        {
            $parentPid = \getmypid();
            if(isset(self::$processGraph[$parentPid]))
            {
                foreach(self::$processGraph[$parentPid] as $childPid)
                {
                    @\posix_kill($childPid, $signal);
                }
            }
        }

        // stop script
        exit;
	}

	/**
	 * Trigger registred signal events
	 */
	public function dispatchSignalEvent()
	{
		// trigger all callbacks
		foreach($this->shutdownCallbacks as $callback)
		{
            \call_user_func($callback, $this);
		}
	}

	/**
	 * Registres an $callback for the given $signal
	 *
	 * @param callable $callback		Callback to trigger
	 * @return \sweikenb\Library\Threading\Thread
	 */
	public function registerShutdownCallback($callback)
	{
		$this->shutdownCallbacks[] = $callback;

		// return this instance for chaining
		return $this;
	}

	/**
	 * Sends a $message to the std-out of PHP and optonally kills the thread (ignores registred signal-events!)
	 *
	 * @param string $message	Message to send
	 * @param bool $stop
	 */
	protected function threadMessage($message, $stop = false)
	{
		if(self::TYPE_THREAD !== $this->type) {
			return;
		}

		// print message
		echo \sprintf("\n\n[THREAD:%s] %s.\n\n", $this->getThreadPid(), $message);

		// need to kill the thread?
		$stop && $this->kill();
	}

    /**
     * Sends the terminate-signal to the thread
     *
     * @param boolean $silentOnError    Don't throw exceptions?
     * @param int $timeout
     */
	public function terminate($silentOnError = false, $timeout = 10)
	{
		$this->sendShutdownSignal(\SIGTERM, $silentOnError, \max(1, (int)$timeout));
	}

    /**
     * Sends the kill-signal to the thread
     *
     * @param boolean $silentOnError    Don't throw exceptions?
     * @param int $timeout
     */
	public function kill($silentOnError = false, $timeout = 10)
	{
		$this->sendShutdownSignal(\SIGKILL, $silentOnError, \max(1, (int)$timeout));
	}

    /**
     * Sends a 'shutdown'-signal to the thread
     *
     * @param integer $signal            Signal to send
     * @param boolean $silentOnError    Don't throw exceptions?
     * @param int $timeout
     * @throws \Exception
     */
	protected function sendShutdownSignal($signal, $silentOnError = false, $timeout = 10)
	{
		// if we are the thread, exit the script
		if(self::TYPE_THREAD === $this->type)
		{
            // dispatch signal
            $this->signalTerminate($signal);
		}

		// if we are not the thread, send the kill-signal
		else
		{
			// get pid
			$pid = $this->getThreadPid($silentOnError);

			// send KILL-signal
			if(false !== $pid)
			{
				@\posix_kill($pid, $signal);
                if(\SIGKILL !== $signal && $timeout > 0)
                {
                    $start = \time();
                    do
                    {
                        // get status
                        $output = null;
                        @\exec("ps -p $pid", $output);

                        // check status
                        $stillRunning = (1 !== count($output));
                        if($stillRunning)
                        {
                            // check time
                            if((\time() - $start) >= $timeout) {
                                throw new \Exception("Signal timed out. Try again!");
                            }

                            // sleep a few milliseconds
                            \usleep(10000);
                        }
                    }
                    while($stillRunning);
                }
			}
			$this->type = null;

			// delete pidfile
            if(false !== $this->pidfile) {
                @\file_exists($this->pidfile) && @\unlink($this->pidfile);
            }
		}
	}
}
