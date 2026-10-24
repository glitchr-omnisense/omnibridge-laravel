<?php

namespace Omnibridge\Laravel\Tests\Fixtures\Action;

use Base\Screen\Json;
use Symfony\Component\Routing\Attribute\Route;

/** Routes on methods, and some for an environment only (one posted to: its body, R29). */
#[Route('/pair')]
final class Pair
{
    #[Route('/one', name: 'pair_one', methods: ['GET'])]
    public function one(): Json
    {
        return new Json('one');
    }

    #[Route('/dev', name: 'pair_dev', env: 'dev')]
    public function dev(): Json
    {
        return new Json('dev');
    }

    /** @param array<string, mixed> $body */
    #[Route('/posted', name: 'pair_posted', methods: ['POST'], env: 'dev')]
    public function posted(array $body = []): Json
    {
        return new Json($body);
    }
}
