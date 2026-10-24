<?php

namespace Omnibridge\Laravel\Tests\Fixtures\Action;

use Base\Screen\Json;
use Symfony\Component\Routing\Attribute\Route;

/** A destination's home, and the site's without one: the catch-all goes last (its priority). */
#[Route('/', name: 'home.default')]
#[Route('/{place}', name: 'home', priority: -2)]
final class Home
{
    public function __invoke(?string $place = null): Json
    {
        return new Json(['page' => 'home', 'place' => $place]);
    }
}
