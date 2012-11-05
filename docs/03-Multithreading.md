Multithreading
==============

Importent: For this feature, you need the PCNTL module for PHP.
To enable the threading you just have the define the amount of threads to use:

``` php
// ...
class ExampleCommand extends CronjobCommand
{
	// ...
	/**
	 * Amount of instances to use (0/1 = no threading, single process)
	 * @var integer
	 */
	protected $threads = 3;
	// ...
}
```

In this example, 3 threads will be spawned. Keep in mind that you now have to programm in a threadsave way if you work with resources like files or database entries!
