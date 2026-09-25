<?php

declare(strict_types=1);

namespace App\Tests\Trait;

use App\Service\FileHelper;
use Symfony\Component\DomCrawler\Form;
use Symfony\Component\PropertyAccess\PropertyAccess;

trait UploadTrait
{
    /**
     * Add an uploaded file to a form.
     *
     * Then you can submit the form like this:
     *
     * [$values, $files] = $this->addUploadToForm(
     *     form: $form,
     *     filePath: 'assets/images/fixtures/avatar.png',
     *     field: '[profile_form][picture][file]',
     * );
     *
     * $this->client->request($form->getMethod(), $form->getUri(), $values, $files);
     *
     * @return array{array<array-key, mixed>, array<array-key, mixed>}
     */
    public function addUploadToForm(
        Form $form,
        string $filePath,
        string $field,
    ): array {
        // PHPStan reads the container as returning object, Psalm already knows the
        // type and calls a runtime check redundant. A type line suits both.
        /** @var FileHelper $fileHelper */
        $fileHelper = self::getContainer()->get(FileHelper::class);

        $uploadedFile = $fileHelper->getTempFilePath($filePath);

        $values = $form->getPhpValues();
        $files = $form->getPhpFiles();

        $accessor = PropertyAccess::createPropertyAccessor();
        $accessor->setValue(
            objectOrArray: $values,
            propertyPath: $field,
            value: $uploadedFile,
        );
        $accessor->setValue(
            objectOrArray: $files,
            propertyPath: $field,
            value: [
                'name' => basename($uploadedFile),
                'type' => mime_content_type($uploadedFile),
                'tmp_name' => $uploadedFile,
                'error' => UPLOAD_ERR_OK,
                'size' => filesize($uploadedFile),
            ],
        );

        return [
            $values,
            $files,
        ];
    }
}
