<?php

namespace Omnibridge\Laravel\Tests\Fixtures\Action;

use Base\Screen\Json;
use Symfony\Component\Routing\Attribute\Route;

/** A localized route: one path per language, its name by language, the page in that language. */
#[Route(['en' => '/{place}/travels', 'fr' => '/{place}/voyages'], name: 'travels', requirements: ['place' => '[a-z]+'])]
#[Route(['en' => '/travels', 'fr' => '/voyages'], name: 'travels.default')]
final class Travels
{
    /** @param array<string, mixed> $query */
    public function __invoke(?string $place = null, array $query = []): ?Json
    {
        if ('nowhere' === $place) {
            return null;
        }

        return new Json(['page' => 'travels', 'place' => $place, 'from' => $query['from'] ?? null, 'locale' => app()->getLocale()]);
    }
}
