<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un administrateur Shopwho.'
)]
class CreateAdminCommand extends Command
{
    public function __construct(
        private readonly UserRepository $users,
        private readonly EntityManagerInterface $em,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument(
            'email',
            InputArgument::REQUIRED,
            'Adresse e-mail de l’administrateur'
        );
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = strtolower(trim((string) $input->getArgument('email')));

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $output->writeln('<error>Adresse e-mail invalide.</error>');

            return Command::INVALID;
        }

        if ($this->users->findOneBy(['email' => $email])) {
            $output->writeln('<error>Un utilisateur avec cette adresse existe déjà.</error>');

            return Command::FAILURE;
        }

        $question = new Question('Mot de passe : ');
        $question->setHidden(true);
        $question->setHiddenFallback(false);

        $password = $this->getHelper('question')->ask(
            $input,
            $output,
            $question
        );

        if (!is_string($password) || strlen($password) < 12) {
            $output->writeln(
                '<error>Le mot de passe doit contenir au moins 12 caractères.</error>'
            );

            return Command::INVALID;
        }

        $user = (new User())
            ->setEmail($email)
            ->setRoles(['ROLE_ADMIN']);

        $user->setPassword(
            $this->passwordHasher->hashPassword($user, $password)
        );

        $this->em->persist($user);
        $this->em->flush();

        $output->writeln(sprintf(
            '<info>Administrateur %s créé.</info>',
            $email
        ));

        return Command::SUCCESS;
    }
}
