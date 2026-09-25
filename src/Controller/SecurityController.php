<?php

declare(strict_types=1);

namespace App\Controller;

use App\Form\LoginFormType;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Core\User\UserInterface;

final class SecurityController extends AbstractController
{
    #[Route('/app/{_locale}/login', name: 'app_login', methods: [
        'GET',
        'POST',
    ])]
    public function login(Request $request): Response
    {
        if ($this->getUser() instanceof UserInterface) {
            return $this->redirectToRoute('app_dashboard');
        }

        $form = $this->createForm(LoginFormType::class);
        $form->handleRequest($request);

        return $this->render(view: 'website/login.html.twig', parameters: [
            'loginForm' => $form,
        ]);
    }
}
