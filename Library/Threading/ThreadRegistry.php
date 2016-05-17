<?php

namespace sweikenb\Library\Threading;

class ThreadRegistry
{
    /**
     * Keeps amount of available parallel threads
     *
     * @var int
     */
    private $maxThreads;

    /**
     * Keeps the registred threads
     *
     * @var array
     */
    protected $registry;

    /**
     * Registry setup
     *
     * @param int $maxThreads Amounts lower than one removes disable the limit
     */
    public function __construct($maxThreads = -1)
    {
        $this->maxThreads = (int)$maxThreads;
        $this->registry = array();
    }

    /**
     * @param int $maxThreads
     */
    public function setMaxThreads($maxThreads)
    {
        $this->maxThreads = (int)$maxThreads;
    }

    /**
     * @return int
     */
    public function getMaxThreads()
    {
        return $this->maxThreads;
    }

    /**
     * @param int $pid
     * @return null|Thread
     */
    public function getThreadByPid($pid)
    {
        if(isset($this->registry[(int)$pid])) {
            return $this->registry[(int)$pid];
        }
        return null;
    }

    /**
     * @param Thread $thread
     * @return $this
     */
    public function registerThread(Thread $thread)
    {
        $this->registry[(int)$thread->getPid()] = $thread;
        return $this;
    }

    /**
     * @param Thread $thread
     * @return $this
     */
    public function unregisterThread(Thread $thread)
    {
        if(isset($this->registry[(int)$thread->getPid()])) {
            unset($this->registry[(int)$thread->getPid()]);
        }
        return $this;
    }

    /**
     * @return bool
     */
    public function isLimitReached()
    {
        if(1 > $this->getMaxThreads()) {
            return false;
        }
        return $this->count() >= $this->getMaxThreads();
    }

    /**
     * @return int
     */
    public function count()
    {
        return \count($this->registry);
    }

    /**
     * Waits for childs to exit and update the exit status of the thread instance.
     * The default timeout for this action is set to ten seconds.
     * After the timeout is reached you can process custom actions and or re-start the wait process untill all threads are finished.
     *
     * @param float $timeout
     * @return array All finished pids for this call (only registred ones; array might be empty if no child exits)
     */
    public function waitForChildsToExit($timeout = 10.0)
    {
        // normalize timeout
        $timeout = (float)$timeout;

        $finished = array();
        $starttimeWait = \microtime(true);
        while($this->count() > 0 && ((\microtime(true) - $starttimeWait) < $timeout))
        {
            // wait for ne next child to finish
            $pid = (int)\pcntl_waitpid(0, $status, \WNOHANG);
            if($pid > 0)
            {
                // get the thread instance
                $thread = $this->getThreadByPid($pid);
                if($thread)
                {
                    // keep the pid for feedback
                    $finished[] = $thread->getPid();

                    // update status
                    $thread->setExitStatus($status);

                    // unregister thread
                    $this->unregisterThread($thread);
                }
            }

            // sleep some time to unblock the system
            \usleep(50);
        }

        // return the finished pids
        return $finished;
    }
}