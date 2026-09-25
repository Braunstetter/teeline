<?php

declare(strict_types=1);

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\Cache;
use Symfony\Component\Routing\Attribute\Route;

final class WebsiteController extends AbstractController
{
    #[Route('/', name: 'app_root', methods: ['GET'], stateless: true)]
    public function root(): RedirectResponse
    {
        return $this->redirectToRoute(route: 'app_dashboard');
    }

    #[Cache(maxage: 7200, public: true, mustRevalidate: true)]
    #[Route(
        '/{_locale}/profile/delete/success',
        name: 'app_profile_delete_success',
        methods: [
            'GET',
        ],
        stateless: true,
    )]
    public function deleteSuccess(): Response
    {
        return $this->render(view: 'website/profile/delete_success.html.twig');
    }
}
