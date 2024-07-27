<?php

namespace App\Security\ACL;

use App\Model\Entity\User;

interface IUserPermissions
{
    public function canViewAll(): bool;

    public function canViewList(): bool;

    public function canCreate(): bool;

    public function canInviteForRegistration(): bool;

    public function canViewPublicData(User $user): bool;

    public function canViewDetail(User $user): bool;

    public function canUpdateProfile(User $user): bool;

    public function canViewGroups(User $user): bool;

    public function canViewInstances(User $user): bool;

    public function canDelete(User $user): bool;

    public function canTakeOver(User $user): bool;

    public function canCreateLocalAccount(User $user): bool;

    public function canUpdatePersonalData(User $user): bool;

    public function canSetRole(User $user): bool;

    public function canSetIsAllowed(User $user): bool;

    public function canInvalidateTokens(User $user): bool;

    public function canForceChangePassword(User $user): bool;

    public function canViewCalendars(User $user): bool;

    public function canEditCalendars(User $user): bool;

    public function canListPendingReviews(User $user): bool;

    public function canListReviewRequests(User $user): bool;
}
