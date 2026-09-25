<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\EmailChangeRequest;
use App\Entity\User;
use App\Exception\EmailAlreadyInUseException;
use App\Exception\EmailChangeThrottledException;
use App\Exception\InvalidEmailChangeTokenException;
use App\Repository\EmailChangeRequestRepository;
use App\Repository\UserRepository;
use App\Service\Mail\AppMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Clock\DatePoint;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

final readonly class EmailChangeService
{
    private const int TOKEN_LIFETIME_HOURS = 24;

    public function __construct(
        private EntityManagerInterface $entityManager,
        private EmailChangeRequestRepository $emailChangeRequestRepository,
        private UserRepository $userRepository,
        private AppMailer $appMailer,
        private TranslatorInterface $translator,
        private UrlGeneratorInterface $urlGenerator,
        #[Autowire(service: 'limiter.email_change')]
        private RateLimiterFactoryInterface $emailChangeLimiter,
    ) {
    }

    /**
     * The address is not written here: it moves only once the link in the mail to the
     * new address has been opened. Until then the account keeps the old one.
     */
    public function requestEmailChange(User $user, string $newEmail): void
    {
        if ($newEmail === $user->getEmail()) {
            return;
        }

        $limiter = $this->emailChangeLimiter->create((string) $user->getId());
        if (! $limiter->consume()->isAccepted()) {
            throw new EmailChangeThrottledException();
        }

        $existingUser = $this->userRepository->findOneBy([
            'email' => $newEmail,
        ]);
        if ($existingUser instanceof User) {
            throw new EmailAlreadyInUseException();
        }

        // A second request replaces the first, so an abandoned link stops working.
        $this->emailChangeRequestRepository->removeAllForUser($user);

        $token = bin2hex(random_bytes(32));
        $expiresAt = new DatePoint(sprintf(
            '+%d hours',
            self::TOKEN_LIFETIME_HOURS,
        ));

        $request = new EmailChangeRequest(
            user: $user,
            newEmail: $newEmail,
            expiresAt: $expiresAt,
            token: $token,
        );
        $this->entityManager->persist($request);
        $this->entityManager->flush();

        $this->sendConfirmationEmail(
            user: $user,
            newEmail: $newEmail,
            token: $token,
        );
        $this->sendNotificationEmail($user);
    }

    public function confirmEmailChange(string $token): User
    {
        $request = $this->emailChangeRequestRepository->findValidByToken(
            $token,
        );

        if (! $request instanceof EmailChangeRequest) {
            throw new InvalidEmailChangeTokenException();
        }

        $user = $request->getUser();
        $newEmail = $request->getNewEmail();

        // Checked again: somebody else may have taken the address in the meantime.
        $existingUser = $this->userRepository->findOneBy([
            'email' => $newEmail,
        ]);
        if ($existingUser instanceof User) {
            throw new EmailAlreadyInUseException();
        }

        $user->setEmail($newEmail);

        $this->emailChangeRequestRepository->removeAllForUser($user);
        $this->entityManager->flush();

        return $user;
    }

    private function sendConfirmationEmail(
        User $user,
        string $newEmail,
        string $token,
    ): void {
        $link = $this->urlGenerator->generate(
            name: 'app_profile_confirm_email',
            parameters: [
                'token' => $token,
            ],
            referenceType: UrlGeneratorInterface::ABSOLUTE_URL,
        );

        $firstname = $user->getFirstname();
        Assert::string($firstname);

        $this->appMailer->sendToAddress(
            recipientEmail: $newEmail,
            recipientName: $firstname,
            locale: $user->getLanguage(),
            subject: $this->translator->trans(
                id: 'email_change.confirmation.subject',
                domain: 'email',
                locale: $user->getLanguage(),
            ),
            htmlTemplate: 'app/email/security/email_change_confirmation.html.twig',
            context: [
                'link' => $link,
            ],
        );
    }

    /**
     * The old address hears about it too, so a stolen session cannot move an account
     * away quietly.
     */
    private function sendNotificationEmail(User $user): void
    {
        $locale = $user->getLanguage();

        $this->appMailer->send(
            recipient: $user,
            locale: $locale,
            subject: $this->translator->trans(
                id: 'email_change.notification.subject',
                domain: 'email',
                locale: $locale,
            ),
            htmlTemplate: 'app/email/security/email_change_notification.html.twig',
        );
    }
}
