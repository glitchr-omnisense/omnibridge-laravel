<?php

namespace Omnibridge\Laravel\Tests\Fixtures\Aliasing;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/** #[AsAlias] naming no interface: the class's only one. */
#[AsAlias]
final class Only implements Named
{
}
