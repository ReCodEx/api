<?php
/**
 * Created by PhpStorm.
 * User: teyras
 * Date: 04.10.17
 * Time: 14:26
 */

namespace App\Security\Policies;


use App\Security\ACL\SisGroupContext;
use App\Security\Identity;

class SisGroupContextPermissionPolicy implements IPermissionPolicy {
  public function getAssociatedClass() {
    return SisGroupContext::class;
  }

  public function doesTermMatch(Identity $identity, SisGroupContext $context): bool {
    return $context->getParentGroup()->getExternalId() === $context->getCourse()->getTermIdentifier();
  }

  public function isParentGroupAssociatedWithCourse(Identity $identity, SisGroupContext $context): bool {
    $cursor = $context->getParentGroup();

    while ($cursor !== NULL) {
      $associatedCourses = explode(" ", $cursor->getExternalId());
      if (in_array($context->getCourse()->getCourseId(), $associatedCourses)) {
        return TRUE;
      }

      $cursor = $cursor->getParentGroup();
    }

    return FALSE;
  }
}