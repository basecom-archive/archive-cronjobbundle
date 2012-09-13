<?php

namespace basecom\CronjobBundle\Command;

use Symfony\Bundle\FrameworkBundle\Command\ContainerAwareCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Input\InputArgument;

abstract class CronjobCommand extends ContainerAwareCommand
{
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
	 * @{inheritdoc}
	 */
	protected function configure()
    {
        $this->addOption('runtime',  't', InputOption::VALUE_OPTIONAL, 'Defines the maximum execution time in seconds (default: 50).', 50)
			 ->addOption('maxloops', 'l', InputOption::VALUE_OPTIONAL, 'Defines the limit how often the cronjob can be executed within one process (default: no limit).', 0);
    }

	/**
	 * Container shortcut
	 * 
	 * @param string $id
	 * @return mixed
	 */
	protected function get($id)
	{
		return $this->getContainer()->get($id);
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

		// get configuration
		$maxCalls = max(0, (int)$input->getOption('maxloops'));

		// output settings
		$this->debug('== Settings ==');
		if($this->debugEnabled) {
			$settings = $input->getOptions();
			foreach($settings as $option => $value)
			{
				if('' !== (string)$value) {
					$this->debug(sprintf('--%s=%s', $option, $value));
				}
			}
		}
		$this->debug("\n");

		// run the loop
		$loops = 1;
		$blnMoreLoops = false;
		$tmpPreloopResultData = null;
		do
		{
			// info
			$this->debug("--[Loop $loops]--");

			// run cronjob
			$result = $this->executeCronjob($input, $output, $loops, $tmpPreloopResultData);
			$this->debug("\n");

			// execute more loops?
			$blnMoreLoops = (false !== $result && $this->checkRuntime() && (0 === $maxCalls || $loops <= $maxCalls));
			if($blnMoreLoops) {

				// result-data for the next loop available?
				$tmpPreloopResultData = is_bool($result) ? null : $result;

				// sleep to avoid deadlocks
				sleep(1);

				// increment loop
				$loops++;
			}
		}
		while($blnMoreLoops);

		// finish
		$this->debug("cronjob stopped successfully after ".$loops." loops\n");
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
	protected  function executeCronjob(InputInterface $input, OutputInterface $output, $loopcount, $preloopResult = null)
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
	 * @return boolean
	 */
	protected function checkRuntime()
	{
		// infinit time oder within the max-runtime?
		if(-1 === $this->runtimeMax || ((\time() - $this->runtimeStart) <= $this->runtimeMax)) {
			// ok, go on
			return true;
		}

		// limit reached, stop
		return	false;
	}
}
