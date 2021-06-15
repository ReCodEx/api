<?php

namespace App\Security\ACL;

use App\Helpers\SisCourseRecord;
use App\Model\Entity\Group;
use App\Model\Entity\SisValidTerm;

class SisIdWrapper
{
    private $id;

    public function __construct(string $id)
    {
        $this->id = $id;
    }

    public function get()
    {
        return $this->id;
    }
}

class SisGroupContext
{
    private $parentGroup;

    private $course;

    public function __construct(Group $parentGroup, SisCourseRecord $course)
    {
        $this->parentGroup = $parentGroup;
        $this->course = $course;
    }

    public function getParentGroup(): Group
    {
        return $this->parentGroup;
    }

    public function getCourse(): SisCourseRecord
    {
        return $this->course;
    }
}

interface ISisPermissions
{
    public function canCreateGroup(SisGroupContext $groupContext, SisCourseRecord $course): bool;

    public function canBindGroup(Group $group, SisCourseRecord $course): bool;

    public function canUnbindGroup(Group $group, SisCourseRecord $course): bool;

    public function canViewCourses(SisIdWrapper $sisId): bool;

    public function canCreateTerm(): bool;

    public function canEditTerm(SisValidTerm $term): bool;

    public function canDeleteTerm(SisValidTerm $term): bool;

    public function canViewTerms(): bool;
}
