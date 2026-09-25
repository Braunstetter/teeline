<?php

declare(strict_types=1);

namespace App\Factory;

use App\Entity\User;
use App\Enum\Gender;
use App\Service\FileHelper;
use Override;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Webmozart\Assert\Assert;
use Zenstruck\Foundry\Persistence\PersistentObjectFactory;

/**
 * @extends PersistentObjectFactory<User>
 */
final class UserFactory extends PersistentObjectFactory
{
    public function __construct(
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly FileHelper $fileHelper,
    ) {
        parent::__construct();
    }

    #[Override]
    public static function class(): string
    {
        return User::class;
    }

    public function admin(): self
    {
        return $this->with(['roles' => ['ROLE_ADMIN']]);
    }

    public function unverified(): self
    {
        return $this->with(['isVerified' => false]);
    }

    public function withPicture(): self
    {
        return $this->with(['picture' => UploadFactory::new()]);
    }

    public function withAvatar(): self
    {
        return $this->beforeInstantiate(function (array $attributes): array {
            $gender = $attributes['gender'] ?? Gender::Male;
            Assert::isInstanceOf(value: $gender, class: Gender::class);

            $attributes['picture'] = UploadFactory::new([
                'file' => $this->fileHelper->getRandomAvatarPath($gender),
            ]);

            // ~20% get an academic title (prefix or suffix)
            if (self::faker()->boolean(20)) {
                return $this->applyRandomTitle($attributes);
            }

            return $attributes;
        });
    }

    /**
     * @return array<string, mixed>
     */
    #[Override]
    protected function defaults(): array
    {
        /** @var Gender $gender */
        $gender = self::faker()->randomElement(Gender::cases());

        return [
            'email' => self::faker()->unique()->safeEmail(),
            'firstname' => self::faker()->firstName($gender->value),
            'gender' => $gender,
            'isVerified' => true,
            'language' => 'en',
            'lastname' => self::faker()->lastName($gender->value),
            'password' => self::faker()->password(),
            'roles' => [],
        ];
    }

    #[Override]
    protected function initialize(): static
    {
        return $this->afterInstantiate(function (User $user): void {
            $plainPassword = $user->getPassword();
            Assert::stringNotEmpty($plainPassword);

            $user->setPassword(
                $this->passwordHasher->hashPassword(
                    user: $user,
                    plainPassword: $plainPassword,
                ),
            );
        });
    }

    /**
     * @param array<string, mixed> $attributes
     * @return array<string, mixed>
     */
    private function applyRandomTitle(array $attributes): array
    {
        $prefixes = ['Dr.', 'Prof.', 'Prof. Dr.'];
        $suffixes = ['PhD', 'MBA', 'MSc', 'BSc', 'MEd', 'BEd', 'MA', 'LL.M.'];

        if (self::faker()->boolean()) {
            $prefix = self::faker()->randomElement($prefixes);
            $firstname = $attributes['firstname'] ?? '';
            Assert::string($prefix);
            Assert::string($firstname);

            $attributes['firstname'] = $prefix . ' ' . $firstname;
        } else {
            $suffix = self::faker()->randomElement($suffixes);
            $lastname = $attributes['lastname'] ?? '';
            Assert::string($suffix);
            Assert::string($lastname);

            $attributes['lastname'] = $lastname . ' ' . $suffix;
        }

        return $attributes;
    }
}
