<?php

namespace App\Model\View;

use App\Model\Entity\ShadowAssignmentEvaluation;

class ShadowAssignmentEvaluationViewFactory {

  public function getEvaluation(ShadowAssignmentEvaluation $evaluation) {
    return [
      'id' => $evaluation->getId(),
      'points' => $evaluation->getPoints(),
      'note' => $evaluation->getNote(),
      'authorId' => $evaluation->getAuthor()->getId(),
      'evaluateeId' => $evaluation->getEvaluatee()->getId(),
      'createdAt' => $evaluation->getCreatedAt()->getTimestamp(),
      'updatedAt' => $evaluation->getUpdatedAt()->getTimestamp(),
      'evaluatedAt' => $evaluation->getEvaluatedAt() ? $evaluation->getEvaluatedAt()->getTimestamp() : null
    ];
  }

  /**
   * @param ShadowAssignmentEvaluation[] $evaluations
   * @return array
   */
  public function getEvaluations(array $evaluations) {
    return array_map(function (ShadowAssignmentEvaluation $evaluation) {
      return $this->getEvaluation($evaluation);
    }, $evaluations);
  }
}
