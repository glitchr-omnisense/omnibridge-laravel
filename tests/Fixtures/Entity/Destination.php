<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
class Destination
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column]
    public string $slug = '';

    // omnibase's datetime (omnibridge/doctrine 4.0): read as a \DateTimeImmutable in UTC, any \DateTimeInterface written.
    #[ORM\Column(type: 'datetime', nullable: true)]
    public ?\DateTimeInterface $startedAt = null;
}
