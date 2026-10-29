<?php

// glitchr/omnishield on Laravel (Omnibridge\Laravel\Shield\ShieldServiceProvider): the keys of
// the Symfony bundle's omnishield.yaml - the gateways by name (a factory and its options), the
// captcha a form asks by default, whether a captcha that does not answer lets a form through,
// whether ALTCHA's widget is served by the application itself, and where the tokens already
// used are remembered (a PSR-6 pool's binding; in memory otherwise).
return [
    'gateways' => [],
    'challenge' => ['gateway' => null, 'unreachable' => 'reject'],
    'serve_scripts' => true,
    'replay' => ['pool' => null],
];
