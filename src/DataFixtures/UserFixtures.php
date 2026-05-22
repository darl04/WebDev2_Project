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
       $admin->setUserName('admin');
       $admin->setRoles(['ROLE_ADMIN']);
       $hashedPassword = $this->passwordHasher->hashPassword($admin, 'admin123');
       $admin->setPassword($hashedPassword);
       $manager->persist($admin);

       $user = new User();
       $user->setUserName('user');
       $user->setRoles(['ROLE_USER']);
       $hashedPassword = $this->passwordHasher->hashPassword($user, 'user123');
       $user->setPassword($hashedPassword);
       $manager->persist($user);

       $staff = new User();
       $staff->setUserName('staff');
       $staff->setRoles(['ROLE_STAFF']);
       $hashedPassword = $this->passwordHasher->hashPassword($staff, 'staff123');
       $staff->setPassword($hashedPassword);
       $manager->persist($staff);


        $manager->flush();
    }
}
