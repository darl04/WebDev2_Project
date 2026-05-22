<?php

namespace App\Security;

use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;

class UserChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        if (method_exists($user, 'isActive') && !$user->isActive()) {
            // this message will be shown to the user
            throw new CustomUserMessageAccountStatusException('Your account is disabled. Please contact an administrator.');
        }
    }

    public function checkPostAuth(UserInterface $user): void
    {
        // no-op
    }
}
