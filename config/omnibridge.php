<?php

// omnibridge/laravel: what omnibase's Fondation is answered with in this
// application (php artisan vendor:publish --tag=omnibridge-config).

return [
    // The site's languages in omnibase's form (fr-FR, en-GB...), and the default
    // one (the first when null). Laravel's locale (fr) names one of them.
    'locales' => [
        'available' => [],
        'default' => null,
    ],

    // The ICU catalogues' folders, the later ones over the earlier (the core's,
    // the bundles', the site's last), and the language of a missing message
    // (app.fallback_locale when null).
    'translations' => [
        'paths' => [],
        'fallback' => null,
    ],

    // The asset manifest a public file's address is read in (a build's
    // manifest.json), none when null.
    'assets' => [
        'manifest' => null,
    ],

    // Twig over the site's templates: [folder, namespace] pairs (null for the
    // main namespace). No Twig\Environment is bound without one.
    'twig' => [
        'paths' => [],
        'cache' => null,
        'timezone' => null,
    ],

    // Doctrine: the Laravel connection (config/database.php) Doctrine DBAL opens,
    // and the entities' folders the entity manager maps (omnibridge/doctrine).
    'doctrine' => [
        'connection' => null,
        'entities' => [],
    ],

    // The application's parameters (Runtime::parameters()) are Laravel's config,
    // with the database settings linked to a parameter laid over it (omnibase's
    // HotParameters) when on: true, false, or null for the config's own
    // base.parameter_bag.use_hot_bag.
    'parameters' => [
        'hot' => null,
    ],

    // omnibase's storages (local.uploads, s3.private...) and the Laravel disk
    // each is; "default" for a storage not named.
    'storages' => [],

    // The entity namespaces of the bundles the site uses, and their prefix in
    // the "entities" catalogues (Base\Marketplace\Entity\ => marketplace\).
    'entity_namespaces' => [],
];
