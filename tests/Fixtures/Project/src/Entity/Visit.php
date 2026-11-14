<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Omnibridge\Laravel\Tests\Fixtures\Targets\TravellerInterface;

/**
 * An entity of an omnibase project's src/Entity (omnibridge.omnibase's project_dir), with a relation toward an
 * interface (resolved by omnibase's option resolve_target_entities) and a column of an enumeration of a folder given
 * (its option enumerations): what Doctrine's mapping and omnibridge/eloquent are both to read the same way.
 */
#[ORM\Entity]
class Visit
{
    #[ORM\Id, ORM\GeneratedValue, ORM\Column]
    public ?int $id = null;

    #[ORM\Column(type: 'visit_state')]
    public string $state = 'VISIT_PLANNED';

    #[ORM\ManyToOne(targetEntity: TravellerInterface::class)]
    public ?object $traveller = null;
}
