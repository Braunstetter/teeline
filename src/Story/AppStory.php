<?php

declare(strict_types=1);

namespace App\Story;

use App\Enum\Gender;
use App\Factory\UserFactory;
use Override;
use Zenstruck\Foundry\Attribute\AsFixture;
use Zenstruck\Foundry\Story;

/**
 * The accounts to sign in with while developing: `bin/console foundry:load-fixtures`.
 */
#[AsFixture(name: 'app')]
final class AppStory extends Story
{
    /**
     * The password for every account in here, and the one the functional tests sign
     * up and in with. Fixture data, never a real secret.
     */
    public const string PASSWORD = 'Korrekt-Pferd-9182-xyz';

    #[Override]
    public function build(): void
    {
        $this->addState(name: 'confirmed', value: UserFactory::createOne([
            'email' => 'confirmed@teeline.test',
            'firstname' => 'Conny',
            'gender' => Gender::Female,
            'language' => 'de',
            'lastname' => 'Bestätigt',
            'password' => self::PASSWORD,
        ]));

        // Mails go out in the account language, whoever triggered them.
        $this->addState(name: 'english', value: UserFactory::createOne([
            'email' => 'english@teeline.test',
            'firstname' => 'Ellen',
            'gender' => Gender::Male,
            'language' => 'en',
            'lastname' => 'English',
            'password' => self::PASSWORD,
        ]));

        // Cannot sign in: the user provider skips unconfirmed accounts. Registering
        // with this address again resends the confirmation mail.
        $this->addState(name: 'unconfirmed', value: UserFactory::new()
            ->unverified()
            ->create([
                'email' => 'unconfirmed@teeline.test',
                'firstname' => 'Uwe',
                'gender' => Gender::Diverse,
                'language' => 'de',
                'lastname' => 'Unbestätigt',
                'password' => self::PASSWORD,
            ]));
    }
}
