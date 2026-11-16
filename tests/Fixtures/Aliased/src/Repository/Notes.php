<?php

namespace App\Repository;

/** An application's own repository interface (lesringostars' App\Repository\Article\Articles). */
interface Notes
{
    public function latest(): array;
}
