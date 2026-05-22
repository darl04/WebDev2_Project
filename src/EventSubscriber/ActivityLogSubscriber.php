<?php

namespace App\EventSubscriber;

use App\Entity\ActivityLog;
use App\Entity\Products;
use App\Entity\StockAdjustment;
use Doctrine\Common\EventSubscriber;
use Doctrine\ORM\Events;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\ORM\Event\OnFlushEventArgs;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class ActivityLogSubscriber implements EventSubscriber
{
    private TokenStorageInterface $tokenStorage;
    private ManagerRegistry $registry;

    public function __construct(TokenStorageInterface $tokenStorage, ManagerRegistry $registry)
    {
        $this->tokenStorage = $tokenStorage;
        $this->registry = $registry;
    }

    public function getSubscribedEvents(): array
    {
        return [
            Events::onFlush,
        ];
    }

    public function onFlush(OnFlushEventArgs $args): void
    {
        $em = $args->getObjectManager();
        $uow = $em->getUnitOfWork();

        $nowUser = $this->tokenStorage->getToken()?->getUser();
        $username = null;
        $userId = null;
        $role = null;
        if (is_object($nowUser)) {
            $username = method_exists($nowUser, 'getUserIdentifier') ? $nowUser->getUserIdentifier() : (property_exists($nowUser, 'username') ? $nowUser->username : null);
            $userId = method_exists($nowUser, 'getId') ? $nowUser->getId() : null;
            $roles = method_exists($nowUser, 'getRoles') ? $nowUser->getRoles() : [];
            $role = is_array($roles) && count($roles) ? $roles[0] : null;
        }

        // Handle inserts
        foreach ($uow->getScheduledEntityInsertions() as $entity) {
            // don't log ActivityLog itself
            if ($entity instanceof ActivityLog || $entity instanceof StockAdjustment || $entity instanceof Products) {
                continue;
            }

            $log = new ActivityLog();
            $log->setUserId($userId);
            $log->setUsername($username);
            $log->setRole($role);
            $log->setAction('CREATE');
            $log->setTarget($this->describeEntity($entity));

            $em->persist($log);
            $uow->computeChangeSet($em->getClassMetadata(ActivityLog::class), $log);
        }

        // Handle updates
        foreach ($uow->getScheduledEntityUpdates() as $entity) {
            if ($entity instanceof ActivityLog || $entity instanceof StockAdjustment || $entity instanceof Products) {
                continue;
            }

            $log = new ActivityLog();
            $log->setUserId($userId);
            $log->setUsername($username);
            $log->setRole($role);
            $log->setAction('UPDATE');
            $log->setTarget($this->describeEntity($entity));

            $em->persist($log);
            $uow->computeChangeSet($em->getClassMetadata(ActivityLog::class), $log);
        }

        // Handle removals
        foreach ($uow->getScheduledEntityDeletions() as $entity) {
            if ($entity instanceof ActivityLog || $entity instanceof StockAdjustment || $entity instanceof Products) {
                continue;
            }

            $log = new ActivityLog();
            $log->setUserId($userId);
            $log->setUsername($username);
            $log->setRole($role);
            $log->setAction('DELETE');
            $log->setTarget($this->describeEntity($entity));

            $em->persist($log);
            $uow->computeChangeSet($em->getClassMetadata(ActivityLog::class), $log);
        }
    }

    private function describeEntity(object $entity): string
    {
        $class = (new \ReflectionClass($entity))->getShortName();
        $identifier = null;
        if (method_exists($entity, 'getId')) {
            $identifier = $entity->getId();
        }

        $summary = $class;
        if ($identifier !== null) {
            $summary .= sprintf(' (id: %s)', $identifier);
        }

        // Add some identifying fields for common entities
        if (method_exists($entity, 'getUsername')) {
            $summary .= ' - ' . $entity->getUsername();
        }
        if (method_exists($entity, 'getName')) {
            $summary .= ' - ' . $entity->getName();
        }

        return $summary;
    }
}
