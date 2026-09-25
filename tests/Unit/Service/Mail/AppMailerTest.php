<?php

declare(strict_types=1);

namespace App\Tests\Unit\Service\Mail;

use App\Entity\User;
use App\Service\Mail\AppMailer;
use Override;
use PHPUnit\Framework\TestCase;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use Webmozart\Assert\Assert;

final class AppMailerTest extends TestCase
{
    private const string NOTIFICATION_EMAIL = 'notifications@braunstetter.dev';

    private const string APP_NAME = 'Teeline';

    private const string DEFAULT_LOCALE = 'en';

    /**
     * @var list<TemplatedEmail>
     */
    private array $sentEmails = [];

    private AppMailer $appMailer;

    #[Override]
    protected function setUp(): void
    {
        $this->sentEmails = [];
        $this->appMailer = $this->createAppMailer();
    }

    public function testSendSetsLocaleOnEmail(): void
    {
        $this->appMailer->send(
            recipient: $this->createUser(),
            locale: 'de',
            subject: 'Subject',
            htmlTemplate: 'test.html.twig',
        );

        self::assertSame('de', $this->lastEmail()->getLocale());
    }

    public function testSendSetsRecipientFromUser(): void
    {
        $this->appMailer->send(
            recipient: $this->createUser(email: 'alice@example.com'),
            locale: 'en',
            subject: 'Subject',
            htmlTemplate: 'test.html.twig',
        );

        self::assertSame(
            'alice@example.com',
            $this->lastEmail()
                ->getTo()[0]
                ->getAddress(),
        );
    }

    public function testSendAddsRecipientNameToContext(): void
    {
        $this->appMailer->send(
            recipient: $this->createUser(firstname: 'Alice'),
            locale: 'en',
            subject: 'Subject',
            htmlTemplate: 'test.html.twig',
        );

        self::assertSame(
            'Alice',
            $this->lastEmail()
                ->getContext()['recipient_name'],
        );
    }

    public function testSendFallsBackToEmptyNameWhenFirstnameIsNull(): void
    {
        $this->appMailer->send(
            recipient: $this->createUser(firstname: null),
            locale: 'en',
            subject: 'Subject',
            htmlTemplate: 'test.html.twig',
        );

        self::assertSame(
            '',
            $this->lastEmail()
                ->getContext()['recipient_name'],
        );
    }

    public function testSendAddsAppNameToContext(): void
    {
        $this->appMailer->send(
            recipient: $this->createUser(),
            locale: 'en',
            subject: 'Subject',
            htmlTemplate: 'test.html.twig',
        );

        self::assertSame(
            self::APP_NAME,
            $this->lastEmail()
                ->getContext()['app_name'],
        );
    }

    public function testSendMergesCustomContext(): void
    {
        $this->appMailer->send(
            recipient: $this->createUser(firstname: 'Alice'),
            locale: 'en',
            subject: 'Subject',
            htmlTemplate: 'test.html.twig',
            context: [
                'seminar_title' => 'Rhetorik-Workshop',
            ],
        );

        $context = $this->lastEmail()
            ->getContext();
        self::assertSame('Rhetorik-Workshop', $context['seminar_title']);
        self::assertSame('Alice', $context['recipient_name']);
        self::assertSame(self::APP_NAME, $context['app_name']);
    }

    public function testSendSetsFromAddress(): void
    {
        $this->appMailer->send(
            recipient: $this->createUser(),
            locale: 'en',
            subject: 'Subject',
            htmlTemplate: 'test.html.twig',
        );

        $from = $this->lastEmail()
            ->getFrom()[0];
        self::assertSame(self::NOTIFICATION_EMAIL, $from->getAddress());
        self::assertSame(self::APP_NAME, $from->getName());
    }

    public function testSendSetsSubjectDirectly(): void
    {
        $this->appMailer->send(
            recipient: $this->createUser(),
            locale: 'en',
            subject: 'Your seat is confirmed',
            htmlTemplate: 'test.html.twig',
        );

        self::assertSame(
            'Your seat is confirmed',
            $this->lastEmail()
                ->getSubject(),
        );
    }

    public function testSendSetsHtmlTemplate(): void
    {
        $this->appMailer->send(
            recipient: $this->createUser(),
            locale: 'en',
            subject: 'Subject',
            htmlTemplate: 'app/email/security/reset_password_request.html.twig',
        );

        self::assertSame(
            'app/email/security/reset_password_request.html.twig',
            $this->lastEmail()
                ->getHtmlTemplate(),
        );
    }

    public function testSendFallsBackToDefaultLocaleWhenNull(): void
    {
        $this->appMailer->send(
            recipient: $this->createUser(),
            locale: null,
            subject: 'Subject',
            htmlTemplate: 'test.html.twig',
        );

        self::assertSame(self::DEFAULT_LOCALE, $this->lastEmail()->getLocale());
    }

    public function testSendToAddressFallsBackToDefaultLocaleWhenNull(): void
    {
        $this->appMailer->sendToAddress(
            recipientEmail: 'bob@example.com',
            recipientName: 'Bob Jones',
            locale: null,
            subject: 'Subject',
            htmlTemplate: 'test.html.twig',
        );

        self::assertSame(self::DEFAULT_LOCALE, $this->lastEmail()->getLocale());
    }

    public function testSendToAddressUsesProvidedValues(): void
    {
        $this->appMailer->sendToAddress(
            recipientEmail: 'bob@example.com',
            recipientName: 'Bob Jones',
            locale: 'fr',
            subject: 'Votre place est confirmée',
            htmlTemplate: 'test.html.twig',
        );

        $email = $this->lastEmail();
        self::assertSame('bob@example.com', $email->getTo()[0]->getAddress());
        self::assertSame('fr', $email->getLocale());
        self::assertSame('Votre place est confirmée', $email->getSubject());
        self::assertSame('Bob Jones', $email->getContext()['recipient_name']);
    }

    private function lastEmail(): TemplatedEmail
    {
        $email = array_last($this->sentEmails);
        Assert::isInstanceOf(value: $email, class: TemplatedEmail::class);

        return $email;
    }

    private function createAppMailer(): AppMailer
    {
        $mailer = self::createStub(MailerInterface::class);
        $mailer->method('send')
            ->willReturnCallback(function (TemplatedEmail $email): void {
                $this->sentEmails[] = $email;
            });

        return new AppMailer(
            mailer: $mailer,
            notificationEmail: self::NOTIFICATION_EMAIL,
            appName: self::APP_NAME,
            defaultLocale: self::DEFAULT_LOCALE,
        );
    }

    private function createUser(
        string $email = 'user@example.com',
        ?string $firstname = 'Test',
    ): User {
        $user = new User();
        $user->setEmail($email);

        if ($firstname !== null) {
            $user->setFirstname($firstname);
        }

        return $user;
    }
}
