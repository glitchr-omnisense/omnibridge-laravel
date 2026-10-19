<?php

namespace Omnibridge\Laravel\Contract;

use Base\Contract\Environment;

/** What the application runs as: Laravel's environment (APP_ENV), debug (app.debug) and key (app.key, the secret). */
final class AppEnvironment implements Environment
{
    public function __construct(
        private readonly string $name,
        private readonly bool $debug,
        #[\SensitiveParameter] private readonly string $secret,
    ) {
    }

    public function name(): string
    {
        return $this->name;
    }

    public function isDebug(): bool
    {
        return $this->debug;
    }

    public function secret(): string
    {
        return $this->secret;
    }
}
