<?php

namespace App\Security\Policies;

use App\Model\Entity\GroupMembership;
use App\Model\Entity\Group;
use App\Model\Entity\User;
use App\Security\Roles;
use InvalidArgumentException;

/**
 * Base policy class that implements caching mechanisms reusable by other policies.
 */
class BasePermissionPolicy
{
    private const MEMBERSHIPS = [
        GroupMembership::TYPE_STUDENT => 1,
        GroupMembership::TYPE_OBSERVER => 2,
        GroupMembership::TYPE_SUPERVISOR => 3,
        GroupMembership::TYPE_ADMIN => 4,
    ];

    private static array $membershipCache = [];

    protected function getMembershipLevel(User $user, Group $group): int
    {
        $gid = $group->getId();
        if (!array_key_exists($gid, self::$membershipCache)) {
            self::$membershipCache[$gid] = 0; // Not a member

            if ($user->getRole() === Roles::STUDENT_ROLE) {
                if ($group->isStudentOf($user)) {
                    self::$membershipCache[$gid] = self::MEMBERSHIPS[GroupMembership::TYPE_STUDENT];
                }
            } else {
                if ($group->isAdminOf($user)) {
                    self::$membershipCache[$gid] = self::MEMBERSHIPS[GroupMembership::TYPE_ADMIN];
                } elseif ($group->isSupervisorOf($user)) {
                    self::$membershipCache[$gid] = self::MEMBERSHIPS[GroupMembership::TYPE_SUPERVISOR];
                } elseif ($group->isObserverOf($user)) {
                    self::$membershipCache[$gid] = self::MEMBERSHIPS[GroupMembership::TYPE_OBSERVER];
                } elseif ($user->getRole() === Roles::STUDENT_ROLE && $group->isStudentOf($user)) {
                    self::$membershipCache[$gid] = self::MEMBERSHIPS[GroupMembership::TYPE_STUDENT];
                }
            }
        }

        return self::$membershipCache[$gid];
    }

    protected function checkMinimalMembership(?User $user, ?Group $group, string $membership): bool
    {
        if (!$user || !$group) {
            return false;
        }

        $minLevel = self::MEMBERSHIPS[$membership] ?? null;
        if ($minLevel === null) {
            throw new InvalidArgumentException("Unknown membership type: $membership");
        }

        $level = $this->getMembershipLevel($user, $group);
        return $level >= $minLevel;
    }
}
