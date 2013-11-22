<?php

namespace basecom\CronjobBundle\Entity;

use basecom\CronjobBundle\Abstracts\AbstractCronjobEntity;
use Doctrine\ORM\Mapping as ORM;

/**
 * Cronjob
 *
 * @ORM\Table(name="bsc_cronjob_registry", indexes={
 *  @ORM\Index(name="cmd", columns={"command"})
 * })
 * @ORM\Entity(repositoryClass="\basecom\CronjobBundle\Repository\CronjobRepository")
 */
class Cronjob extends AbstractCronjobEntity
{
    /**
     * @var integer
     *
     * @ORM\Column(name="id", type="integer")
     * @ORM\Id
     * @ORM\GeneratedValue(strategy="AUTO")
     */
    private $id;

    /**
     * @return int
     */
    public function getId()
    {
        return $this->id;
    }
}
