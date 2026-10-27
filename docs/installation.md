---
title: Installation
order: 10
---

# Installation

## Requirements

| Package | Version |
|---|---|
| PHP | ^8.4 |
| Laravel (`illuminate/auth`, `config`, `contracts`, `http`, `routing`, `support`) | 12 or 13 |
| `omnisense/omnibase` | ^4.0 |
| `omnisense/omnibridge`, `omnibridge/twig`, `omnibridge/doctrine` | ^1.0 |
| `omnisense/omnidrive` | ^1.0 |
| `doctrine/orm` ^3.4, `doctrine/dbal` ^4.2 | |
| `symfony/cache`, `mailer`, `routing`, `translation`, `yaml` | 7 or 8 |
| `twig/twig` | ^3.10 |
| `psr/clock`, `psr/event-dispatcher` | ^1.0 |

omnibase asks for the extensions its own packages need (bcmath, intl, gd, exif, ftp, imagick,
igbinary). Suggested, each wired only when installed: `omnisense/omniauth` (with `omniauth/webauthn`,
`omniauth/otp`, `omniauth/google`), `omnisense/omnimap`, `omnisense/omnishield` (with
`omnishield/altcha`), `omnibase/consent`, `omnibridge/static`.

```sh
composer config allow-plugins.omnibridge/composer true
composer require omnisense/omnibase:4.x-dev omnibridge/laravel:1.x-dev
php artisan vendor:publish --tag=omnibridge-config     # config/omnibridge.php
php artisan vendor:publish --tag=omniauth-config       # config/omniauth.php, for the sign-in
```

`omnibridge/composer` is the Composer plugin omnisense/omnibase declares its patches and stubs through
([omnibridge/composer](https://github.com/glitchr-omnisense/omnibridge-composer)).

## The provider

`Omnibridge\Laravel\OmnibridgeServiceProvider` is discovered by Laravel (`extra.laravel.providers`).

- `register()`: merges `config/omnibridge.php`, binds every contract and service
  ([Contracts](contracts.md)), Doctrine, Twig and the parameters; registers
  `Auth\AuthServiceProvider` when omnisense/omniauth is installed, `Map\MapServiceProvider` when
  omnisense/omnimap is, `Shield\ShieldServiceProvider` when omnisense/omnishield is (each wires
  nothing until its configuration turns it on).
- `boot()`: `Base\Contract\Runtime::setContainer($app)` - the application itself is the registry's
  container; with `omnibridge.media` on, omnibase's media and their image routes; the
  `omnibridge-config` publication; `omnibridge:static:build` in the console when omnibridge/static is
  installed.

What the application adds itself, as its needs say: `Http\Localize` in the web group
(`bootstrap/app.php`: `$middleware->web(append: [Localize::class])`, with `LANG` left out of the
encrypted cookies), the `_switch_locale` route, its actions' routes (`ActionRoutes::register()`), its
`#[AsAlias]` bindings (`Aliases::bind()`) - [Routing and actions](routing.md).

## A run

A Testbench application with the provider, two locales, an ICU catalogue and a route (PHP 8.4.26,
Laravel 13.38, omnisense/omnibase 4.x-dev, in the package's image `omnibridge-laravel:php8.4`,
2026-10-27):

```yaml
# translations/messages+intl-icu.fr.yaml
menu:
    home: 'Accueil'
    welcome: 'Bonjour {name}'
```

```php
<?php

require 'vendor/autoload.php';

use Base\Contract\Runtime;
use Illuminate\Support\Facades\Route;
use Omnibridge\Contract\Access;
use Omnibridge\Contract\Locales;
use Omnibridge\Contract\Urls;
use Omnibridge\Laravel\OmnibridgeServiceProvider;
use Orchestra\Testbench\Foundation\Application;

$app = Application::create(options: ['extra' => ['providers' => [OmnibridgeServiceProvider::class]]]);
$app['config']->set('omnibridge.locales.available', ['fr-FR', 'en-GB']);
$app['config']->set('omnibridge.translations.paths', [__DIR__.'/translations']);
$app->setLocale('fr');
Route::get('/articles/{slug}', static fn () => '')->name('article');
$app['router']->getRoutes()->refreshNameLookups();

echo Runtime::urls()->path('article', ['slug' => 'le-fuji']), "\n";
echo get_class(Runtime::urls()), ' ', var_export(app(Urls::class) === Runtime::urls(), true), "\n";
echo Runtime::locales()->getLocale(), ' ', implode(',', Runtime::locales()->getAvailableLocales()), "\n";
echo Runtime::translations()->trans('@messages.menu.welcome', ['name' => 'Ada']), ' | ', var_export(Runtime::translations()->exists('menu.home'), true), ' | "', Runtime::translations()->trans('@messages.menu.missing'), "\"\n";
echo var_export(Runtime::access()->isGranted('ROLE_ADMIN'), true), ' ', var_export(Runtime::access()->getUser(), true), "\n";
echo Runtime::environment()->name(), ' ', get_class(Runtime::clock()), ' ', get_class(Runtime::events()), "\n";
echo get_class(Runtime::parameters()), ' ', Runtime::parameters()->get('app.locale'), "\n";
```

```text
/articles/le-fuji
Omnibridge\Laravel\RouterUrls true
fr-FR fr-FR,en-GB
Bonjour Ada | true | ""
false NULL
workbench Omnibridge\Laravel\Contract\DateClock Omnibridge\Laravel\Contract\DispatcherEvents
Base\Service\HotParameters fr
```

- The registry and the container answer one object.
- Laravel's `fr` is the site's `fr-FR`; the default first.
- A key no catalogue holds is printed as nothing, as omnibase's Translator answers it.
- Nobody signed in: no role, no user. `workbench` is Testbench's environment name.
- `Runtime::parameters()` is omnibase's `HotParameters` over Laravel's config.
