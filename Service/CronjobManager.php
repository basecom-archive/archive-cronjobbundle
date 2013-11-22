<?php

namespace basecom\CronjobBundle\Service;

use basecom\CronjobBundle\Command\CronjobCommand;
use basecom\CronjobBundle\Entity\Cronjob;
use basecom\CronjobBundle\Repository\CronjobRepository;
use Doctrine\ORM\EntityManager;

class CronjobManager
{
    /**
     * @var \Doctrine\ORM\EntityManager
     */
    private $entityManager;

    /**
     * @var array
     */
    private $registry;

    private $registryClass;

    /**
     * @param EntityManager $entityManager
     * @param $registryClass
     */
    public function __construct(EntityManager $entityManager, $registryClass)
    {
        $this->entityManager = $entityManager;
        $this->registryClass = $registryClass;
        $this->registry = array();
    }

    /**
     * Locks the given command for singleton execution and returns true.
     * If the command can't be locked, false will be returned.
     *
     * @param CronjobCommand $command
     * @return bool
     */
    public function lockCronjobCommand(CronjobCommand $command)
    {
        // get the registry entity
        $registryEntry = $this->getRegistryEntryForCommand($command);

        // already locked status?
        $now = new \DateTime();
        if($registryEntry->getSingletonLocked() && $now < $registryEntry->getSingletonTtl()) {
            return false;
        }

        // get the defined timeout
        $ttl = $command->getSingletonTimeout();

        // prepare timeout
        $time = \time();
        if($ttl <= $time) {
            $singletonTtl = new \DateTime(\sprintf("+%u seconds", $ttl));
        }
        else {
            $singletonTtl = new \DateTime();
            $singletonTtl->setTimestamp($ttl);
        }

        // try to lock the command with the given ttl
        return $this->getCronjobRegistryRepository()->lockCronjobCommand($registryEntry, $singletonTtl);
    }

    /**
     * Unlocks the given command for singleton execution and returns true.
     * If the command can't be unlocked, false will be returned.
     *
     * @param CronjobCommand $command
     * @return bool
     */
    public function unlockCronjobCommand(CronjobCommand $command)
    {
        return $this->getCronjobRegistryRepository()->unlockCronjobCommand($this->getRegistryEntryForCommand($command));
    }

    /**
     * @return \Doctrine\ORM\EntityManager
     */
    private function getEntityManager()
    {
        return $this->entityManager;
    }

    /**
     * @return CronjobRepository
     */
    private function getCronjobRegistryRepository()
    {
        return $this->getEntityManager()->getRepository($this->registryClass);
    }

    /**
     * @param CronjobCommand $command
     * @return Cronjob
     * @throws \Exception
     */
    private function getRegistryEntryForCommand(CronjobCommand $command)
    {
        // get the name of the command
        $name = $command->getName();

        // already loaded?
        if(!isset($this->registry[$name]))
        {
            $retries = 0;
            do
            {
                ++$retries;
                try
                {
                    // try to find an existing entity
                    $registryEntry = $this->getCronjobRegistryRepository()->findOneBy(array(
                        'command' => $name
                    ));

                    // create new instance
                    if(!$registryEntry)
                    {
                        // create instance
                        $class = $this->registryClass;
                        $registryEntry = new $class(); /* @var Cronjob $registryEntry */
                        $registryEntry->setCommand($name);
                        $registryEntry->setSingletonLocked(false);
                        $registryEntry->setSingletonTtl(null);

                        // save and flush
                        $this->getEntityManager()->persist($registryEntry);
                        $this->getEntityManager()->flush($registryEntry);
                    }
                }
                catch(\Exception $e)
                {
                    if(2 === $retries) {
                        throw $e;
                    }
                    $registryEntry = false;
                }
            }
            while(!$registryEntry);

            // keep the entry
            $this->registry[$name] = $registryEntry;
        }

        // return the registry entry
        return $this->registry[$name];
    }
} 