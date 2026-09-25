<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service\Upload;

use App\Entity\Upload\Upload;
use App\Service\Upload\UploadService;
use App\Tests\Trait\TestFileHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Webmozart\Assert\Assert;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

final class UploadNamingServiceTest extends KernelTestCase
{
    use Factories;
    use ResetDatabase;
    use TestFileHelperTrait;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testASecondUploadOfTheSameNameGetsASuffix(): void
    {
        self::assertSame('avatar.png', $this->store()->getFilename());
        self::assertSame('avatar-1.png', $this->store()->getFilename());
        self::assertSame('avatar-2.png', $this->store()->getFilename());
    }

    private function store(): Upload
    {
        $upload = new Upload($this->getTestImage());

        $this->uploader()
            ->upload($upload);

        $entityManager = $this->entityManager();
        $entityManager->persist($upload);
        $entityManager->flush();

        return $upload;
    }

    private function uploader(): UploadService
    {
        /** @var UploadService $uploader */
        $uploader = self::getContainer()->get(UploadService::class);

        return $uploader;
    }

    private function entityManager(): EntityManagerInterface
    {
        $entityManager = self::getContainer()->get(
            'doctrine.orm.entity_manager',
        );
        Assert::isInstanceOf(
            value: $entityManager,
            class: EntityManagerInterface::class,
        );

        return $entityManager;
    }
}
