<?php

// omnibridge/laravel: what omnibase's Foundation is answered with in this
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
    // omnibase: omnibase's own mapping on that entity manager, as under Symfony
    // (Base\Bridge\Doctrine\Mapping: the class convention, the core's entities,
    // its types, discriminators, translations, listeners and filters) - false
    // (off), true, or Mapping::register()'s options: project_dir (base_path()
    // when not given: its src/Enum and src/Attribute), environment (omnibase's
    // name, the vault's: prod, dev, test from production, local, testing),
    // cache_dir, parameters (over the config's "base" by full name), types,
    // enumerations, attribute_paths, resolve_target_entities, entity_listeners.
    'doctrine' => [
        'connection' => null,
        'entities' => [],
        'omnibase' => false,
    ],

    // The application's parameters (Runtime::parameters()) are Laravel's config,
    // with the database settings linked to a parameter laid over it (omnibase's
    // HotParameters) when on: true, false, or null for the config's own
    // base.parameter_bag.use_hot_bag.
    'parameters' => [
        'hot' => null,
    ],

    // omnibase's themes, as the screens' context reads the visitor's colour scheme
    // (their cookie THEME/MODE): the modes (id => [id, class, icon, prefers]), the
    // filters, the one for no filter; no scheme without modes.
    'theme' => [
        'modes' => [],
        'filters' => [],
        'no_filter' => [],
    ],

    // omnibase's storages (local.uploads, s3.private...) and the Laravel disk
    // each is; "default" for a storage not named. With media on, omnibase's
    // Flysystem reads them too (a storage not named is the disk of its name),
    // local.cache - the thumbnails made - required.
    'storages' => [],

    // omnibase's media rule (audit R6): false (off), true, or its options.
    // On, MediaService is built on Laravel's router, Base\Contract\Media and
    // Pictures' thumbnails answer through it, and omnibase's image routes
    // (/images/..., /contents/...) are declared under their Symfony names and
    // answered by Base\Service\ImageRoute - the same obfuscated addresses,
    // thumbnails and formats as under Symfony. Its parameters are the config's
    // "base": base.images.* (max_quality, enable_webp, fallback, no_image...),
    // base.obfuscator.* (uuid, compression..., cache_dir: the identifiers' map,
    // the Symfony site's to read the addresses it wrote), base.uploader.storage,
    // base.twig.editor.storage; omnibase's defaults where it says nothing.
    // Options: routes (false: declare the routes yourself), secret (the
    // hashids compression's, the Symfony site's kernel.secret; app.key when null).
    'media' => false,

    // The entity namespaces of the bundles the site uses, and their prefix in
    // the "entities" catalogues (Base\Marketplace\Entity\ => marketplace\).
    'entity_namespaces' => [],

    // Signing in through omniauth (plan "omniauth", step AU8; omniscient/omniauth
    // installed): true turns on Omnibridge\Laravel\Auth\ - the guard and the user
    // provider (config/auth.php: driver "omniauth"), the routes of every way in at
    // the Symfony site's addresses, the second factor's page, the sign-out - on
    // config/omniauth.php (the same keys as omnibridge/symfony's omniauth.yaml).
    'auth' => false,

    // What omnibase's Twig extensions take from the application in its pages
    // (View\OmnibaseExtensions, audit R24; omniscient/omnibase 4 from R24):
    // - encore: the Encore builds encore_entry_link_tags() and _script_tags()
    //   read (a list of {entrypoints, manifest} files, in the order they are
    //   looked into), the breakpoints (name => media query) a stylesheet has
    //   versions for, the entries a page loads with another ({form: [form.editor]});
    //   no Encore tags without builds;
    // - consent: omnibase/consent's configuration (a YAML file with a consent:
    //   key, the Symfony site's config/packages/consent.yaml, or the array),
    //   none when null;
    // - push: the VAPID keys of omnibase's WebPushService (public_key,
    //   private_key, subject); no push without them;
    // - deploy.project_dir: the folder deploy_version() reads the git heads of
    //   (base_path() when null);
    // - icons.metadata: the folder of the icons' metadata (icons.yml,
    //   bootstrap-icons.json: public/bundles/base/metadata when null).
    'encore' => ['builds' => [], 'breakpoints' => [], 'optional' => []],
    'consent' => ['config' => null],
    'push' => ['public_key' => null, 'private_key' => null, 'subject' => null],
    'deploy' => ['project_dir' => null],
    'icons' => ['metadata' => null],
];
