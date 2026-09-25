<?php

declare(strict_types=1);

namespace App\Tests\Integration\Service;

use App\Enum\NotificationType;
use App\Service\FlashMessage\NotificationService;
use Override;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Webmozart\Assert\Assert;

final class NotificationServiceTest extends KernelTestCase
{
    private NotificationService $notificationService;

    private Session $session;

    #[Override]
    protected function setUp(): void
    {
        self::bootKernel();

        $this->session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($this->session);

        /** @var RequestStack $requestStack */
        $requestStack = self::getContainer()->get(RequestStack::class);
        $requestStack->push($request);

        /** @var NotificationService $notificationService */
        $notificationService = self::getContainer()->get(
            NotificationService::class,
        );
        $this->notificationService = $notificationService;
    }

    public function testServiceCanAddWelcomeNotification(): void
    {
        $this->notificationService->addWelcomeNotification();

        $data = $this->firstFlash('notification_success');

        self::assertSame('notification_success', $data['type']);
        self::assertSame('Welcome to your admin panel', $data['title']);
        self::assertSame('You have successfully registered.', $data['message']);
        self::assertTrue($data['canBeDismissed']);
        self::assertSame(15, $data['timeToDestroy']);
        self::assertTrue($data['confetti']);
    }

    public function testServiceCanAddSuccessNotification(): void
    {
        $this->notificationService->addSuccessNotification(
            title: 'Custom Title',
            message: 'Custom Message',
        );

        $data = $this->firstFlash('notification_success');

        self::assertSame('notification_success', $data['type']);
        self::assertSame('Custom Title', $data['title']);
        self::assertSame('Custom Message', $data['message']);
        self::assertSame(3, $data['timeToDestroy']);
        self::assertFalse($data['confetti']);
    }

    public function testServiceUsesCorrectEnumValues(): void
    {
        $this->notificationService->addSuccessNotification();

        $data = $this->firstFlash('notification_success');

        self::assertSame(NotificationType::SUCCESS->value, $data['type']);
    }

    /**
     * @return array<array-key, mixed>
     */
    private function firstFlash(string $type): array
    {
        $flashes = $this->session->getFlashBag()
            ->get($type);
        self::assertCount(1, $flashes);

        $flash = $flashes[0];
        Assert::string($flash);

        $data = json_decode(
            json: $flash,
            associative: true,
            depth: 512,
            flags: JSON_THROW_ON_ERROR,
        );
        Assert::isArray($data);

        return $data;
    }
}
