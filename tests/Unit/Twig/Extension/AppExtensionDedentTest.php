<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig\Extension;

use App\Twig\Extension\AppExtension;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class AppExtensionDedentTest extends TestCase
{
    private AppExtension $extension;

    #[Override]
    protected function setUp(): void
    {
        $this->extension = new AppExtension();
    }

    #[DataProvider('dedentProvider')]
    public function testDedent(string $input, string $expected): void
    {
        self::assertSame($expected, $this->extension->dedent($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function dedentProvider(): iterable
    {
        yield 'strips consistent 4-space indentation' => [
            "\n    line one\n\n    line two\n",
            "\nline one\n\nline two\n",
        ];

        yield 'keeps indentation when another line is flush left' => [
            "\n    indented line\n\nnon-indented line\n",
            "\n    indented line\n\nnon-indented line\n",
        ];

        yield 'keeps the relative indent of a nested list' => [
            "    - one\n        - two",
            "- one\n    - two",
        ];

        yield 'preserves text without indentation' => [
            "line one\n\nline two\n",
            "line one\n\nline two\n",
        ];

        yield 'handles empty string' => ['', ''];

        yield 'handles single line with indentation' => ['    hello', 'hello'];

        yield 'handles only blank lines' => ["\n\n\n", "\n\n\n"];

        yield 'strips 8-space indentation from nested twig blocks' => [
            "\n        deeply indented\n\n        also indented\n",
            "\ndeeply indented\n\nalso indented\n",
        ];
    }
}
