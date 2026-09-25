<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class AppMailer
{
    public function __construct(
        private MailerInterface $mailer,
        #[Autowire(param: 'notification_email')]
        private string $notificationEmail,
        #[Autowire(param: 'app_name')]
        private string $appName,
        #[Autowire(param: 'kernel.default_locale')]
        private string $defaultLocale,
    ) {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function send(
        User $recipient,
        ?string $locale,
        string $subject,
        string $htmlTemplate,
        array $context = [],
    ): void {
        $this->sendToAddress(
            recipientEmail: (string) $recipient->getEmail(),
            recipientName: $recipient->getFirstname() ?? '',
            locale: $locale ?? $this->defaultLocale,
            subject: $subject,
            htmlTemplate: $htmlTemplate,
            context: $context,
        );
    }

    /**
     * @param array<string, mixed> $context
     */
    public function sendToAddress(
        string $recipientEmail,
        string $recipientName,
        ?string $locale,
        string $subject,
        string $htmlTemplate,
        array $context = [],
    ): void {
        $locale ??= $this->defaultLocale;
        $email = new TemplatedEmail()
            ->from(
                new Address(
                    address: $this->notificationEmail,
                    name: $this->appName,
                ),
            )
            ->to($recipientEmail)
            ->subject($subject)
            ->htmlTemplate($htmlTemplate)
            ->context([
                'recipient_name' => $recipientName,
                'app_name' => $this->appName,
                ...$context,
            ])
            ->locale($locale);

        $this->mailer->send($email);
    }
}
