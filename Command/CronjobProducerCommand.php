<?php

namespace basecom\CronjobBundle\Command;

use Cron\CronExpression;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Process\ProcessBuilder;

/**
 * @author Maximilian Beckers <beckers@basecom.de>
 */
class CronjobProducerCommand extends CronjobCommand
{
    /**
     * {@inheritdoc}
     */
    protected $singletonMode = true;

    /**
     * {@inheritdoc}
     */
    protected function configure()
    {
        parent::configure();

        $this
            ->setName('bsc:cronjob:produce')
            ->setDescription('Schedules cronjobs from given Config.');
    }

    /**
     * {@inheritdoc}
     */
    protected function executeCronjob(InputInterface $input, OutputInterface $output, $loopcount, $preloopResult = null)
    {
        $second = (int)date('s');
        if((null !== $preloopResult && 0 !== $second) || $preloopResult < $second ) {
            return $second;
        }

        $cronjobs = $this->getContainer()->getParameter('bsc.cronjob.cronjobs');

        foreach ($cronjobs as $cronjob) {
            $cron = CronExpression::factory($cronjob['schedule']);
            if ($cron->isDue()) {
                $output->writeln('Starting process ' . $cronjob['script']);
                exec($cronjob['script'] . ' &');
            }
        }

        return $second;
    }
}
