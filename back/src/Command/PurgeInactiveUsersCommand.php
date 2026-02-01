<?php

namespace App\Command;

use App\Entity\AuditLog;
use App\Repository\AuditLogRepository;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:purge-inactive-users',
    description: 'Purge soft-deleted users and optionally inactive users'
)]
class PurgeInactiveUsersCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly AuditLogRepository $auditLogRepository
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption(
                'soft-deleted-days',
                'd',
                InputOption::VALUE_REQUIRED,
                'Hard delete users soft-deleted for more than X days',
                '30'
            )
            ->addOption(
                'inactive-months',
                'm',
                InputOption::VALUE_REQUIRED,
                'Hard delete users inactive for more than X months (set to 0 to disable)',
                '0'
            )
            ->addOption(
                'dry-run',
                null,
                InputOption::VALUE_NONE,
                'Run without actually deleting (preview mode)'
            )
            ->addOption(
                'force',
                'f',
                InputOption::VALUE_NONE,
                'Skip confirmation prompt'
            );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $softDeletedDays = (int) $input->getOption('soft-deleted-days');
        $inactiveMonths = (int) $input->getOption('inactive-months');
        $dryRun = $input->getOption('dry-run');
        $force = $input->getOption('force');

        $io->title('Purge des utilisateurs inactifs');

        if ($dryRun) {
            $io->note('Mode simulation activé - aucune suppression ne sera effectuée');
        }

        // Find users to purge
        $usersToPurge = [];
        $softDeleteThreshold = new \DateTimeImmutable("-{$softDeletedDays} days");

        // 1. Soft deleted users older than threshold
        $softDeletedUsers = $this->entityManager->createQueryBuilder()
            ->select('u')
            ->from('App\Entity\User', 'u')
            ->where('u.deletedAt IS NOT NULL')
            ->andWhere('u.deletedAt < :threshold')
            ->setParameter('threshold', $softDeleteThreshold)
            ->getQuery()
            ->getResult();

        foreach ($softDeletedUsers as $user) {
            $usersToPurge[$user->getId()] = [
                'user' => $user,
                'reason' => sprintf(
                    'Soft deleted since %s (%d days)',
                    $user->getDeletedAt()->format('Y-m-d'),
                    (new \DateTime())->diff($user->getDeletedAt())->days
                ),
            ];
        }

        // 2. Inactive users (if enabled)
        if ($inactiveMonths > 0) {
            $inactiveThreshold = new \DateTimeImmutable("-{$inactiveMonths} months");

            $inactiveUsers = $this->entityManager->createQueryBuilder()
                ->select('u')
                ->from('App\Entity\User', 'u')
                ->where('u.deletedAt IS NULL')
                ->andWhere('u.dateDerniereConnexion IS NOT NULL')
                ->andWhere('u.dateDerniereConnexion < :threshold')
                ->setParameter('threshold', $inactiveThreshold)
                ->getQuery()
                ->getResult();

            foreach ($inactiveUsers as $user) {
                if (!isset($usersToPurge[$user->getId()])) {
                    // Don't purge admins based on inactivity
                    if ($user->hasRole('ROLE_ADMIN') || $user->hasRole('ROLE_SUPER_ADMIN')) {
                        $io->warning(sprintf(
                            'Skipping admin user %s (inactive since %s)',
                            $user->getEmail(),
                            $user->getDateDerniereConnexion()->format('Y-m-d')
                        ));
                        continue;
                    }

                    $usersToPurge[$user->getId()] = [
                        'user' => $user,
                        'reason' => sprintf(
                            'Inactive since %s (%d months)',
                            $user->getDateDerniereConnexion()->format('Y-m-d'),
                            (int) ((new \DateTime())->diff($user->getDateDerniereConnexion())->days / 30)
                        ),
                    ];
                }
            }
        }

        if (empty($usersToPurge)) {
            $io->success('Aucun utilisateur à purger.');
            return Command::SUCCESS;
        }

        // Display users to purge
        $io->section(sprintf('Utilisateurs à purger: %d', count($usersToPurge)));

        $tableData = [];
        foreach ($usersToPurge as $data) {
            $user = $data['user'];
            $tableData[] = [
                $user->getId(),
                $user->getEmail(),
                $user->getNomComplet() ?? '-',
                $data['reason'],
            ];
        }

        $io->table(['ID', 'Email', 'Nom', 'Raison'], $tableData);

        // Confirmation
        if (!$dryRun && !$force) {
            $confirm = $io->confirm(
                sprintf('Voulez-vous supprimer définitivement ces %d utilisateurs ?', count($usersToPurge)),
                false
            );

            if (!$confirm) {
                $io->warning('Opération annulée.');
                return Command::SUCCESS;
            }
        }

        if ($dryRun) {
            $io->success(sprintf(
                'Mode simulation: %d utilisateurs auraient été purgés.',
                count($usersToPurge)
            ));
            return Command::SUCCESS;
        }

        // Actually delete users
        $deletedCount = 0;
        $errors = [];

        foreach ($usersToPurge as $data) {
            $user = $data['user'];

            try {
                $userId = $user->getId();
                $userEmail = $user->getEmail();

                // Create audit log before deletion
                $auditLog = new AuditLog();
                $auditLog->setAction(AuditLog::ACTION_HARD_DELETE);
                $auditLog->setMetadata([
                    'deleted_user_id' => $userId,
                    'deleted_user_email' => $userEmail,
                    'reason' => $data['reason'],
                    'purge_command' => true,
                ]);
                $this->entityManager->persist($auditLog);

                // Delete the user
                $this->entityManager->remove($user);
                $this->entityManager->flush();

                $deletedCount++;
                $io->text(sprintf('  ✓ Supprimé: %s (#%d)', $userEmail, $userId));
            } catch (\Exception $e) {
                $errors[] = sprintf(
                    'Erreur pour %s (#%d): %s',
                    $user->getEmail(),
                    $user->getId(),
                    $e->getMessage()
                );
            }
        }

        $io->newLine();

        if (!empty($errors)) {
            $io->section('Erreurs rencontrées');
            foreach ($errors as $error) {
                $io->error($error);
            }
        }

        $io->success(sprintf(
            'Purge terminée: %d/%d utilisateurs supprimés.',
            $deletedCount,
            count($usersToPurge)
        ));

        return empty($errors) ? Command::SUCCESS : Command::FAILURE;
    }
}
