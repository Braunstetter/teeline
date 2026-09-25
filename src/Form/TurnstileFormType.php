<?php

declare(strict_types=1);

namespace App\Form;

use Override;
use PixelOpen\CloudflareTurnstileBundle\Type\TurnstileType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

/**
 * @extends AbstractType<mixed>
 */
final class TurnstileFormType extends AbstractType
{
    public function __construct(
        private readonly TranslatorInterface $translator,
    ) {
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        // The key, not the sentence: a resolved form type is kept for the life of
        // the worker, so translating here would freeze the first request's language.
        $resolver->define('disabledInfo')
            ->default('turnstile.disabled_info')
            ->allowedTypes('string')
            ->info(
                'Translation key for the title of the submit button while the challenge is unsolved',
            );

        // Left alone, the widget follows prefers-color-scheme and turns black on
        // systems set to dark, while the page around it stays light.
        $resolver->define('theme')
            ->default('light')
            ->allowedValues('light', 'dark', 'auto')
            ->info('Colour scheme of the widget');

        $resolver->define('size')
            ->default('flexible')
            ->allowedValues('normal', 'flexible', 'compact')
            ->info('Width behaviour of the widget');

        // Not 'action': FormType already owns that name for the form's action URL.
        // Required on purpose, because three forms sharing one name is what happened
        // before, and Cloudflare's analytics cannot tell them apart afterwards.
        $resolver->define('turnstileAction')
            ->required()
            ->allowedTypes('string')
            ->info('Name this widget reports to Cloudflare; one per form');
    }

    #[Override]
    public function buildView(
        FormView $view,
        FormInterface $form,
        array $options,
    ): void {
        $disabledInfo = $options['disabledInfo'];
        $theme = $options['theme'];
        $size = $options['size'];
        $turnstileAction = $options['turnstileAction'];

        Assert::string($disabledInfo);
        Assert::string($theme);
        Assert::string($size);
        Assert::string($turnstileAction);

        // Every option travels as a Stimulus value, not as a data attribute on the div:
        // the widget renders explicitly, and turnstile.render() takes its parameters from
        // JavaScript only -- anything sitting on the element itself is read by nobody.
        $view->vars['turnstile_disabled_info'] = $this->translator->trans(
            id: $disabledInfo,
            domain: 'security',
        );
        $view->vars['turnstile_theme'] = $theme;
        $view->vars['turnstile_size'] = $size;
        $view->vars['turnstile_action'] = $turnstileAction;
        $view->vars['turnstile_language'] = $this->translator->getLocale();
    }

    #[Override]
    public function getParent(): string
    {
        return TurnstileType::class;
    }
}
