<?php

declare(strict_types=1);

namespace App\Controller;

use App\Entity\User;
use App\Exception\EmailAlreadyInUseException;
use App\Exception\EmailChangeThrottledException;
use App\Exception\InvalidEmailChangeTokenException;
use App\Form\ProfileFormType;
use App\Service\EmailChangeService;
use App\Service\FlashMessage\NotificationService;
use App\Service\Upload\UploadService;
use App\Service\User\ProfileService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;
use Webmozart\Assert\Assert;

final class ProfileController extends AbstractController
{
    public function __construct(
        private readonly UploadService $uploadService,
        private readonly NotificationService $notificationService,
        private readonly TranslatorInterface $translator,
        private readonly Security $security,
        private readonly ProfileService $profileService,
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailChangeService $emailChangeService,
    ) {
    }

    #[IsGranted('IS_AUTHENTICATED')]
    #[Route('/app/profile', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $user = $this->getUser();
        Assert::isInstanceOf(value: $user, class: User::class);

        $form = $this->createForm(type: ProfileFormType::class, data: $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->uploadService->uploadFromForm($form);

            $this->entityManager->persist($user);
            $this->entityManager->flush();

            $newEmail = $form->get('email')
                ->getData();
            Assert::string($newEmail);

            $this->handleEmailChange(
                newEmail: $newEmail,
                user: $user,
                locale: $request->getLocale(),
            );

            return $this->redirectToRoute('app_profile');
        }

        return $this->render(view: 'app/profile/index.html.twig', parameters: [
            'form' => $form,
        ]);
    }

    /**
     * POST only, and behind a token tied to this account: a GET would let any page on
     * the internet delete the reader's account by embedding an image.
     */
    #[IsGranted('IS_AUTHENTICATED')]
    #[Route('/app/profile/delete', name: 'app_profile_delete', methods: [
        'POST',
    ])]
    public function delete(Request $request): RedirectResponse
    {
        $user = $this->getUser();
        Assert::isInstanceOf(value: $user, class: User::class);

        $userId = $user->getId();
        Assert::notNull($userId);

        $token = $request->request->get('_token');
        Assert::nullOrString($token);

        if (! $this->isCsrfTokenValid(
            id: 'delete_user_' . $userId,
            token: $token,
        )) {
            return $this->redirectToRoute(
                route: 'app_profile',
                status: Response::HTTP_SEE_OTHER,
            );
        }

        $this->security->logout(false);
        $this->profileService->remove($user);

        return $this->redirectToRoute(
            route: 'app_profile_delete_success',
            status: Response::HTTP_SEE_OTHER,
        );
    }

    /**
     * Reached from the mail, so it carries no #[IsGranted]: whoever opens the link may
     * well not be signed in. The token alone decides whose address moves.
     */
    #[Route(
        '/app/profile/confirm-email/{token}',
        name: 'app_profile_confirm_email',
        methods: [
            'GET',

        ],
    )]
    public function confirmEmail(
        Request $request,
        string $token,
    ): RedirectResponse {
        try {
            $user = $this->emailChangeService->confirmEmailChange($token);

            // Only when nobody is signed in. Logging in unconditionally would hand the
            // session to whoever opened a forwarded link.
            if (! $this->getUser() instanceof UserInterface) {
                $this->security->login(
                    user: $user,
                    authenticatorName: 'remember_me',
                );
            }

            $this->notifyEmailChangeConfirmed($user);
        } catch (InvalidEmailChangeTokenException) {
            $this->notifyEmailChangeError();
        } catch (EmailAlreadyInUseException) {
            // Whoever opens the link may not be signed in, so there is no user
            // to ask. UserLocaleListener has already put their language here.
            $this->notifyEmailAlreadyInUse($request->getLocale());
        }

        return $this->redirectToRoute('app_profile');
    }

    private function handleEmailChange(
        string $newEmail,
        User $user,
        string $locale,
    ): void {
        if ($newEmail === $user->getEmail()) {
            $this->notifyProfileSaved($user);

            return;
        }

        try {
            $this->emailChangeService->requestEmailChange(
                user: $user,
                newEmail: $newEmail,
            );
            $this->notifyEmailChangePending($user);
        } catch (EmailAlreadyInUseException) {
            $this->notifyEmailAlreadyInUse($locale);
        } catch (EmailChangeThrottledException) {
            $this->notifyEmailChangeThrottled($user);
        }
    }

    private function notifyEmailChangePending(User $user): void
    {
        $locale = $user->getLanguage();

        $this->notificationService->addSuccessNotification(
            title: $this->translator->trans(
                id: 'profile.form.email_change.pending.title',
                domain: 'user',
                locale: $locale,
            ),
            message: $this->translator->trans(
                id: 'profile.form.email_change.pending.message',
                domain: 'user',
                locale: $locale,
            ),
            timeToDestroy: null,
        );
    }

    private function notifyEmailAlreadyInUse(string $locale): void
    {
        $this->notificationService->addErrorNotification(
            message: $this->translator->trans(
                id: 'profile.form.email_change.already_in_use',
                domain: 'user',
                locale: $locale,
            ),
            timeToDestroy: null,
        );
    }

    private function notifyEmailChangeThrottled(User $user): void
    {
        $this->notificationService->addErrorNotification(
            message: $this->translator->trans(
                id: 'profile.form.email_change.throttled',
                domain: 'user',
                locale: $user->getLanguage(),
            ),
            timeToDestroy: null,
        );
    }

    private function notifyEmailChangeConfirmed(User $user): void
    {
        $locale = $user->getLanguage();

        $this->notificationService->addSuccessNotification(
            title: $this->translator->trans(
                id: 'profile.form.email_change.confirmed.title',
                domain: 'user',
                locale: $locale,
            ),
            message: $this->translator->trans(
                id: 'profile.form.email_change.confirmed.message',
                domain: 'user',
                locale: $locale,
            ),
        );
    }

    private function notifyEmailChangeError(): void
    {
        $this->notificationService->addErrorNotification(
            title: $this->translator->trans(
                id: 'profile.form.email_change.error.title',
                domain: 'user',
            ),
            message: $this->translator->trans(
                id: 'profile.form.email_change.error.message',
                domain: 'user',
            ),
            timeToDestroy: null,
        );
    }

    private function notifyProfileSaved(User $user): void
    {
        $locale = $user->getLanguage();

        $this->notificationService->addSuccessNotification(
            title: $this->translator->trans(
                id: 'profile.form.success.title',
                domain: 'user',
                locale: $locale,
            ),
            message: $this->translator->trans(
                id: 'profile.form.success.message',
                domain: 'user',
                locale: $locale,
            ),
        );
    }
}
