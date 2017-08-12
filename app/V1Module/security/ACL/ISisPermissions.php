<?php
namespace App\Security\ACL;

use App\Helpers\SisCourseRecord;
use App\Model\Entity\Group;

class SisIdWrapper {
  private $id;

  public function __construct(string $id) {
    $this->id = $id;
  }

  public function get() {
    return $this->id;
  }
}

interface ISisPermissions {
  function canCreateGroup(Group $parentGroup, SisCourseRecord $course): bool;
  function canBindGroup(Group $group, SisCourseRecord $course): bool;
  function canViewCourses(SisIdWrapper $sisId): bool;
  function canCreateTerm(): bool;
}
