<?php

namespace App\Command;

use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(
    name: 'app:user:set-backoffice-roles',
    description: 'Définit les permissions backoffice d’un utilisateur.'
)]
final class SetBackofficeRolesCommand extends Command
{
    private const ROLE_MAP = [
        'catalog' => 'ROLE_CATALOG_MANAGER',
        'marketing' => 'ROLE_MARKETING_MANAGER',
        'analytics' => 'ROLE_DATA_ANALYST',
        'data' => 'ROLE_DATA_MANAGER',
        'admin' => 'ROLE_ADMIN',
    ];

    private const BACKOFFICE_ROLES = [
        'ROLE_BACKOFFICE',
        'ROLE_CATALOG_MANAGER',
        'ROLE_MARKETING_MANAGER',
        'ROLE_DATA_ANALYST',
        'ROLE_DATA_MANAGER',
        'ROLE_ADMIN',
    ];

    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument(
                'email',
                InputArgument::REQUIRED,
                'Adresse e-mail de l’utilisateur'
            )
            ->addArgument(
                'roles',
                InputArgument::REQUIRED
                | InputArgument::IS_ARRAY,
                'catalog, marketing, analytics, data, admin ou none'
            );
    }

    protected function execute(
        InputInterface $input,
        OutputInterface $output
    ): int {
        $email = strtolower(
            trim(
                (string) $input->getArgument(
                    'email'
                )
            )
        );

        $user = $this->users->findOneBy([
            'email' => $email,
        ]);

        if ($user === null) {
            $output->writeln(
                '<error>Utilisateur introuvable.</error>'
            );

            return Command::FAILURE;
        }

        $aliases = array_values(
            array_unique(
                array_map(
                    static fn (mixed $role): string =>
                        strtolower(
                            trim((string) $role)
                        ),
                    $input->getArgument('roles')
                )
            )
        );

        if (
            in_array('none', $aliases, true)
            && count($aliases) !== 1
        ) {
            $output->writeln(
                '<error>none doit être utilisé seul.</error>'
            );

            return Command::INVALID;
        }

        foreach ($aliases as $alias) {
            if (
                $alias !== 'none'
                && !array_key_exists(
                    $alias,
                    self::ROLE_MAP
                )
            ) {
                $output->writeln(
                    sprintf(
                        '<error>Permission inconnue : %s.</error>',
                        $alias
                    )
                );

                return Command::INVALID;
            }
        }

        $preservedRoles = array_values(
            array_filter(
                $user->getRoles(),
                static fn (string $role): bool =>
                    $role !== 'ROLE_USER'
                    && !in_array(
                        $role,
                        self::BACKOFFICE_ROLES,
                        true
                    )
            )
        );

        $backofficeRoles = [];

        if ($aliases !== ['none']) {
            if (
                in_array(
                    'admin',
                    $aliases,
                    true
                )
            ) {
                $backofficeRoles = [
                    'ROLE_ADMIN',
                ];
            } else {
                foreach ($aliases as $alias) {
                    $backofficeRoles[] =
                        self::ROLE_MAP[$alias];
                }
            }
        }

        $user->setRoles(
            array_values(
                array_unique([
                    ...$preservedRoles,
                    ...$backofficeRoles,
                ])
            )
        );

        $this->em->flush();

        $output->writeln(
            $backofficeRoles === []
                ? '<info>Permissions backoffice retirées.</info>'
                : sprintf(
                    '<info>Permissions enregistrées : %s.</info>',
                    implode(
                        ', ',
                        $backofficeRoles
                    )
                )
        );

        return Command::SUCCESS;
    }
}
