<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\ChangePasswordFormType;
use App\Form\ResetPasswordRequestFormType;
use App\Repository\UserRepository;
use App\Service\FlashMessage\NotificationService;
use App\Service\Mail\AppMailer;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\ResetPassword\Controller\ResetPasswordControllerTrait;
use SymfonyCasts\Bundle\ResetPassword\Exception\ResetPasswordExceptionInterface;
use SymfonyCasts\Bundle\ResetPassword\Model\ResetPasswordToken;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;
use Webmozart\Assert\Assert;

final class ResetPasswordController extends AbstractController
{
    use ResetPasswordControllerTrait;

    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly TranslatorInterface $translator,
        private readonly AppMailer $appMailer,
        private readonly Security $security,
        #[Autowire(service: 'limiter.password_reset')]
        private readonly RateLimiterFactoryInterface $passwordResetLimiter,
        private readonly NotificationService $notificationService,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale,
    ) {
    }

    #[Route(
        '/app/{_locale}/reset-password',
        name: 'app_forgot_password_request',
        methods: [
            'GET',
            'POST',
        ],
    )]
    public function request(Request $request): Response
    {
        $form = $this->createForm(ResetPasswordRequestFormType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Guessing addresses costs a mail per try, so the attempts are capped per
            // client -- silently, because saying so would answer the guess.
            $limiter = $this->passwordResetLimiter->create(
                $request->getClientIp() ?? 'unknown',
            );

            if (! $limiter->consume()->isAccepted()) {
                return $this->redirectToRoute('app_reset_password_check_email');
            }

            $email = $form->get('email')
                ->getData();
            Assert::stringNotEmpty($email);

            return $this->processSendingPasswordResetEmail($email);
        }

        return $this->render(
            view: 'website/reset-password/request.html.twig',
            parameters: [
                'requestForm' => $form,
            ],
        );
    }

    /**
     * Confirmation page after a user has requested a password reset.
     */
    #[Route(
        '/app/{_locale}/reset-password/check-email',
        name: 'app_reset_password_check_email',
        methods: [
            'GET',
        ],
    )]
    public function checkEmail(): Response
    {
        // A fake token for whoever lands here without having asked: the page must look
        // the same for an address that exists and one that does not.
        $resetToken = $this->getTokenObjectFromSession();

        if (! $resetToken instanceof ResetPasswordToken) {
            $resetToken = $this->resetPasswordHelper->generateFakeResetToken();
        }

        return $this->render(
            view: 'website/reset-password/check-email.html.twig',
            parameters: [
                'resetToken' => $resetToken,
            ],
        );
    }

    /**
     * Validates and process the reset URL that the user clicked in their email.
     */
    #[Route(
        '/app/{_locale}/reset-password/reset/{token}',
        name: 'app_reset_password',
        methods: [
            'GET',
            'POST',
        ],
    )]
    public function reset(Request $request, ?string $token = null): Response
    {
        if ($token !== null) {
            // Into the session and out of the URL: otherwise the token sits in the
            // browser history, in the referrer and in every proxy log.
            $this->storeTokenInSession($token);

            return $this->redirectToRoute('app_reset_password');
        }

        $token = $this->getTokenFromSession();

        if ($token === null) {
            throw $this->createNotFoundException(
                'No reset password token found in the URL or in the session.',
            );
        }

        try {
            $user = $this->resetPasswordHelper->validateTokenAndFetchUser(
                $token,
            );
        } catch (ResetPasswordExceptionInterface $resetPasswordException) {
            // The reason is a full sentence on its own; the bundle's lead-in adds
            // nothing but a second one without a full stop between them.
            // Stays until dismissed: it names a next step, and three seconds is not
            // enough to read one.
            $this->notificationService->addErrorNotification(
                message: $this->translator->trans(
                    id: $resetPasswordException->getReason(),
                    parameters: [],
                    domain: 'ResetPasswordBundle',
                ),
                timeToDestroy: null,
            );

            return $this->redirectToRoute('app_forgot_password_request');
        }

        Assert::isInstanceOf(value: $user, class: User::class);

        $form = $this->createForm(
            type: ChangePasswordFormType::class,
            data: $user,
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Used once, then gone -- before the new password is stored, so a crash in
            // between cannot leave a live token behind.
            $this->resetPasswordHelper->removeResetRequest($token);

            // The form hashed it into the account already; the plaintext never
            // reached this scope, and so cannot reach a stack trace either.
            $this->entityManager->flush();

            $this->cleanSessionAfterReset();

            $this->security->login(
                user: $user,
                authenticatorName: 'remember_me',
            );

            return $this->redirectToRoute('app_dashboard');
        }

        return $this->render(
            view: 'website/reset-password/reset.html.twig',
            parameters: [
                'resetForm' => $form,
            ],
        );
    }

    private function processSendingPasswordResetEmail(
        string $email,
    ): RedirectResponse {
        // The same repository the firewall uses, so an unconfirmed account is as
        // invisible here as it is at the login form.
        $user = $this->userRepository->loadUserByIdentifier($email);

        if (! $user instanceof User) {
            return $this->redirectToRoute('app_reset_password_check_email');
        }

        try {
            $resetToken = $this->resetPasswordHelper->generateResetToken($user);
        } catch (ResetPasswordExceptionInterface) {
            return $this->redirectToRoute('app_reset_password_check_email');
        }

        $locale = $user->getLanguage();

        $this->appMailer->send(
            recipient: $user,
            locale: $locale,
            subject: $this->translator->trans(
                id: 'reset_password.subject',
                domain: 'email',
                locale: $locale,
            ),
            htmlTemplate: 'app/email/security/reset_password_request.html.twig',
            context: $this->createResetPasswordEmailParameters(
                resetToken: $resetToken,
                locale: $locale,
            ),
        );

        $this->setTokenObjectInSession($resetToken);

        return $this->redirectToRoute('app_reset_password_check_email');
    }

    /**
     * Built here, not in the template: the mail renders in a worker with no request.
     *
     * @return array{link: string}
     */
    private function createResetPasswordEmailParameters(
        ResetPasswordToken $resetToken,
        ?string $locale,
    ): array {
        return [
            'link' => $this->generateUrl(
                route: 'app_reset_password',
                parameters: [
                    '_locale' => $locale ?? $this->defaultLocale,
                    'token' => $resetToken->getToken(),
                ],
                referenceType: UrlGeneratorInterface::ABSOLUTE_URL,
            ),
        ];
    }
}
