<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Validator\Constraints\PasswordRequirements;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Same rules as the registration form: a password set here must pass what a password set
 * there passes.
 *
 * @extends AbstractType<User>
 */
final class ChangePasswordFormType extends AbstractType
{
    #[Override]
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add(child: 'plainPassword', type: RepeatedType::class, options: [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'reset_password.reset_form.password',
                    'hash_property_path' => 'password',
                    'attr' => ['autocomplete' => 'new-password', 'autofocus' => true],
                ],
                'second_options' => [
                    'label' => 'reset_password.reset_form.password_repeat',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'password_mismatch',
                'constraints' => [
                    new PasswordRequirements(),
                ],
            ])
            ->add(child: 'submit', type: SubmitType::class, options: [
                'label' => 'reset_password.reset_form.submit',
            ])
        ;
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'security',
        ]);
    }
}
