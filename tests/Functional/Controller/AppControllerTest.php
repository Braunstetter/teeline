<?php

declare(strict_types=1);

namespace App\Tests\Functional\Controller;

use App\Entity\User;
use App\Factory\UserFactory;
use App\Tests\Functional\FunctionalTestCase;
use Symfony\Component\HttpFoundation\Request;
use Webmozart\Assert\Assert;

final class AppControllerTest extends FunctionalTestCase
{
    public function testUserCanOpenTheDashboard(): void
    {
        $user = UserFactory::createOne();
        Assert::isInstanceOf(value: $user, class: User::class);

        $this->client->loginUser($user);
        $this->client->request(method: Request::METHOD_GET, uri: '/app');

        self::assertResponseIsSuccessful();
    }

    /**
     * Nothing but #[IsGranted] stands between the dashboard and the internet,
     * and an attribute can be forgotten. This is what notices.
     */
    public function testUserCannotAccessTheDashboardWhenUnauthenticated(): void
    {
        $this->client->request(method: Request::METHOD_GET, uri: '/app');

        self::assertResponseRedirects('/app/en/login');
    }

    public function testUserReachesTheApplicationFromTheBareDomain(): void
    {
        $this->client->request(method: Request::METHOD_GET, uri: '/');

        self::assertResponseRedirects('/app');
    }
}
