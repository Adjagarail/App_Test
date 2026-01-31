<?php

namespace App\DataFixtures;

use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    public function __construct(
        private UserPasswordHasherInterface $passwordHasher
    ) {}

    public function load(ObjectManager $manager): void
    {
        // User 1: admin
        $admin = new User();
        $admin->setEmail('admin@example.com');
        $admin->setNomComplet('Admin User');
        $admin->setRoles(['ROLE_ADMIN']);

        // IMPORTANT: hash du mot de passe
        $admin->setPassword(
            $this->passwordHasher->hashPassword($admin, 'admin123')
        );

        $manager->persist($admin);

        // User 2: user normal
        $user = new User();
        $user->setEmail('user@example.com');
        $user->setNomComplet('Normal User');
        $user->setRoles(['ROLE_USER']);

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, 'user123')
        );

        $manager->persist($user);

        $manager->flush();
    }
}
