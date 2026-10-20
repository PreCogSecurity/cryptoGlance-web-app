# Security Policy

## Supported versions

Only the latest release on the `master` branch receives security fixes.

## Reporting a vulnerability

Please do **not** open a public issue for security problems. Report them
privately to the maintainers (see the contact details on
<http://cryptoglance.info/>) with:

- a description of the issue and its impact,
- steps to reproduce (a proof of concept if possible),
- the affected version/commit.

You will receive an acknowledgement as soon as possible.

## Deployment hardening checklist

- Serve cryptoGlance over HTTPS. Session cookies are marked `Secure`
  automatically when the request is HTTPS.
- Set a strong account password on first login. Passwords are stored with
  PHP's `password_hash()` (bcrypt); legacy SHA-512 hashes are upgraded on the
  next successful login. Repeated failed logins are throttled.
- Keep `user_data/` writable by the web server only (775/664), never 777.
- Leave `CG_DEBUG` unset in production; PHP errors are written to
  `user_data/logs/php-error.log` instead of the browser.
- Leave `CG_INSECURE_TLS` unset unless you run a trusted local mirror with a
  self-signed certificate. TLS certificates are verified by default.
- Provide the MobileMiner key via the `MOBILEMINER_API_KEY` environment
  variable (see `.env.example`); never commit it to source.
- Do not expose the miner API port (default 4028) to the internet.
- Keep dependencies current: `composer update` and re-run the test suite.

## Automated checks

CI (`.github/workflows/ci.yml`) runs on every push and pull request:

- `php -l` syntax check over every PHP file,
- `vendor/bin/phpcs` (forbidden-function / discouraged-function sniffs),
- `vendor/bin/phpunit` (unit + regression suite),
- a hardcoded-credential scan over first-party PHP,
- `docker compose config` validation.
