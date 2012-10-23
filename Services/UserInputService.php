<?php

namespace basecom\CronjobBundle\Services;

class UserInputService
{
	public function readInput($prefixOutput = null, $length = 80)
	{
		$prefixOutput && $this->writeOutput($prefixOutput);
		defined('STDIN') || define('STDIN', fopen('php://stdin'));
		$content = fread(STDIN, $length);
		return \trim($content);
	}

	public function writeOutput($content)
	{
		defined('STDOUT') || define('STDOUT', fopen('php://stdout'));
		fwrite(STDOUT, $content);
	}
}