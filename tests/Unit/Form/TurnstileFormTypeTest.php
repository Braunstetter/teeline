<?php

declare(strict_types=1);

namespace App\Tests\Unit\Form;

use App\Form\TurnstileFormType;
use PHPUnit\Framework\TestCase;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\Extension\Validator\ValidatorExtension;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\Forms;
use Symfony\Component\Form\FormView;
use Symfony\Component\Translation\Loader\ArrayLoader;
use Symfony\Component\Translation\Translator;
use Symfony\Component\Validator\Validation;

final class TurnstileFormTypeTest extends TestCase
{
    /**
     * The widget renders explicitly, so every option has to reach turnstile.render()
     * as a Stimulus value. An attribute on the div alone is read by nobody.
     */
    public function testWidgetPassesRenderOptionsAsStimulusValues(): void
    {
        $view = $this->createWidgetView(
            factory: $this->createFactory($this->createTranslator()),
            options: ['turnstileAction' => 'login'],
        );

        self::assertSame('light', $view->vars['turnstile_theme']);
        self::assertSame('flexible', $view->vars['turnstile_size']);
        self::assertSame('login', $view->vars['turnstile_action']);
    }

    /**
     * One factory across both locales on purpose: it keeps the resolved type, which is
     * exactly what would freeze a sentence translated while the options are configured.
     */
    public function testWidgetTranslatesTheDisabledInfoAgainForEveryLocale(): void
    {
        $translator = $this->createTranslator();
        $factory = $this->createFactory($translator);

        $translator->setLocale('de');
        $german = $this->createWidgetView($factory);

        $translator->setLocale('en');
        $english = $this->createWidgetView($factory);

        self::assertSame(
            'Bestätige, dass du ein Mensch bist.',
            $german->vars['turnstile_disabled_info'],
        );
        self::assertSame(
            'Confirm that you are a human.',
            $english->vars['turnstile_disabled_info'],
        );
    }

    public function testWidgetCarriesTheCurrentLocaleAsLanguage(): void
    {
        $translator = $this->createTranslator();
        $factory = $this->createFactory($translator);

        $translator->setLocale('de');
        self::assertSame(
            'de',
            $this->createWidgetView($factory)
                ->vars['turnstile_language'],
        );

        $translator->setLocale('en');
        self::assertSame(
            'en',
            $this->createWidgetView($factory)
                ->vars['turnstile_language'],
        );
    }

    private function createTranslator(): Translator
    {
        $translator = new Translator('de');
        $translator->addLoader(format: 'array', loader: new ArrayLoader());
        $translator->addResource(
            format: 'array',
            resource: ['turnstile.disabled_info' => 'Bestätige, dass du ein Mensch bist.'],
            locale: 'de',
            domain: 'security',
        );
        $translator->addResource(
            format: 'array',
            resource: ['turnstile.disabled_info' => 'Confirm that you are a human.'],
            locale: 'en',
            domain: 'security',
        );

        return $translator;
    }

    private function createFactory(Translator $translator): FormFactoryInterface
    {
        return Forms::createFormFactoryBuilder()
            ->addExtension(
                new ValidatorExtension(Validation::createValidator()),
            )
            ->addType(new TurnstileType(key: 'test-site-key', enable: true))
            ->addType(new TurnstileFormType(translator: $translator))
            ->getFormFactory();
    }

    /**
     * @param array<string, mixed> $options
     */
    private function createWidgetView(
        FormFactoryInterface $factory,
        array $options = [],
    ): FormView {
        return $factory->create(
            type: TurnstileFormType::class,
            options: ['turnstileAction' => 'registration', ...$options],
        )->createView();
    }
}
