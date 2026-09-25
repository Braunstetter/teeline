<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Webmozart\Assert\Assert;

final class BoxTemplateTest extends KernelTestCase
{
    private const array MARKERS = [
        'success' => ['bg-brand/5', 'text-brand'],
        'error' => ['bg-danger/5', 'text-danger'],
    ];

    /**
     * @param list<string> $markers
     */
    #[DataProvider('provideVariants')]
    public function testEveryVariantRendersWithItsOwnClasses(
        string $variant,
        array $markers,
    ): void {
        self::bootKernel();

        $twig = self::getContainer()->get('twig');
        Assert::isInstanceOf(value: $twig, class: Environment::class);

        $html = $twig->render(
            name: 'partials/embed/box.html.twig',
            context: ['variant' => $variant],
        );

        foreach ($markers as $marker) {
            self::assertStringContainsString($marker, $html);
        }
    }

    /**
     * @return iterable<string, array{string, list<string>}>
     */
    public static function provideVariants(): iterable
    {
        foreach (self::MARKERS as $variant => $markers) {
            yield $variant => [$variant, $markers];
        }
    }
}
