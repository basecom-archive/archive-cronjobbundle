<?php

namespace basecom\CronjobBundle\Command;

use basecom\WrapperBundle\ContainerAware\ContainerAwareCommand;
use sweikenb\Library\Threading\Thread;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;

abstract class CronjobCommand extends ContainerAwareCommand
{
	const THREAD_CHILD  = 'child';
	const THREAD_PARENT = 'parent';
	const THREAD_ERROR  = 'error';

	/**
	 * Debugging enabled?
	 * @var boolean
	 */
	protected $debugEnabled;

	/**
	 * Output interface to use
	 * @var \Symfony\Component\Console\Output\OutputInterface
	 */
	protected $debugOutput;

	/**
	 * Maximum runtime in seconds
	 * @var integer
	 */
	protected $runtimeMax;

	/**
	 * Start-timestamp of the script
	 * @var integer
	 */
	protected $runtimeStart;

	/**
	 * Time to sleep between the loops in micro seconds.
	 * A micro second is one millionth of a second.
	 * @var integer
	 */
	protected $sleeptime = 1000000; // 1 second default

	/**
	 * Groups loops until a sleep is required in micro seconds.
	 * A micro second is one millionth of a second.
	 * @var float
	 */
	protected $groupingTime = 0.000001; // -> one loop per second

	/**
	 * Amount of instances to use (0/1 = no threading, single process)
	 * @var integer
	 */
	protected $threads = 1;

	/**
	 * Contains the thread process ids if threadding is enabled
	 * @var Array
	 */
	protected $threadPids = array();

	/**
	 * Contains the thread signals
	 * @var Array
	 */
	protected $threadSignalQue = array();

	/**
	 * Flag to define that i am a thread child
	 * @var boolean
	 */
	protected $threadChild = false; // don't overwrite this!
	
	/**
	 * @{inheritdoc}
	 */
	protected function configure()
    {
        $this->addOption('runtime',  't', InputOption::VALUE_OPTIONAL, 'Defines the maximum execution time in seconds (default: 50).', 50)
			 ->addOption('maxloops', 'l', InputOption::VALUE_OPTIONAL, 'Defines the limit how often the cronjob can be executed within one process (default: no limit).', 0)
			 ->addOption('threads',  'x', InputOption::VALUE_OPTIONAL, 'Defines how many threads should be spawned (default: no threads).', 0);
    }

	/**
	 * @{inheritdoc}
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
    {
		// debugging active?
		$this->debugEnabled = !(bool)$input->getOption('no-debug');
		$this->debugInit($output);

		// set runtime
		$this->runtimeStart   = \time();
		$this->runtimeMax     = (int)$input->getOption('runtime');
		if($this->runtimeMax  < 1) {
			$this->runtimeMax = -1;
		}

		// get thread-count
		$this->threads = \max(0, (int)$input->getOption('threads'));
		
		// get configuration
		$maxCalls = \max(0, (int)$input->getOption('maxloops'));

		// output settings
		$this->debug('== Settings ==');
		if($this->debugEnabled)
        {
			$settings = $input->getOptions();
			foreach($settings as $option => $value)
			{
				if('' !== (string)$value) {
					$this->debug(sprintf('--%s=%s', $option, $value));
				}
			}
		}
		$this->debug("\n");

		// threadding?
		if($this->threads > 1)
		{
            // debug
            $output->writeln(\sprintf("Threading triggerd. Starting to spawn <comment>%s</comment> threads ...", $this->threads));

            // create requested threads
			$childs = array();
            for($i = 0; $i < $this->threads; $i++)
            {
                // spawn new thread
                $pid = Thread::getInstance()->spawn(array($this, 'executeSingleInstance'), array($input, $output, $maxCalls))->getPid();

                // register pid
                $childs[$pid] = 1;

                // debug
                $output->writeln(\sprintf("--> Cronjob thread spawned: <info>%s</info>", $pid));
            }

            // add an 10% buffer to wait for all childs
            if($this->runtimeMax > 0) {
                $this->runtimeMax += \ceil(($this->runtimeMax / 100) * 110);
            }

            // wait for childs to exit
            $output->writeln("Start waiting for childs to finish ...");
            while(\count($childs) > 0 && $this->checkRuntime(-1))
            {
                $pid = (int)\pcntl_wait($status, \WNOHANG);
                if($pid > 0) {
                    unset($childs[$pid]);
                    $output->writeln(\sprintf("--> Thread <info>%s</info> finished", $pid));
                }
                else {
                    \usleep(500);
                }
            }

            // return status based on the child count
            return (\count($childs) === 0 ? 0 : 1);
		}
        else
        {
            // execute an single instance and return its status
            return $this->executeSingleInstance(null, $input, $output, $maxCalls);
        }
    }

    /**
     * @param Thread $thread
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function onThreadSpawned(Thread $thread, InputInterface $input, OutputInterface $output)
    {
        // refresh database connections / resources at this point ...
    }

    /**
     * @param Thread $thread
     * @param InputInterface $input
     * @param OutputInterface $output
     */
    protected function onThreadTerminating(Thread $thread, InputInterface $input, OutputInterface $output)
    {
        // close database connections / resources at this point ...
    }

    /**
     * @param Thread $thread
     * @param InputInterface $input
     * @param OutputInterface $output
     * @param int $maxCalls
     * @return int
     */
    public function executeSingleInstance(Thread $thread = null, InputInterface $input, OutputInterface $output, $maxCalls)
    {
        // threaded call?
        if(null !== $thread) {
            $this->onThreadSpawned($thread, $input, $output);
        }

        // run the loop
        $loops = 1;
        $tmpPreloopResultData = null;
        $microtime = microtime(true);
        do
        {
            // info
            $this->debug("--[Loop $loops]--");

            // run cronjob
            $result = $this->executeCronjob($input, $output, $loops, $tmpPreloopResultData);
            $this->debug("\n");

            // execute more loops?
            $blnMoreLoops = (false !== $result && $this->checkRuntime($loops) && (0 === $maxCalls || ($loops + 1) <= $maxCalls));
            if($blnMoreLoops && ((microtime(true) - $microtime) > $this->groupingTime))
            {
                // result-data for the next loop available?
                $tmpPreloopResultData = is_bool($result) ? null : $result;

                // sleep to avoid deadlocks
                usleep($this->sleeptime);

                // set new compare-microtime
                $microtime = microtime(true);

                // increment loop
                $loops++;
            }
        }
        while($blnMoreLoops);

        // finish
        $this->debug("cronjob stopped successfully after ".$loops." loops\n");

        // threaded call?
        if(null !== $thread)
        {
            // trigger event callback
            $this->onThreadTerminating($thread, $input, $output);

            // if this is an threaded run, we have to terminate the call
            $thread->terminate();
        }

        // return exit status
        return 0;
    }

	/**
	 * Executes the cronjob
	 *
	 * @param \Symfony\Component\Console\Input\InputInterface $input
	 * @param \Symfony\Component\Console\Output\OutputInterface $output
	 * @param Integer $loopcount		Numer of the current loop
	 * @param Mixed $preloopResult		Returned result of the last executes loop (optional, NOT reliable!)
	 * @throws \Exception
	 */
	protected function executeCronjob(InputInterface $input, OutputInterface $output, $loopcount, $preloopResult = null)
	{
		throw new \Exception('You have to overwrite this method with your own logic');
	}

	/**
	 * Prepares the debug-output
	 *
	 * @param \Symfony\Component\Console\Output\OutputInterface $output
	 */
	protected function debugInit(OutputInterface $output)
	{
		// set output-stream for debugging
		$this->debugOutput = $output;

		// clear screen if debugging is enabled
		if($this->debugEnabled) {
			@passthru('clear');
		}
	}

	/**
	 * prints the given data to the console
	 *
	 * @param mixed $data	debug data
	 */
	protected function debug($data)
	{
		if($this->debugEnabled)
		{
			// need more informations?
			if(is_object($data) || is_array($data) || is_null($data) || is_bool($data)) {

				// add a linebreak
				$this->debugOutput->writeln('');

				// dump
				var_dump($data);

				// add a linebreak
				$this->debugOutput->writeln('');
			}
			else {

				// write debug
				$this->debugOutput->writeln($data);
			}
		}
	}

    /**
     * Checks the runtime for this process
     *
     * @param int $loops
     * @return boolean
     */
	protected function checkRuntime($loops)
	{
        // infinit time?
        if(-1 === $this->runtimeMax) {
            return true;
        }

		// get the current runtime
		$currentRuntime = (\time() - $this->runtimeStart);

        // ignore loop count?
        if(-1 === $loops) {
            return ($currentRuntime < $this->runtimeMax);
        }

		// enough time for the next loop?
        $avgRuntimePerLoop = \ceil($currentRuntime / $loops);
		if((($currentRuntime + $avgRuntimePerLoop) <= $this->runtimeMax)) {
			// ok, go on
			return true;
		}

		// limit reached, stop
		return false;
	}

	/**
	 * Creates a new thread of this script instance
	 * 
	 * @return string	self::THREAD_*-status
	 */
	protected function createThread()
	{
		$childPid = pcntl_fork();
		if(-1 === $childPid)
		{
			// error
			return self::THREAD_ERROR;
		}
		else if($childPid)
		{
			// save child pid and status-pointer
			$this->threadPids[$childPid] = 1;

			// In the event that a signal for this pid was caught before we get here, it will be in our signalQueue array
            // So let's go ahead and process it now as if we'd just received the signal
            if(isset($this->threadSignalQue[$childPid])) {
                $this->childSignalHandler(\SIGCHLD, $childPid, $this->threadSignalQue[$childPid]);
                unset($this->threadSignalQue[$childPid]);
            }

			// we are the parent
			return self::THREAD_PARENT;
		}
		else
		{
			// disable debug mode
			$this->debugEnabled = false;

			// mark this instance as child
			$this->threadChild = true;

			//prevent output to main process
			ob_start();

			//to kill self before exit();, or else the resource shared with parent will be closed 
            register_shutdown_function(create_function('$pars', 'ob_end_clean();posix_kill(getmypid(), SIGKILL);'), array());

			// we are the child
			return self::THREAD_CHILD;
		}
	}

	/**
	 * Watches the threads and prints some informations about them
	 */
	protected function watchThreads()
	{
		// print some debug informations
		$this->debug("\nSpawned threads: ".count($this->threadPids)."\n");

		// wait until all threads are done
		while(!empty($this->threadPids)) {
			$pid = pcntl_wait($status);
			$this->threadSignalHandler(0, $pid, $status);
		}
	}

	/**
	 * Signal handler for threads
	 * 
	 * @param integer $signo
	 * @param integer $pid
	 * @param integer $status
	 * @return boolean
	 */
	protected function threadSignalHandler($signo, $pid=null, $status=null)
	{
		// if no pid is provided, that means we're getting the signal from the system.
        // let's figure out which child process ended.
        if(!$pid) {
            $pid = pcntl_waitpid(-1, $status, \WNOHANG);
        }

		// make sure we get all of the exited children
        while($pid > 0)
		{
            if(isset($this->threadPids[$pid])) {
                $exitCode = pcntl_wexitstatus($status);
                $this->debug("pid $pid exited with status $exitCode");
                unset($this->threadPids[$pid]);
            }
            else {
                $this->threadSignalQue[$pid] = $status;
            }
            $pid = pcntl_waitpid(-1, $status, \WNOHANG);
        }
        return true;
	}

	/**
	 * Returns true if we are in a child-thread
	 * 
	 * @return boolean
	 */
	protected function isChildThread()
	{
		return (true === $this->threadChild);
	}
}
