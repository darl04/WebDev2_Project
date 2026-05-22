<?php

namespace App\Service;

// use App\Entity\User;
use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RequestStack;

class ActivityLogger
{
    private EntityManagerInterface $em;
    private Security $security;
    private RequestStack $requestStack;

    public function __construct(EntityManagerInterface $em, Security $security, RequestStack $requestStack)
    {
        $this->em = $em;
        $this->security = $security;
        $this->requestStack = $requestStack;
    }

    public function log(string $action, ?string $setTargetData = null)
    {
        $user = $this->security->getUser();
        $request = $this->requestStack->getCurrentRequest();

        $log = new ActivityLog();
        $log->setAction($action);
        $log->setTarget($setTargetData);
        $log->setCreatedAt(new \DateTimeImmutable);
        // $log->setIpAddress($request?->getClientIp());

        if ($user) {
            if (method_exists($user, 'getId')) {
                $log->setUserId($user->getId());
            }
            $log->setUsername($user->getUserIdentifier());
            $roles = $user->getRoles();
            $primaryRole = null;
            if (in_array('ROLE_ADMIN', $roles, true)) {
                $primaryRole = 'ROLE_ADMIN';
            } elseif (in_array('ROLE_STAFF', $roles, true)) {
                $primaryRole = 'ROLE_STAFF';
            } elseif (in_array('ROLE_USER', $roles, true)) {
                $primaryRole = 'ROLE_USER';
            } elseif (count($roles) > 0) {
                $primaryRole = $roles[0];
            }
            $log->setRole($primaryRole);
        }

        $this->em->persist($log);
        $this->em->flush();
    }
}