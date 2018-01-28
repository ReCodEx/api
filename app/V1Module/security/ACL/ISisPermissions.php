<?php
namespace App\Security\ACL;

use App\Helpers\SisCourseRecord;
use App\Model\Entity\Group;
use App\Model\Entity\SisValidTerm;

class SisIdWrapper {
  private $id;

  public function __construct(string $id) {
    $this->id = $id;
  }

  public function get() {
    return $this->id;
  }
}

class SisGroupContext {
  private $parentGroup;

  private $course;

  public function __construct(Group $parentGroup, SisCourseRecord $course) {
    $this->parentGroup = $parentGroup;
    $this->course = $course;
  }

  public function getParentGroup(): Group {
    return $this->parentGroup;
  }

  public function getCourse(): SisCourseRecord {
    return $this->course;
  }
}

interface ISisPermissions {
  function canCreateGroup(SisGroupContext $groupContext, SisCourseRecord $course): bool;
  function canBindGroup(Group $group, SisCourseRecord $course): bool;
  function canViewCourses(SisIdWrapper $sisId): bool;
  function canCreateTerm(): bool;
  function canEditTerm(SisValidTerm $term): bool;
  function canDeleteTerm(SisValidTerm $term): bool;
  function canViewTerms(): bool;
}
