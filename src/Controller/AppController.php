<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

final class AppController extends AbstractController
{
    #[Route('/app', name: 'app_dashboard', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED')]
    public function dashboard(): Response
    {
        return $this->render(view: 'app/dashboard.html.twig');
    }
}
