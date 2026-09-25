<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Upload\Upload;
use App\Entity\User;
use App\Enum\AvatarSize;
use App\Enum\Gender;
use Locale;
use Override;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * @extends AbstractType<User>
 */
final class ProfileFormType extends AbstractType
{
    /**
     * @param list<string> $enabledLocales
     */
    public function __construct(
        #[Autowire(param: 'kernel.enabled_locales')]
        private readonly array $enabledLocales,
    ) {
    }

    #[Override]
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        $builder
            ->add(child: 'email', type: EmailType::class, options: [
                'mapped' => false,
                'constraints' => [
                    new Assert\NotBlank(message: 'email.blank'),
                    new Assert\Email(message: 'email.invalid'),
                ],
                'attr' => [
                    'autocomplete' => 'email',
                ],
            ])
            ->add(child: 'firstname', type: TextType::class, options: [
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'given-name',
                ],
            ])
            ->add(child: 'lastname', type: TextType::class, options: [
                'empty_data' => '',
                'attr' => [
                    'autocomplete' => 'family-name',
                ],
            ])
            ->add(child: 'gender', type: EnumType::class, options: [
                'class' => Gender::class,
                'constraints' => [new Assert\NotBlank(message: 'gender.blank')],
                'choice_label' => static fn (Gender $gender): string => 'gender.' . $gender->value,
                'choice_translation_domain' => 'app',
                'attr' => [
                    'autocomplete' => 'sex',
                ],
            ])
            ->add(child: 'language', type: ChoiceType::class, options: [
                'help' => 'profile.form.settings.language.help',
                'choices' => $this->languageChoices(),
                'choice_translation_domain' => false,
                'attr' => [
                    'autocomplete' => 'language',
                ],
            ])
            ->add(child: 'submit', type: SubmitType::class)
        ;

        // Added here rather than above, because the placeholder depends on the account.
        $builder->addEventListener(
            eventName: FormEvents::POST_SET_DATA,
            listener: static function (FormEvent $event): void {
                $user = $event->getData();

                if (! $user instanceof User) {
                    return;
                }

                $form = $event->getForm();

                // Unmapped, so it has to be filled by hand.
                $form->get('email')
                    ->setData($user->getEmail());

                $form
                    ->add(
                        child: 'picture',
                        type: ImageSingleFormType::class,
                        options: [
                            'placeholder_image_path' => $user->getAvatarPlaceholder(
                                AvatarSize::Large,
                            ),
                            'file_options' => [
                                'required' => false,
                                'constraints' => self::getPictureConstraints(),
                            ],
                        ],
                    );
            },
        );
    }

    /**
     * @return list<Constraint>
     */
    public static function getPictureConstraints(): array
    {
        return [
            new Assert\File(
                maxSize: Upload::MAX_UPLOAD_SIZE,
                mimeTypes: Upload::ALLOWED_IMAGE_MIME_TYPES,
                maxSizeMessage: 'upload.maxSizeMessage',
                mimeTypesMessage: 'upload.mimeTypesMessage',
            ),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function languageChoices(): array
    {
        return array_combine(
            keys: array_map(
                callback: static fn (string $locale): string => Locale::getDisplayLanguage(
                    locale: $locale,
                    displayLocale: $locale,
                ),
                array: $this->enabledLocales,
            ),
            values: $this->enabledLocales,
        );
    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'translation_domain' => 'user',
        ]);
    }
}
