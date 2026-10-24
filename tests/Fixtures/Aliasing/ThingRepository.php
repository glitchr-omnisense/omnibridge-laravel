<?php

namespace Omnibridge\Laravel\Tests\Fixtures\Aliasing;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

#[AsAlias(Things::class)]
class ThingRepository implements Things, Named
{
    public function name(): string
    {
        return 'things';
    }
}
