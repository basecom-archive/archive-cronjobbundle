<?php

namespace basecom\CronjobBundle\Abstracts;

use Doctrine\ORM\Mapping as ORM;

/**
 * @ORM\MappedSuperclass
 */
abstract class AbstractCronjobEntity
{
    /**
     * @var string
     *
     * @ORM\Column(name="command", type="string", length=512, nullable=false)
     */
    protected $command;

    /**
     * @var bool|null
     *
     * @ORM\Column(name="singleton_locked", type="boolean", length=255, nullable=true)
     */
    protected $singletonLocked;

    /**
     * @var \DateTime|null
     *
     * @ORM\Column(name="singleton_ttl", type="datetime", nullable=true)
     */
    protected $singletonTtl;

    /**
     * @return int
     */
    abstract public function getId();

    /**
     * @param string $command
     */
    public function setCommand($command)
    {
        $this->command = (string)$command;
    }

    /**
     * @return string
     */
    public function getCommand()
    {
        return $this->command;
    }

    /**
     * @param string $singletonLocked
     */
    public function setSingletonLocked($singletonLocked)
    {
        $this->singletonLocked = (bool)$singletonLocked;
    }

    /**
     * @return bool
     */
    public function getSingletonLocked()
    {
        return (bool)$this->singletonLocked;
    }

    /**
     * @param \DateTime|null $singletonTtl
     */
    public function setSingletonTtl(\DateTime $singletonTtl = null)
    {
        $this->singletonTtl = $singletonTtl;
    }

    /**
     * @return \DateTime|null
     */
    public function getSingletonTtl()
    {
        return $this->singletonTtl;
    }
}