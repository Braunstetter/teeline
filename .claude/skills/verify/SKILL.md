---
name: verify
description: Drive this app to observe a change at its surface — HTTP requests against the running containers, mails through Mailpit.
---

# Verify

The app runs in Docker. `docker compose ps` must show php, database, mailer and
mailer_worker healthy; `make start` brings them up, `make up` starts what is already built.

There **is** a test suite — `make test`, 74 tests. Reach for it first: it is faster, it
resets the database per test, and a feature is not done until it has one. What follows is
for the things a test cannot show you: the rendered page in a real browser, a mail as it
arrives in Mailpit, behaviour of the built asset bundle.

## Routes

Everything behind a login lives under `/app`. **The authentication pages carry a locale
segment**, the pages behind the session do not:

    /app/{_locale}/register                      GET|POST
    /app/{_locale}/login                         GET|POST
    /app/{_locale}/reset-password                GET|POST
    /app/{_locale}/reset-password/reset/{token}  GET|POST
    /app/register/verify-email                   GET
    /app/logout                                  ANY
    /app                                         GET   — dashboard
    /app/profile                                 GET|POST
    /{_locale}/profile/delete/success            GET   — outside /app, no firewall

`/app/login` without the segment is a **404**, not a redirect. `/` redirects to `/app`, and
`/app` while signed out redirects to `/app/{lang}/login` — the language taken from
`Accept-Language`. Inside the auth pages the URL segment decides, not the header.

`bin/console debug:router` is the authority; check it before writing a URL by hand.

## Driving HTTP

`https://localhost` (self-signed, so `-k`).

**`-H "Origin: https://localhost"` is required for every POST.** CSRF is stateless;
`SameOriginCsrfTokenManager` accepts the placeholder `csrf-token` as long as the request is
same-origin. Without the header the form rejects it with a 422.

Registration, fully formed:

```bash
curl -sk -o /dev/null -w "%{http_code} -> %{redirect_url}\n" \
  -H "Origin: https://localhost" \
  -X POST https://localhost/app/de/register \
  -d "registration_form[firstname]=Michael" -d "registration_form[lastname]=Brauner" \
  -d "registration_form[gender]=male" \
  -d "registration_form[email]=probe@example.com" \
  -d "registration_form[plainPassword][first]=Korrekt-Pferd-9182-xyz" \
  -d "registration_form[plainPassword][second]=Korrekt-Pferd-9182-xyz" \
  -d "registration_form[agreeTerms]=1" \
  -d "cf-turnstile-response=X.D.T.X" \
  -d "registration_form[_token]=csrf-token"
```

Names need **at least two characters** (`Assert\Length(min: 2)`) — single letters give a
422 that looks like a bug in the code under test. The Turnstile value can be anything: the
dev secret `1x000…` accepts every response.

**A fresh account cannot sign in.** Registration leaves `is_verified = false`, and the login
answers with a redirect back to the login page that looks exactly like a wrong password.
Either open the link from the confirmation mail, or set the flag:

```bash
docker compose exec -T database psql -U app -d app \
  -c "update \"user\" set is_verified = true where email = 'probe@example.com';"
```

Login posts `login_form[_username]`, `login_form[_password]`, `_csrf_token=csrf-token`
**and `cf-turnstile-response`** (any value, see above) to `/app/{_locale}/login`, and lands
on `/app`. Without the token the `TurnstileLoginListener` rejects before the password
check and redirects back to the login page — that is the captcha working, not a wrong
password.

The password reset runs over four requests: post the address to
`/app/{_locale}/reset-password`, take the link from the mail, open it (it drops the token
into the session and redirects to the same path **without** the token), then post
`change_password_form[plainPassword][first]` and `[second]` there **with the cookie jar from
that redirect** — without it the token is gone and the page 404s. The mail is written in the
account's language, so its link may carry a different locale than the one you registered
through.

## Reading mails

Mailpit's web interface is fixed at 32774.

```bash
curl -s "http://localhost:32774/api/v1/search?query=probe@example.com"   # → messages[].ID
curl -s "http://localhost:32774/api/v1/message/<ID>"                     # → .Subject, .HTML
```

**Mails are asynchronous.** Poll for the message instead of deleting the account right
after the request — two of my runs were worthless because I cleaned up before the worker
had delivered.

## Who is signed in

The profiler is no longer worth the trouble: the security panel does not carry the
identifier in the HTML, so grepping it finds nothing. Ask the app instead.

```bash
curl -sk -b jar -o /dev/null -w "%{http_code}\n" https://localhost/app   # 200 signed in, 302 not
curl -sk -b jar https://localhost/app/profile | grep -o 'id="profile_form_email"[^>]*value="[^"]*"'
```

The profile page prints the address into the email field's `value`, which is the shortest
answer to *who* is signed in.

## Gotchas

- **The worker holds its container in memory.** A newly registered service or Twig filter is
  unknown to it until `docker compose restart mailer_worker`; `cache:clear` does not reach it.
  It dies by itself after 60s (`--time-limit`), so waiting also works.
- Mail templates render **twice** — once when queued, once in the worker. A broken template
  turns the registration into a 500, the async transport does not shield it.
- Host ports are fixed in `compose.override.yaml`: database 32773, Mailpit 32774. Only
  Mailpit's SMTP port is still dynamic; nothing outside the containers needs it.
- Asset changes need `npm run build` — the page loads `/build/app-*.js`, not a dev server,
  unless `make dev` is running. **And the php worker holds the old asset manifest**: after a
  build the page keeps referencing the previous hashed filenames (by then 404s) until
  `docker compose restart php`.
- Static vendor state survives requests the same way. Imagine caches a failed Imagick
  probe in `DriverInfo::$instance` forever: every thumbnail URL 500s with "Imagick driver
  not installed" while the CLI happily lists 271 formats. `docker compose restart php`
  clears it — check that before hunting phantom install problems.

## Clean up

```bash
docker compose exec -T database psql -U app -d app -c "delete from \"user\" where email like '%example.com';"
```

Test mails may stay in Mailpit; they do no harm.
