<?php

declare(strict_types=1);

namespace App\Form;

use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Email;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<array<string, mixed>>
 */
final class ResetPasswordRequestFormType extends AbstractType
{
    #[Override]
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add(child: 'email', type: EmailType::class, options: [
                'label' => 'reset_password.form.email',
                'attr' => ['autocomplete' => 'email', 'autofocus' => true],
                'constraints' => [
                    new NotBlank(message: 'email.blank'),
                    new Email(message: 'email.invalid'),
                ],
            ])
            ->add(child: 'security', type: TurnstileFormType::class, options: [
                'label' => false,
                'turnstileAction' => 'reset-password',
            ])
            ->add(child: 'submit', type: SubmitType::class, options: [
                'label' => 'reset_password.form.submit',
            ])
        ;
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'translation_domain' => 'security',
        ]);
    }
}
