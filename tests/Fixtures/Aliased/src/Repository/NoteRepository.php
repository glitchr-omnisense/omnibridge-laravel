<?php

namespace App\Repository;

use Symfony\Component\DependencyInjection\Attribute\AsAlias;

/** A repository of the project's src/Repository naming its interface with Symfony's #[AsAlias], as the Symfony site does. */
#[AsAlias(Notes::class)]
final class NoteRepository implements Notes
{
    public function latest(): array
    {
        return ['first-snow'];
    }
}
