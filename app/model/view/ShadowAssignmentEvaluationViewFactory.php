<?php

namespace App\Model\View;

use App\Model\Entity\ShadowAssignmentEvaluation;
use App\Security\ACL\IShadowAssignmentPermissions;

class ShadowAssignmentEvaluationViewFactory {

  /** @var IShadowAssignmentPermissions */
  public $shadowAssignmentAcl;

  public function __construct(IShadowAssignmentPermissions $shadowAssignmentAcl) {
    $this->shadowAssignmentAcl = $shadowAssignmentAcl;
  }

  public function getEvaluation(ShadowAssignmentEvaluation $evaluation) {
    return [
      'id' => $evaluation->getId()
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
