<?php
namespace App\Model\View;

use App\Helpers\PermissionHints;
use App\Model\Entity\LocalizedShadowAssignment;
use App\Model\Entity\ShadowAssignment;
use App\Model\Entity\ShadowAssignmentPoints;
use App\Model\Entity\User;
use App\Security\ACL\IShadowAssignmentPermissions;
use App\Security\Identity;


class ShadowAssignmentViewFactory {

  /** @var IShadowAssignmentPermissions */
  public $shadowAssignmentAcl;

  /** @var User */
  private $user = null;

  public function __construct(IShadowAssignmentPermissions $shadowAssignmentAcl, \Nette\Security\User $user) {
    $this->shadowAssignmentAcl = $shadowAssignmentAcl;
    $identity = $user->getIdentity();
    if ($identity !== null && $identity instanceof Identity) {
      $this->user = $identity->getUserData();
    }
  }

  public function getPoints(ShadowAssignmentPoints $points) {
    return [
      'id' => $points->getId(),
      'points' => $points->getPoints(),
      'note' => $points->getNote(),
      'authorId' => $points->getAuthor()->getId(),  // who give the points
      'awardeeId' => $points->getAwardee()->getId(),  // who gets the points
      'createdAt' => $points->getCreatedAt()->getTimestamp(),
      'updatedAt' => $points->getUpdatedAt()->getTimestamp(),
      'awardedAt' => $points->getAwardedAt() ? $points->getAwardedAt()->getTimestamp() : null
    ];
  }

  private function getAssignmentPoints(ShadowAssignment $assignment) {
    $points = [];
    if ($this->shadowAssignmentAcl->canViewAllPoints($assignment)) {
      // The lidless eye sees all...
      $points = array_map(function($p) {
        return $this->getPoints($p);
      }, $assignment->getShadowAssignmentPointsCollection()->getValues());
    } elseif ($this->user !== null) {
      // The user can see only points of hir own...
      $p = $assignment->getPointsByUser($this->user);
      if ($p) {
        $points[] = $this->getPoints($p);
      }
    }
    return $points;
  }

  public function getAssignment(ShadowAssignment $assignment) {
    return [
      "id" => $assignment->getId(),
      "version" => $assignment->getVersion(),
      "isPublic" => $assignment->isPublic(),
      "createdAt" => $assignment->getCreatedAt()->getTimestamp(),
      "updatedAt" => $assignment->getUpdatedAt()->getTimestamp(),
      "localizedTexts" => $assignment->getLocalizedTexts()->map(function (LocalizedShadowAssignment $text) {
        return $text->jsonSerialize();
      })->getValues(),
      "groupId" => $assignment->getGroup()->getId(),
      "isBonus" => $assignment->isBonus(),
      "maxPoints" => $assignment->getMaxPoints(),
      "points" => $this->getAssignmentPoints($assignment),
      "permissionHints" => PermissionHints::get($this->shadowAssignmentAcl, $assignment)
    ];
  }
}
