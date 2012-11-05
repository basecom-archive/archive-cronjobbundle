Basics
======

As shown in the project-description, this bundle is very easy to use.
With a few tweaks, you can fit your cronjobs even better to your needs.

If you are missing some functionality, please feel free to wirte a feature request!


Stop execution afer a defined amount of loops
---------------------------------------------
By default, there is no limit. If you like to set a limit you kan use this option:

``` bash
php app/console basecom:exampleCronjob --maxloops=5
```

In this example, the command will execute the cronjob-loop only 5 times.
Keep in mind that the real amount of loops depends on the time each loop takes to get executed.
For example, if your runtime is set to 10 seconds and each loop will run about 5 seconds, only 2 to 3 loops will be executed.


Disable output
--------------
If you don't wish to print the output of the command, you can use the default symfony '--no-debug=1' flag:

``` bash
php app/console basecom:exampleCronjob --no-debug=1
```


Set custom runtime
------------------
By default, the runtime per command-execution will be 50 seconds.
If you like to modify this amount of time, you can do this by setting the 'runtime'-option when you are executing the command:

``` bash
php app/console basecom:exampleCronjob --runtime=30
```

In this example, the command will now only run 30 seconds.


Set custom pause between each loop
----------------------------------
By default, the pause between each loop is one second. You can overwrite this value in your command class:

``` php
// ...
class ExampleCommand extends CronjobCommand
{
	// ...
	/**
	 * Time to sleep between the loops in micro seconds.
	 * A micro second is one millionth of a second.
	 * @var integer
	 */
	protected $sleeptime = 5000000;
	// ...
}
```

In this example, the command will now sleep 5 seconds between each loop.
