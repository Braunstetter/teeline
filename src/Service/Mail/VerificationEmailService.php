<?php

declare(strict_types=1);

namespace App\Service\Mail;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;
use SymfonyCasts\Bundle\VerifyEmail\VerifyEmailHelperInterface;
use Webmozart\Assert\Assert;

final readonly class VerificationEmailService
{
    public function __construct(
        private VerifyEmailHelperInterface $verifyEmailHelper,
        private AppMailer $mailer,
        private EntityManagerInterface $entityManager,
        private TranslatorInterface $translator,
    ) {
    }

    public function sendVerificationEmail(User $user): void
    {
        // Nothing left to confirm, so no link is sent -- otherwise an old, expired
        // link would keep triggering mail to an address that is long since confirmed.
        if ($user->getIsVerified()) {
            return;
        }

        // Casting a missing id would sign an empty one, and the link would match no account.
        Assert::notNull($user->getId());
        Assert::stringNotEmpty($user->getEmail());

        $signature = $this->verifyEmailHelper->generateSignature(
            routeName: 'app_register_verify_email',
            userId: (string) $user->getId(),
            userEmail: $user->getEmail(),
            extraParams: ['id' => $user->getId()],
        );

        $locale = $user->getLanguage();

        $this->mailer->send(
            recipient: $user,
            locale: $locale,
            subject: $this->translator->trans(
                id: 'verification.subject',
                domain: 'email',
                locale: $locale,
            ),
            htmlTemplate: 'app/email/security/verification.html.twig',
            context: [
                'link' => $signature->getSignedUrl(),
            ],
        );
    }

    /**
     * @throws VerifyEmailExceptionInterface
     */
    public function handleEmailConfirmation(Request $request, User $user): void
    {
        Assert::notNull($user->getId());
        Assert::stringNotEmpty($user->getEmail());

        $this->verifyEmailHelper->validateEmailConfirmationFromRequest(
            request: $request,
            userId: (string) $user->getId(),
            userEmail: $user->getEmail(),
        );

        $user->setIsVerified(true);
        $this->entityManager->flush();
    }
}
