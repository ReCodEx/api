<?php

namespace App\Model;

/**
 * Defines the type of lock for a group exam.
 * The lock type defines the level of access to the student's solutions previously submitted in other groups.
 */
enum GroupExamLockType: string
{
    /**
     * No access to other groups.
     */
    case Restricted = 'restricted';

    /**
     * Solutions that were marked as accepted in other groups are visible.
     */
    case Accepted = 'accepted';

    /**
     * Solutions that were marked as accepted or reviewed in other groups are visible.
     */
    case Reviewed = 'reviewed';

    /**
     * All solutions from other groups are visible.
     */
    case Visible = 'visible';


    public static function values(): array
    {
        return array_map(fn($case) => $case->value, self::cases());
    }
}
