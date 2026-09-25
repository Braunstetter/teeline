<?php

declare(strict_types=1);

namespace App\Form;

use App\Event\Subscriber\LoginFormSubscriber;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class LoginFormType extends AbstractType
{
    public function __construct(
        private readonly LoginFormSubscriber $loginFormSubscriber,
    ) {
    }

    #[Override]
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add(child: '_username', type: EmailType::class, options: [
                'label' => 'login.form.email',
                'attr' => ['autocomplete' => 'email', 'autofocus' => true],
            ])
            ->add(child: '_password', type: PasswordType::class, options: [
                'label' => 'login.form.password',
                'attr' => ['autocomplete' => 'current-password'],
            ])
            ->add(child: '_remember_me', type: CheckboxType::class, options: [
                'label' => 'login.form.remember_me',
                'data' => true,
                'required' => false,
            ])
            ->add(child: 'security', type: TurnstileFormType::class, options: [
                'label' => false,
                'turnstileAction' => 'login',
            ])
            ->add(child: 'submit', type: SubmitType::class, options: [
                'label' => 'login.form.submit',
            ])
        ;

        $builder->addEventSubscriber($this->loginFormSubscriber);
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'csrf_protection' => false,
            'translation_domain' => 'security',
        ]);
    }
}
