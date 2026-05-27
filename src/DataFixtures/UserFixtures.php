<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserFixtures extends Fixture
{
    private UserPasswordHasherInterface $passwordHasher;

    public function __construct(UserPasswordHasherInterface $passwordHasher)
    {
        $this->passwordHasher = $passwordHasher;
    }

    public function load(ObjectManager $manager): void
    {
       $admin = new User();
         $admin->setUsername('admin');
         $admin->setEmail('admin@example.com');
       $admin->setRoles(['ROLE_ADMIN']);
         $admin->setIsVerified(true);
         $admin->setVerificationToken('seed-admin-token');
       $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
       $admin->setPassword($hashedPassword);
       $manager->persist($admin);

       $user = new User();
         $user->setUsername('user');
         $user->setEmail('user@example.com');
       $user->setRoles(['ROLE_USER']);
         $user->setIsVerified(true);
         $user->setVerificationToken('seed-user-token');
       $hashedPassword = $this->passwordHasher->hashPassword($user, 'user123');
       $user->setPassword($hashedPassword);
       $manager->persist($user);

       $staff = new User();
         $staff->setUsername('staff');
         $staff->setEmail('staff@example.com');
       $staff->setRoles(['ROLE_STAFF']);
         $staff->setIsVerified(true);
         $staff->setVerificationToken('seed-staff-token');
       $hashedPassword = $this->passwordHasher->hashPassword($staff, 'staff123');
       $staff->setPassword($hashedPassword);
       $manager->persist($staff);


        $manager->flush();
    }
}
