<?php

namespace App\Model\View;

use App\Model\Entity\ShadowAssignmentPoints;

class ShadowAssignmentPointsViewFactory {

  public function getPoints(ShadowAssignmentPoints $points) {
    return [
      'id' => $points->getId(),
      'points' => $points->getPoints(),
      'note' => $points->getNote(),
      'authorId' => $points->getAuthor()->getId(),
      'awardeeId' => $points->getAwardee()->getId(),
      'createdAt' => $points->getCreatedAt()->getTimestamp(),
      'updatedAt' => $points->getUpdatedAt()->getTimestamp(),
      'awardedAt' => $points->getAwardedAt() ? $points->getAwardedAt()->getTimestamp() : null
    ];
  }

  /**
   * @param ShadowAssignmentPoints[] $pointsList
   * @return array
   */
  public function getPointsList(array $pointsList) {
    return array_map(function (ShadowAssignmentPoints $points) {
      return $this->getPoints($points);
    }, $pointsList);
  }
}
