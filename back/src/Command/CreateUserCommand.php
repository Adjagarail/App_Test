<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-user',
    description: 'Créer un utilisateur pour tester JWT',
)]
class CreateUserCommand extends Command
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::OPTIONAL, 'Email de l\'utilisateur', 'test@example.com')
            ->addArgument('Nom Complet', InputArgument::OPTIONAL, 'Nom Complet de l\'utilisateur', 'Utilisateur Test')
            ->addArgument('password', InputArgument::OPTIONAL, 'Mot de passe', 'password')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = $input->getArgument('email');
        $nomComplet = $input->getArgument('Nom Complet');
        $password = $input->getArgument('password');

        // Vérifier si l'utilisateur existe déjà
        $existingUser = $this->entityManager->getRepository(User::class)->findOneBy(['email' => $email]);
        if ($existingUser) {
            $io->warning(sprintf('L\'utilisateur %s existe déjà. Suppression...', $email));
            $this->entityManager->remove($existingUser);
            $this->entityManager->flush();
        }

        // Créer l'utilisateur
        $user = new User();
        $user->setEmail($email);
        $user->setNomComplet($nomComplet);
        $user->setRoles(['ROLE_USER']);
        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $password)
        );

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success([
            'Utilisateur créé avec succès !',
            sprintf('Nom Complet: %s', $nomComplet),
            sprintf('Email: %s', $email),
            sprintf('Password: %s', $password),
            '',
            'Testez la connexion avec:',
            sprintf('curl -X POST http://localhost:8080/api/login_check -H "Content-Type: application/json" -d \'{"email":"%s","password":"%s"}\'', $email, $password)
        ]);

        return Command::SUCCESS;
    }
}
