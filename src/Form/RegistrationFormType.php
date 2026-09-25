<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\User;
use App\Enum\Gender;
use App\Validator\Constraints\PasswordRequirements;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\IsTrue;
use Symfony\Component\Validator\Constraints\NotBlank;

/**
 * @extends AbstractType<User>
 */
final class RegistrationFormType extends AbstractType
{
    #[Override]
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add(child: 'firstname', type: TextType::class, options: [
                'label' => 'registration.form.firstname',
                'attr' => ['autocomplete' => 'given-name', 'autofocus' => true],
            ])
            ->add(child: 'lastname', type: TextType::class, options: [
                'label' => 'registration.form.lastname',
                'attr' => ['autocomplete' => 'family-name'],
            ])
            ->add(child: 'gender', type: EnumType::class, options: [
                'class' => Gender::class,
                'label' => 'registration.form.gender.label',
                'placeholder' => 'registration.form.gender.placeholder',
                'constraints' => [new NotBlank(message: 'gender.blank')],
            ])
            ->add(child: 'email', type: EmailType::class, options: [
                'label' => 'registration.form.email',
                'attr' => ['autocomplete' => 'email'],
            ])
            ->add(child: 'plainPassword', type: RepeatedType::class, options: [
                'type' => PasswordType::class,
                'mapped' => false,
                'first_options' => [
                    'label' => 'registration.form.password',
                    'hash_property_path' => 'password',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'second_options' => [
                    'label' => 'registration.form.password_repeat',
                    'attr' => ['autocomplete' => 'new-password'],
                ],
                'invalid_message' => 'password_mismatch',
                'constraints' => [
                    new PasswordRequirements(),
                ],
            ])
            ->add(child: 'agreeTerms', type: CheckboxType::class, options: [
                'mapped' => false,
                'label' => 'registration.form.agree_terms',
                'constraints' => [
                    new IsTrue(message: 'registration.terms_missing'),
                ],
            ])
            ->add(child: 'security', type: TurnstileFormType::class, options: [
                'label' => false,
                'turnstileAction' => 'registration',
            ])
            ->add(child: 'submit', type: SubmitType::class, options: [
                'label' => 'registration.form.submit',
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
