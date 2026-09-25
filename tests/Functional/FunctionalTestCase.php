<?php

declare(strict_types=1);

namespace App\Tests\Functional;

use Override;
use Psr\Cache\CacheItemPoolInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\Session\Flash\FlashBagInterface;
use Symfony\Component\HttpFoundation\Session\FlashBagAwareSessionInterface;
use Symfony\Component\Mime\BodyRendererInterface;
use Webmozart\Assert\Assert;
use Zenstruck\Foundry\Test\Factories;
use Zenstruck\Foundry\Test\ResetDatabase;

/**
 * A booted client and the mail helpers every functional test needs. These used to be a
 * trait, which no language server can resolve: a trait has no parent, so getContainer()
 * and the assertions look undefined until a class pulls it in.
 */
abstract class FunctionalTestCase extends WebTestCase
{
    use Factories;
    use ResetDatabase;

    protected KernelBrowser $client;

    #[Override]
    protected function setUp(): void
    {
        $this->client = self::createClient();

        // Rate limiters live in a cache pool, not in the database, so the transaction
        // rollback between tests does not reach them: one test that exhausts a limit
        // would otherwise make the next one fail depending on the order they run in.
        $rateLimiterCache = self::getContainer()->get('cache.rate_limiter');
        Assert::isInstanceOf(
            value: $rateLimiterCache,
            class: CacheItemPoolInterface::class,
        );
        $rateLimiterCache->clear();
    }

    /**
     * The mail at the given position, typed: getMailerMessage() widens it to null
     * and to RawMessage, and neither is ever what this app sends.
     */
    protected static function getSentEmail(int $index = 0): TemplatedEmail
    {
        $message = self::getMailerMessage($index);
        Assert::isInstanceOf(value: $message, class: TemplatedEmail::class);

        return $message;
    }

    /**
     * Renders a queued mail the way the worker would. The test transport keeps messages
     * unrendered, so this is the only place the mail template is exercised at all.
     */
    protected static function getMailerMessageHtml(int $index): string
    {
        $message = self::getSentEmail($index);

        // The interface is a private alias Symfony does not expose, hence the service id.
        $renderer = self::getContainer()->get('twig.mime_body_renderer');
        Assert::isInstanceOf(
            value: $renderer,
            class: BodyRendererInterface::class,
        );
        $renderer->render($message);

        $html = $message->getHtmlBody();
        Assert::string($html);

        return $html;
    }

    /**
     * Returns the first absolute URL in the mail whose href contains the given path.
     */
    protected static function extractEmailUrl(
        string $html,
        string $path,
    ): string {
        preg_match(
            pattern: '#href="([^"]*' . preg_quote(
                str: $path,
                delimiter: '#',
            ) . '[^"]*)"#',
            subject: $html,
            matches: $matches,
        );
        self::assertNotEmpty(
            $matches,
            sprintf('No URL containing "%s" found in the mail.', $path),
        );

        $url = html_entity_decode($matches[1]);
        self::assertStringStartsWith(
            'http',
            $url,
            sprintf('Expected an absolute URL, got "%s".', $url),
        );

        return $url;
    }

    /**
     * The flash bag of the session the last request ran in. getSession() is typed as
     * SessionInterface, which knows nothing of flashes, so the narrowing happens here
     * rather than in every test that reads a notification.
     */
    protected function flashBag(): FlashBagInterface
    {
        $session = $this->client->getRequest()
            ->getSession();
        Assert::isInstanceOf(
            value: $session,
            class: FlashBagAwareSessionInterface::class,
        );

        return $session->getFlashBag();
    }
}
