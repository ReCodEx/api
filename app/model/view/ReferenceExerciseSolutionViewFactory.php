<?php
namespace App\Model\View;

use App\Helpers\PermissionHints;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Security\ACL\IReferenceExerciseSolutionPermissions;

class ReferenceExerciseSolutionViewFactory {
  /**
   * @var IReferenceExerciseSolutionPermissions
   */
  private $referenceSolutionAcl;

  public function __construct(IReferenceExerciseSolutionPermissions $referenceSolutionAcl) {
    $this->referenceSolutionAcl = $referenceSolutionAcl;
  }

  public function getReferenceSolution(ReferenceExerciseSolution $referenceExerciseSolution) {
    return [
      "id" => $referenceExerciseSolution->getId(),
      "exerciseId" => $referenceExerciseSolution->getExercise()->getId(),
      "description" => $referenceExerciseSolution->getDescription(),
      "solution" => $referenceExerciseSolution->getSolution(),
      "runtimeEnvironmentId" => $referenceExerciseSolution->getSolution()->getRuntimeEnvironment()->getId(),
      "submissions" => $referenceExerciseSolution->getSubmissions()->map(
        function (ReferenceSolutionSubmission $evaluation) {
          return $evaluation->getId();
        }
      )->getValues(),
      "permissionHints" => PermissionHints::get($this->referenceSolutionAcl, $referenceExerciseSolution)
    ];
  }

  public function getReferenceSolutionList($solutions) {
    return array_map([$this, "getReferenceSolution"], $solutions);
  }
}
