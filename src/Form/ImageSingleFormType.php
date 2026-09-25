<?php

declare(strict_types=1);

namespace App\Form;

use App\Entity\Upload\Upload;
use Braunstetter\Helper\Arr;
use Override;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Event\PreSubmitEvent;
use Symfony\Component\Form\Event\SubmitEvent;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\UlidType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Uid\Ulid;
use Webmozart\Assert\Assert;

/**
 * @extends AbstractType<mixed>
 */
final class ImageSingleFormType extends AbstractType
{
    public const string PROTOTYPE_FIELD_NAME = '__name__';

    #[Override]
    public function buildForm(
        FormBuilderInterface $builder,
        array $options,
    ): void {
        /** @var array<string, mixed> $fileOptions */
        $fileOptions = $options['file_options'] ?? [];

        $builder
            ->add(child: 'id', type: UlidType::class, options: [
                'required' => false,
                'row_attr' => [
                    'class' => 'hidden',
                ],
            ])
            ->add(child: 'file', type: FileType::class, options: $fileOptions);

        if ($builder->getName() !== self::PROTOTYPE_FIELD_NAME) {
            $prototype = $builder->create(
                name: self::PROTOTYPE_FIELD_NAME,
                type: self::class,
                options: $options,
            );
            $builder->setAttribute(
                name: 'prototype',
                value: $prototype->getForm(),
            );
        }

        $builder->addEventListener(
            eventName: FormEvents::PRE_SUBMIT,
            listener: static function (PreSubmitEvent $event): void {
                $data = $event->getData();

                // Null when the field was not submitted at all. Reading it as an array
                // then turns a bad request into a 500.
                if (! is_array($data)) {
                    return;
                }

                if (($data['id'] ?? '') === '' && ($data['file'] ?? null) === null) {
                    $event->getForm()
                        ->setData(null);
                }
            },
        );

        $builder->addEventListener(
            eventName: FormEvents::SUBMIT,
            listener: static function (SubmitEvent $event): void {
                /** @var Upload|null $upload */
                $upload = $event->getData();
                if ($upload instanceof Upload && ! $upload->getFile() instanceof File && ! $upload->getId() instanceof Ulid) {
                    $event->setData(null);
                }
            },
        );

    }

    #[Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->define('placeholder_image_path')
            ->allowedTypes('string', 'null')
            ->default(null)
            ->info(
                'The public path to the placeholder image for this image field. It will be used if no image is uploaded. If you want to disable the placeholder image, set this option to null.',
            );

        $resolver->define('file_options')
            ->allowedTypes('array')
            ->default([
                'required' => false,
            ])
            ->info('The array of options for the file field.');

        $resolver->setDefaults([
            'data_class' => Upload::class,
        ]);
    }

    #[Override]
    public function finishView(
        FormView $view,
        FormInterface $form,
        array $options,
    ): void {
        $row_attr = $view->vars['row_attr'] ?? [];
        Assert::isArray($row_attr);

        $view->vars = array_replace($view->vars, [
            'row_attr' => Arr::attach(array: $row_attr, data: [
                'data-controller' => 'single-upload',
            ]),
        ]);

        $view->vars['placeholder_image_path'] = $options['placeholder_image_path'];

        /** @var array<string, mixed> $attr */
        $attr = $view->vars['attr'];

        $view->vars = array_replace($view->vars, [
            'attr' => Arr::attach(array: $attr, data: [
                'data-controller' => 'image-upload',
                'data-single-upload-target' => 'field',
            ]),
        ]);

    }

    #[Override]
    public function buildView(
        FormView $view,
        FormInterface $form,
        array $options,
    ): void {
        if ($form->getConfig()->hasAttribute('prototype')) {
            $prototype = $form->getConfig()
                ->getAttribute('prototype');
            Assert::isInstanceOf(
                value: $prototype,
                class: FormInterface::class,
            );
            $view->vars['prototype'] = $prototype->createView($view);
        }
    }

    #[Override]
    public function getBlockPrefix(): string
    {
        return 'media_image_single';
    }
}
