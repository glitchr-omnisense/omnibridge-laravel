---
title: Verified, and not
order: 90
---

# Verified, and not

## What the tests prove

Run on 2026-10-27 in `omnibridge-laravel:php8.4` (PHP 8.4.26, PHPUnit 12.5.40, Laravel 13.38,
omnisense/omnibase 4.x-dev), the checkout's own `vendor/`, no network: 114 tests, 736 assertions, green,
1 skipped (`OmnibaseTwigTest::testTheCookieAnswerOnTheSitesConfiguration`: omnibase/consent not
installed there). The [Installation](installation.md#a-run) page's script was run in the same image that
day and its output pasted.

## Verified elsewhere, as the README and the commits state

- lesringostars' Laravel twin, which renders the Symfony site's blog and articles from the same database
  and templates, and its parity suite against the Symfony site.
- omnibase's mapping: the metadata of 94 classes equal to Symfony's (`OmnibaseMappingTest`, the records
  from omnisense/omnibase's Symfony harness).
- `omnibridge:static:build` on lesringostars-laravel (2026-10-24, the working copy loaded by hand): the
  twin's home, intro, destinations, two articles and the travels pages rendered by Laravel and written,
  the routes read from lesringostars' actions; not a whole site.

## What is not verified

- Laravel 12: composer.json admits it, the suite runs on Laravel 13.
- A real identity provider, a real passkey authenticator: simulated in the tests.
- omnimap providers other than OpenStreetMap through this wiring; the map's export and suppression
  (omnibridge/symfony's).
- The bindings under a long-running worker (Octane, FrankenPHP's worker mode): singletons such as
  `RouterUrls`' manifest, the Twig environment and omnishield's `WidgetPrinter` live for the worker.
- PostgreSQL and SQL Server connections: `Doctrine\Connections` maps them, the suite uses SQLite.
