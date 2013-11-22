<?php

namespace basecom\CronjobBundle\Repository;

use basecom\CronjobBundle\Abstracts\AbstractCronjobEntity;
use Doctrine\ORM\EntityRepository;

class CronjobRepository extends EntityRepository
{
    /**
     * @param \basecom\CronjobBundle\Abstracts\AbstractCronjobEntity $command
     * @return \Doctrine\ORM\QueryBuilder
     */
    private function getDefaultLockUpdateQuery(AbstractCronjobEntity $command)
    {
        // create the update query
        $qb = $this->createQueryBuilder('c');
        $qb->update()
            ->set('c.singletonLocked', ':locked_new')
            ->set('c.singletonTtl', ':ttl_new')
            ->where(
                $qb->expr()->eq('c.command', ':command')
            );

        if(null === $command->getSingletonLocked()) {
            $qb->andWhere($qb->expr()->isNull('c.singletonLocked'));
        }
        else {
            $qb->andWhere($qb->expr()->eq('c.singletonLocked', ':locked_old'));
        }

        if(null === $command->getSingletonTtl()) {
            $qb->andWhere($qb->expr()->isNull('c.singletonTtl'));
        }
        else {
            $qb->andWhere($qb->expr()->eq('c.singletonTtl', ':ttl_old'));
        }

        return $qb;
    }

    /**
     * @param AbstractCronjobEntity $command
     * @param \DateTime $singletonTtl
     * @return bool
     */
    public function lockCronjobCommand(AbstractCronjobEntity $command, \DateTime $singletonTtl)
    {
        // create the update query
        $qb = $this->getDefaultLockUpdateQuery($command);

        // define parameters
        $params = array(

            // set
            'locked_new'    => true,
            'ttl_new'       => $singletonTtl,

            // where
            'command'       => $command->getCommand()
        );
        if(null !== $command->getSingletonLocked()) {
            $params['locked_old'] = $command->getSingletonLocked();
        }
        if(null !== $command->getSingletonTtl()) {
            $params['ttl_old'] = $command->getSingletonTtl();
        }

        // set parameters to the query builder
        $qb->setParameters($params);

        // sync the entiy to prevent inconsistency during the locking process
        if($qb->getQuery()->execute() > 0)
        {
            // refresh entity
            $this->getEntityManager()->refresh($command);

            // succes
            return true;
        }

        // lock failed
        return false;
    }

    /**
     * @param AbstractCronjobEntity $command
     * @return bool
     */
    public function unlockCronjobCommand(AbstractCronjobEntity $command)
    {
        // create the update query
        $qb = $this->getDefaultLockUpdateQuery($command);

        // define parameters
        $params = array(

            // set
            'locked_new'    => false,
            'ttl_new'       => null,

            // where
            'command'       => $command->getCommand()
        );
        if(null !== $command->getSingletonLocked()) {
            $params['locked_old'] = $command->getSingletonLocked();
        }
        if(null !== $command->getSingletonTtl()) {
            $params['ttl_old'] = $command->getSingletonTtl();
        }

        // set parameters to the query builder
        $qb->setParameters($params);

        // sync the entiy to prevent inconsistency during the locking process
        if($qb->getQuery()->execute() > 0)
        {
            // refresh entity
            $this->getEntityManager()->refresh($command);

            // succes
            return true;
        }

        // lock failed
        return false;
    }
}