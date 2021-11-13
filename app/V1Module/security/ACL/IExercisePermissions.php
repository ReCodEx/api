<?php

namespace App\Security\ACL;

use App\Model\Entity\Exercise;
use App\Model\Entity\Group;

interface IExercisePermissions
{
    public function canViewAll(): bool;

    public function canViewAllAuthors(): bool;

    public function canViewList(): bool;

    public function canViewDetail(Exercise $exercise): bool;

    public function canViewConfig(Exercise $exercise): bool;

    public function canUpdate(Exercise $exercise): bool;

    public function canCreate(): bool;

    public function canRemove(Exercise $exercise): bool;

    public function canFork(Exercise $exercise): bool;

    public function canViewLimits(Exercise $exercise): bool;

    public function canSetLimits(Exercise $exercise): bool;

    public function canViewScoreConfig(Exercise $exercise): bool;

    public function canSetScoreConfig(Exercise $exercise): bool;

    public function canAddReferenceSolution(Exercise $exercise): bool;

    public function canAttachPipeline(Exercise $exercise): bool;

    public function canDetachPipeline(Exercise $exercise): bool;

    public function canViewPipelines(Exercise $exercise): bool;

    public function canViewAssignments(Exercise $exercise): bool;

    public function canAttachGroup(Exercise $exercise, Group $group): bool;

    public function canDetachGroup(Exercise $exercise, Group $group): bool;

    public function canViewAllTags(): bool;

    public function canViewTagsStats(): bool;

    public function canUpdateTagsGlobal(): bool;

    public function canRemoveTagsGlobal(): bool;

    public function canAddTag(Exercise $exercise): bool;

    public function canRemoveTag(Exercise $exercise): bool;

    public function canAssign(Exercise $exercise): bool;
}
