<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Form\RegistrationFormType;
use App\Repository\UserRepository;
use App\Service\FlashMessage\NotificationService;
use App\Service\Mail\VerificationEmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use SymfonyCasts\Bundle\VerifyEmail\Exception\ExpiredSignatureException;
use SymfonyCasts\Bundle\VerifyEmail\Exception\VerifyEmailExceptionInterface;

final class RegistrationController extends AbstractController
{
    public function __construct(
        private readonly VerificationEmailService $verificationEmailService,
        private readonly UserRepository $userRepository,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly NotificationService $notificationService,
    ) {
    }

    #[Route('/app/{_locale}/register', name: 'app_register', methods: [
        'GET',
        'POST',
    ])]
    public function register(
        Request $request,
        EntityManagerInterface $entityManager,
    ): Response {
        if ($this->getUser() instanceof UserInterface) {
            return $this->redirectToRoute('app_dashboard');
        }

        $user = new User();
        $form = $this->createForm(
            type: RegistrationFormType::class,
            data: $user,
        );
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $existing = $this->userRepository->findOneBy(
                ['email' => $user->getEmail()],
            );

            if ($existing instanceof User) {
                // The message never reaches the page: the template renders one generic
                // error for any failure, so nothing here spells out what went wrong.
                $form->addError(new FormError('duplicate_email'));

                return $this->render(
                    view: 'website/register.html.twig',
                    parameters: ['registrationForm' => $form],
                    response: new Response(
                        status: Response::HTTP_UNPROCESSABLE_ENTITY,
                    ),
                );
            }

            $user->setLanguage($request->getLocale());

            $entityManager->persist($user);
            $entityManager->flush();

            $this->verificationEmailService->sendVerificationEmail($user);

            return $this->redirectToRoute('app_register_check_email');
        }

        return $this->render(view: 'website/register.html.twig', parameters: [
            'registrationForm' => $form,
        ]);
    }

    #[Route('/app/{_locale}/register/check-email', name: 'app_register_check_email', defaults: ['resent' => false], methods: [
        'GET',
    ])]
    #[Route('/app/{_locale}/register/check-email/resent', name: 'app_register_check_email_resent', defaults: ['resent' => true], methods: [
        'GET',
    ])]
    public function checkEmail(
        #[MapQueryParameter('resent')]
        bool $resent = false,
    ): Response {
        return $this->render(
            view: 'website/register/check-email.html.twig',
            parameters: [
                'resent' => $resent,
            ],
        );
    }

    #[Route(
        '/app/register/verify-email',
        name: 'app_register_verify_email',
        methods: [
            'GET',

        ],
    )]
    public function verifyUserEmail(
        Request $request,
        #[MapQueryParameter]
        ?int $id = null,
    ): RedirectResponse {
        $user = $id === null ? null : $this->userRepository->find($id);

        if ($user === null) {
            return $this->redirectToRoute('app_register');
        }

        $wasVerified = $user->getIsVerified();

        try {
            $this->verificationEmailService->handleEmailConfirmation(
                request: $request,
                user: $user,
            );
        } catch (ExpiredSignatureException) {
            if ($wasVerified) {
                return $this->alreadyConfirmed();
            }

            // An expired link is not the visitor's fault, so send a fresh one.
            $this->verificationEmailService->sendVerificationEmail($user);

            return $this->redirectToRoute('app_register_check_email_resent');
        } catch (VerifyEmailExceptionInterface) {
            $this->notificationService->addErrorNotification(
                message: $this->translator->trans(
                    id: 'registration.verify.failed',
                    parameters: [],
                    domain: 'security',
                ),
                timeToDestroy: null,
            );

            return $this->redirectToRoute('app_register');
        }

        if ($this->getUser() instanceof UserInterface) {
            return $this->redirectToRoute('app_dashboard');
        }

        if ($wasVerified) {
            return $this->alreadyConfirmed();
        }

        $this->security->login(user: $user, authenticatorName: 'remember_me');

        $this->notificationService->addWelcomeNotification();

        return $this->redirectToRoute('app_dashboard');
    }

    private function alreadyConfirmed(): RedirectResponse
    {
        $this->notificationService->addSuccessNotification(
            message: $this->translator->trans(
                id: 'login.already_verified',
                parameters: [],
                domain: 'security',
            ),
            timeToDestroy: null,
        );

        return $this->redirectToRoute('app_login');
    }
}
