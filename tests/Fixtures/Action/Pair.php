<?php

namespace Omnibridge\Laravel\Tests\Fixtures\Action;

use Base\Screen\Json;
use Symfony\Component\Routing\Attribute\Route;

/** Routes on methods, and one for an environment only. */
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
}
