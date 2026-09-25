# AGENTS.md

This is a Symfony project. Check `composer.json` for the exact Symfony/PHP version
in use, and read `symfony.lock` to see which recipes ran. Don't assume Doctrine,
Twig, API Platform, Messenger, or Lock are installed unless one of those says so.

## Ask before generating

If the task doesn't specify, ask rather than guess:

- Persistence: Doctrine ORM, Doctrine ODM, or none?
- Interface: server-rendered (Twig), API (Serializer, maybe API Platform), or both?
- Auth: SecurityBundle, and which authenticator?

If you can't ask (no interactive channel), state the assumption you're making and
pick the smallest option (e.g. no persistence layer) rather than scaffolding a
full stack nobody asked for.

## Adding features: Flex, not hand-wiring

Install new capabilities with `composer require <package>` (e.g. `symfony/lock`,
`symfony/messenger`, `orm-pack`) and let the Flex recipe register the bundle and
generate its config. Don't hand-edit `config/bundles.php` or hand-write a bundle's
base config; that's what the recipe is for. Don't skip a good-fit component just
because it isn't installed yet; installing it is one command.

That goes for PHP extensions and dev-only tools too: `composer require --dev
ext-fileinfo`, never a line typed into `composer.json`. A hand-edited `require`
changes the content hash without writing `composer.lock`, and `composer install`
then installs from a lock that no longer matches — a real package added that way
never arrives in CI at all. `make lint-composer` (`composer validate --strict`)
is the guard, and it runs in `make lint` and as its own CI job.

## Conventions

Follow https://symfony.com/doc/current/best_practices.html to write idiomatic
Symfony:

- Use PHP attributes for framework metadata, and not only on controllers:
  `#[Route]`, `#[MapRequestPayload]`, `#[IsGranted]` on actions, `#[Assert\...]`
  on properties, `#[AsCommand]`, `#[AsEventListener]`, `#[AsMessageHandler]`, and
  `#[AsAlias]` / `#[AsTaggedItem]` / `#[Autoconfigure]` on services. No YAML or
  XML routing.
- Rely on autowiring and autoconfiguration. Type-hint constructor arguments and
  let the container resolve them. Where a type-hint can't express it, stay in the
  class with `#[Autowire]` (parameters, env vars, expressions) or `#[Target]` (one
  of several implementations of an interface). A YAML service definition is the
  last resort, not the first.
- Controllers extend `AbstractController`, stay thin, and delegate to services.
- Use the framework for what it already does: Form for server-rendered forms,
  Validator for validation, Serializer for JSON, Messenger for async work,
  Security (voters, authenticators) for access control, Twig `path()`/`url()`
  instead of hardcoded URLs.
- Before hand-writing infrastructure (locks, queues, caches, HTTP clients,
  mailers, schedulers) or reaching for a third-party library, check whether a
  Symfony component covers it. It usually does.

Three specifics worth spelling out, because they are easy to get wrong:

- Bind request data with `#[MapRequestPayload]` / `#[MapQueryString]` on action
  arguments, which wires up Serializer and Validator for you, instead of calling
  `json_decode()` or `SerializerInterface` by hand. If neither package is
  installed yet, `composer require` them rather than falling back to manual
  parsing.
- Use constructor property promotion, and `readonly` for DTOs and value objects.
  Don't mark a service `readonly` if it might become `lazy: true`: a lazy proxy
  can't extend a `readonly` class.
- Use `symfony/lock` (`LockFactory`) for mutual exclusion. A hand-built flag or
  lock file looks fine in review and is usually wrong under concurrency.

## Routing and access

Everything that needs an account lives under `/app`, the authentication pages
included. Everything outside `/app` is public and has no firewall at all, so it
can be cached by anything between us and the reader.

    firewalls:
        main:                          # the application
            pattern: ^/app
        public:                        # marketing, catalogue, legal pages
            pattern: ^/(?!app)
            security: false

`access_control` stays empty. Access is decided in the controller with
`#[IsGranted('IS_AUTHENTICATED')]`, and with roles or voters where the cut is
finer. A new route under `/app` is therefore open until somebody adds the
attribute, so every controller that needs an account gets a test that calls it
signed out -- that test is the guard, not the configuration.

A public page cannot tell who is reading it. If one ever has to, move that route
under `main` deliberately.

## The worker mode

FrankenPHP serves this app from a worker: the kernel outlives the request. No service
may therefore keep request state -- no translation resolved once and kept, no active
path remembered, no static collector. Anything that has to remember something
implements `ResetInterface`, which the kernel resets between requests.

The test for it runs **two** passes through **one** instance, or it proves nothing.
`tests/Unit/Form/TurnstileFormTypeTest` shows the shape: one form factory, two locales
one after the other. With a fresh kernel per call the bug is invisible -- and being
invisible is exactly what defines this class of bug.

## Everyday workflow

- Run the app with `symfony serve -d`, and commands with `symfony console ...`
  (or `bin/console` when the Symfony CLI isn't available).
- When something fails, read `var/log/dev.log` and the web profiler
  (`/_profiler`) before changing code.
- If `maker-bundle` is installed, prefer `bin/console make:*` with every argument
  passed up front and `--no-interaction` where supported: makers prompt on a
  terminal by default, which hangs a non-interactive shell. If a maker still
  needs interactive input, hand-write the code instead.
- If Doctrine ORM is installed, schema changes go through migrations
  (`bin/console make:migration`, then `doctrine:migrations:migrate`), never
  `doctrine:schema:update` or hand-written SQL.
- `.env` is committed and holds defaults only. Real secrets belong in `.env.local`
  (git-ignored) or the secrets vault (`bin/console secrets:set`), read via
  `%env(...)%`.

## Templates

Attributes are rendered **only** through the `attr` macro, never through
`{{ block('attributes') }}` at the call site:

    {% import 'partials/macro/macro.html.twig' as std %}

    <form {{ std.attr(attr) }}>
    <div {{ std.attr(row_attr) }}>

The macro takes the map as a parameter, so a template with more than one of them --
a form and its button -- needs no `{% with {attr: …} %}` wrapper, and the reader sees
which map ends up on the tag. `partials/block/blocks.html.twig` still defines the block;
`macro.html.twig` is its only caller.

**Blocks and macros render in isolation, so the import has to sit inside each one
that calls `std`** -- a template-level import is invisible there and fails at runtime
with `Call to a member function getTemplateForMacro() on null`:

    {% block checkbox_row %}
        {% import 'partials/macro/macro.html.twig' as std %}
        <div {{ std.attr(row_attr) }}>
    {% endblock %}

A template pulled in with `{% use %}` (a Twig trait, like
`partials/block/_uploads_form_fields.html.twig`) may hold nothing but blocks -- a
template-level import there breaks the including template, and `lint:twig` does not
catch it. The test suite does.

## Testing

Install `symfony/test-pack` if it isn't already. Functional/HTTP tests extend
`WebTestCase`; service-level tests extend `KernelTestCase`. Run
`php bin/phpunit` (falls back to `vendor/bin/phpunit`). A feature isn't done
until it has a test that exercises it the way a caller would, an HTTP request for
a controller or a service call for a service, not just "it didn't throw."

## Code style and static analysis

`make code-check` is the gate: Twig/YAML/container linters, ECS, Rector in dry-run,
PHPStan and Psalm. `make ecs-fix` and `make rector-fix` apply what they can, and the
same four analysers run in CI before anything is deployed.

ECS owns the style here, not php-cs-fixer's `@Symfony` ruleset, so comparisons read
`$user === null` rather than Yoda. Any call passing more than one argument names them;
`NameArgumentsRector` in `utils/Rector/` does it for you. Symfony's BC promise does not
cover parameter names, so a minor upgrade can rename one -- PHPStan reports those call
sites, and it runs before anything is deployed.

PHPStan runs at level 10 with 100% type coverage and a cognitive-complexity ceiling,
Psalm at errorLevel 1 plus a taint-analysis pass. Where a value is genuinely wider than
the code needs, assert it with `Webmozart\Assert` instead of adding an `ignoreErrors`
entry; every exception in `phpstan.dist.neon` and `psalm.xml` carries a comment saying
why it is there.

## Discover, don't guess

Framework APIs change between versions and your training data may be stale. Look
things up in the project instead of relying on memory:

- `bin/console about`: versions, environment, paths.
- `bin/console debug:router`, `debug:container`, `debug:autowiring <name>`,
  `debug:config <bundle>`, `config:dump-reference <bundle>`: what exists and how
  it is configured.
- `bin/console lint:container`, plus `lint:twig templates/` and
  `lint:yaml config/` where those packages are installed: validate before running.
- Read the installed source and docblocks under `vendor/`.
- Docs: https://symfony.com/doc/current/ (switch to the version matching
  `composer.json` if it differs).
