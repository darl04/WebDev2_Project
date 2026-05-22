<?php

namespace App\EventListener;

use App\Entity\ActivityLog;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Http\Event\LogoutEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class SecurityLogoutListener implements EventSubscriberInterface
{
    private EntityManagerInterface $em;

    public function __construct(EntityManagerInterface $em)
    {
        $this->em = $em;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            LogoutEvent::class => 'onLogout',
        ];
    }

    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();
        if (!$token) {
            return;
        }

        $user = $token->getUser();
        $log = new ActivityLog();
        $log->setUserId(method_exists($user, 'getId') ? $user->getId() : null);
        $log->setUsername(method_exists($user, 'getUserIdentifier') ? $user->getUserIdentifier() : null);
        $roles = method_exists($user, 'getRoles') ? $user->getRoles() : [];
        $primaryRole = null;
        if (is_array($roles)) {
            if (in_array('ROLE_ADMIN', $roles, true)) {
                $primaryRole = 'ROLE_ADMIN';
            } elseif (in_array('ROLE_STAFF', $roles, true)) {
                $primaryRole = 'ROLE_STAFF';
            } elseif (in_array('ROLE_USER', $roles, true)) {
                $primaryRole = 'ROLE_USER';
            } elseif (count($roles) > 0) {
                $primaryRole = $roles[0];
            }
        }
        $log->setRole($primaryRole);
        $log->setAction('LOGOUT');
        $log->setTarget('User logout');

        $this->em->persist($log);
        $this->em->flush();
    }
}
