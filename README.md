# Teeline

Shorthand was invented so that an office could write at the speed of speech. Teeline is that idea
for a Symfony application: the parts every project needs — accounts, mail, uploads, two languages,
a quality gate — already written and already tested, so a new project starts at the interesting
part.

Symfony 8.1 on PHP 8.5, served by [FrankenPHP](https://frankenphp.dev) in worker mode behind
[Caddy](https://caddyserver.com/), in Docker from development through to production.

## What is already in it

**Accounts.** Registration with e-mail verification, login throttled and behind a
[Cloudflare Turnstile](https://www.cloudflare.com/products/turnstile/) challenge, password reset,
and a profile that can change its e-mail address and delete its own account.

**A public half and an application half.** Everything under `/app` sits behind the firewall;
everything else has no firewall at all and can be cached by anything between the server and the
reader. Access is decided in the controller with `#[IsGranted]`, never in `access_control` — so a
new route under `/app` is open until somebody adds the attribute, and every controller that needs
an account carries a test that calls it signed out. That test is the guard, not the configuration.

**Mail that survives a slow provider.** Messenger queues it, a worker container consumes it,
[Mailpit](https://mailpit.axllent.org/) catches it in development, Brevo sends it in production.

**Uploads** through Flysystem with Liip Imagine for the variants, **German and English** per user,
and a signed-in dashboard whose empty state is waiting for the first domain screen.

**A quality gate that means it.** ECS, Rector, PHPStan at level 10 with 100% type coverage and a
cognitive-complexity ceiling, Psalm at errorLevel 1 plus a taint-analysis pass. 144 test methods
across unit, integration and functional levels.

**Ready for coding agents.** [AGENTS.md](AGENTS.md) writes down the conventions this codebase
actually follows, `.claude/` ships a skill that drives the running app, and a
[Dev Container](https://containers.dev/) plus [a one-page guide](docs/agents.md) get an agent
running against a local or a remote model.

## Getting started

```console
docker compose build --pull
make assets
docker compose up --wait
```

Then open `https://localhost` and accept the self-signed certificate — or
[trust it properly](docs/tls.md), which the Vite dev server needs anyway. Migrations run on boot.
`make assets` builds the Vite bundle, which is not committed.

```console
make            # every target, with descriptions
make test       # the suite
make code-check # the whole gate, no fixes
make dev        # vite with hot reloading on https://localhost:5173
```

One thing to keep in mind while writing services: the kernel outlives the request in worker mode,
so no service may hold request state. `AGENTS.md` says what that rules out, and what shape the test
for it has to take.

## Docs

1. [Conventions for this codebase](AGENTS.md)
2. [Voice — how the interface speaks](docs/voice.md)
3. [Using AI coding agents](docs/agents.md)
4. [Options available](docs/options.md)
5. [Starting from an existing project](docs/existing-project.md)
6. [Support for extra services](docs/extra-services.md)
7. [Deploying in production](docs/production.md)
8. [Debugging with Xdebug](docs/xdebug.md)
9. [TLS certificates](docs/tls.md)
10. [Using MySQL instead of PostgreSQL](docs/mysql.md)
11. [Using Alpine Linux instead of Debian](docs/alpine.md)
12. [Using the Makefile](docs/makefile.md)
13. [Updating the template](docs/updating.md)
14. [Troubleshooting](docs/troubleshooting.md)

## Credits

Built on [Symfony Docker](https://github.com/dunglas/symfony-docker) by
[Kévin Dunglas](https://dunglas.dev), which supplies the Docker, FrankenPHP and Caddy setup and
most of what sits under `docs/`.

## License

MIT — see [LICENSE](LICENSE).
