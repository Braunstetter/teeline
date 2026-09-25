<?php

declare(strict_types=1);

namespace App\Tests\Unit\Twig;

use App\Enum\NotificationType;
use App\Value\FlashMessages\Types\Notification;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Twig\Environment;
use Webmozart\Assert\Assert;

final class NotificationTemplateTest extends KernelTestCase
{
    private const array MARKERS = [
        'notification_success' => 'ring-brand/50',
        'notification_error' => 'ring-danger/50',
    ];

    #[DataProvider('provideTypes')]
    public function testEveryTypeRendersWithItsOwnClasses(
        NotificationType $type,
    ): void {
        self::assertArrayHasKey(
            $type->value,
            self::MARKERS,
            sprintf(
                'No marker for "%s": add its variant to classes.html.twig and list it here.',
                $type->value,
            ),
        );

        $html = $this->render($type);

        self::assertStringContainsString(self::MARKERS[$type->value], $html);
    }

    /**
     * @return iterable<string, array{NotificationType}>
     */
    public static function provideTypes(): iterable
    {
        foreach (NotificationType::cases() as $type) {
            yield $type->value => [$type];
        }
    }

    private function render(NotificationType $type): string
    {
        self::bootKernel();

        $twig = self::getContainer()->get('twig');
        Assert::isInstanceOf(value: $twig, class: Environment::class);

        $notification = new Notification(
            type: $type,
            title: 'Title',
            message: 'Message',
        );

        $data = json_decode(
            json: $notification->toJson(),
            associative: true,
            depth: 512,
            flags: JSON_THROW_ON_ERROR,
        );
        Assert::isArray($data);

        return $twig->render(
            name: 'app/flashMessages/notification/notification_embed.html.twig',
            context: [
                'data' => $data,
                'type' => $type,
            ],
        );
    }
}
