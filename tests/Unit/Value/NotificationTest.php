<?php

declare(strict_types=1);

namespace App\Tests\Unit\Value;

use App\Enum\NotificationType;
use App\Value\FlashMessages\Types\Notification;
use PHPUnit\Framework\TestCase;
use Webmozart\Assert\Assert;

final class NotificationTest extends TestCase
{
    public function testNotificationCanBeCreatedWithRequiredParameters(): void
    {
        $notification = new Notification(
            type: NotificationType::SUCCESS,
            title: 'Test Title',
            message: 'Test Message',
        );

        self::assertSame(NotificationType::SUCCESS, $notification->getType());

        $data = $this->decode($notification);
        self::assertSame('Test Title', $data['title']);
        self::assertSame('Test Message', $data['message']);
        self::assertTrue($data['canBeDismissed']);
        self::assertSame(3, $data['timeToDestroy']);
        self::assertFalse($data['confetti']);
    }

    public function testNotificationCanBeCreatedWithAllParameters(): void
    {
        $notification = new Notification(
            type: NotificationType::SUCCESS,
            title: 'Success Title',
            message: 'Success Message',
            canBeDismissed: false,
            timeToDestroy: 10,
            confetti: true,
        );

        $data = $this->decode($notification);
        self::assertSame('Success Title', $data['title']);
        self::assertSame('Success Message', $data['message']);
        self::assertFalse($data['canBeDismissed']);
        self::assertSame(10, $data['timeToDestroy']);
        self::assertTrue($data['confetti']);
    }

    public function testNotificationHasCorrectDefaultValues(): void
    {
        $data = $this->decode(
            new Notification(type: NotificationType::SUCCESS, title: 'Title'),
        );

        self::assertTrue($data['canBeDismissed']);
        self::assertSame(3, $data['timeToDestroy']);
        self::assertNull($data['message']);
        self::assertFalse($data['confetti']);
    }

    public function testNotificationCanHaveNullTimeToDestroy(): void
    {
        $data = $this->decode(new Notification(
            type: NotificationType::SUCCESS,
            title: 'Title',
            timeToDestroy: null,
        ));

        self::assertNull($data['timeToDestroy']);
    }

    public function testNotificationUsesCorrectEnumValue(): void
    {
        $notification = new Notification(
            type: NotificationType::SUCCESS,
            title: 'Title',
        );

        self::assertSame(
            'notification_success',
            $notification->getType()
                ->value,
        );
        self::assertSame(
            'notification_success',
            $this->decode($notification)['type'],
        );
    }

    public function testNotificationJsonEncodingDoesNotThrowException(): void
    {
        $notification = new Notification(
            type: NotificationType::SUCCESS,
            title: 'Title with "quotes" and \'apostrophes\'',
            message: 'Message with special chars: <>&"',
        );

        self::assertJson($notification->toJson());
    }

    /**
     * @return array<array-key, mixed>
     */
    private function decode(Notification $notification): array
    {
        $data = json_decode(
            json: $notification->toJson(),
            associative: true,
            depth: 512,
            flags: JSON_THROW_ON_ERROR,
        );
        Assert::isArray($data);

        return $data;
    }
}
