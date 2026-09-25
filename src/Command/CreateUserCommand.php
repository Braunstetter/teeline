<?php

declare(strict_types=1);

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\Argument;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Attribute\Option;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(
    name: 'app:user:create',
    description: 'Creates a user who can sign in',
)]
final readonly class CreateUserCommand
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private UserPasswordHasherInterface $passwordHasher,
        #[Autowire(param: 'kernel.default_locale')]
        private string $defaultLocale,
    ) {
    }

    public function __invoke(
        SymfonyStyle $io,
        #[Argument(description: 'Email address, used to sign in')]
        string $email,
        #[Argument(description: 'First name')]
        string $firstname,
        #[Argument(description: 'Last name')]
        string $lastname,
        #[Option(description: 'Password; generated and printed when omitted')]
        ?string $password = null,
    ): int {
        if ($this->entityManager->getRepository(User::class)->findOneBy(
            ['email' => $email],
        ) instanceof User) {
            $io->error(\sprintf('A user with "%s" already exists.', $email));

            return Command::FAILURE;
        }

        $generated = $password === null;
        $password ??= bin2hex(random_bytes(8));

        $user = new User();
        $user->setEmail($email);
        $user->setFirstname($firstname);
        $user->setLastname($lastname);
        $user->setLanguage($this->defaultLocale);
        $user->setPassword(
            $this->passwordHasher->hashPassword(
                user: $user,
                plainPassword: $password,
            ),
        );
        // Accounts created here skip the confirmation mail.
        $user->setIsVerified(true);

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success(\sprintf('Created %s', $email));

        if ($generated) {
            $io->writeln(\sprintf('Password: <info>%s</info>', $password));
            $io->comment('Shown once — it is stored hashed.');
        }

        return Command::SUCCESS;
    }
}
