<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Entity\User;
use App\Form\ProfileFormType;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\ChoiceList\View\ChoiceView;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Validator\Validation;
use Webmozart\Assert\Assert;

final class ProfileFormTypeLanguageTest extends TestCase
{
    public function testLanguageChoicesFollowTheEnabledLocales(): void
    {
        $choices = $this->languageChoiceLabels(['en', 'de']);

        self::assertSame(['English', 'Deutsch'], $choices);
    }

    public function testANewEnabledLocaleShowsUpWithoutTouchingTheForm(): void
    {
        $choices = $this->languageChoiceLabels(['en', 'de', 'fr']);

        self::assertContains('français', $choices);
    }

    /**
     * @param list<string> $locales
     *
     * @return list<string>
     */
    private function languageChoiceLabels(array $locales): array
    {
        $view = $this->createLanguageView($locales);

        /** @var list<ChoiceView> $choices */
        $choices = $view->vars['choices'];

        return array_map(
            callback: static function (ChoiceView $choice): string {
                Assert::string($choice->label);

                return $choice->label;
            },
            array: $choices,
        );
    }

    /**
     * @param list<string> $locales
     */
    private function createLanguageView(array $locales): FormView
    {
        $factory = Forms::createFormFactoryBuilder()
            ->addExtension(new ValidatorExtension(
                Validation::createValidator(),
            ))
            ->addType(new ProfileFormType($locales))
            ->getFormFactory();

        return $factory->create(type: ProfileFormType::class, data: new User())
            ->createView()
            ->children['language'];
    }
}
