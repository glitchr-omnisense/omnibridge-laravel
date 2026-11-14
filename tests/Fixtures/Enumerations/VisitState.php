<?php

namespace Omnibridge\Laravel\Tests\Fixtures\Enumerations;

use Base\Database\Type\EnumType;

/** An enumeration of a folder omnibase's option enumerations gives (no src/Enum of the project): the column type visit_state. */
class VisitState extends EnumType
{
    public const PLANNED = 'VISIT_PLANNED';
    public const DONE = 'VISIT_DONE';
}
