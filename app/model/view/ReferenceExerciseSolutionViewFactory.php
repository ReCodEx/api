<?php
namespace App\Model\View;

use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\ReferenceSolutionSubmission;

class ReferenceExerciseSolutionViewFactory {
  public function getReferenceSolution(ReferenceExerciseSolution $referenceExerciseSolution) {
    $result = [
      "id" => $referenceExerciseSolution->getId(),
      "description" => $referenceExerciseSolution->getDescription(),
      "solution" => $referenceExerciseSolution->getSolution(),
      "runtimeEnvironmentId" => $referenceExerciseSolution->getSolution()->getRuntimeEnvironment()->getId(),
      "submissions" => $referenceExerciseSolution->getSubmissions()->map(
        function (ReferenceSolutionSubmission $evaluation) {
          return $evaluation->getId();
        }
      )->getValues()
    ];

    return $result;
  }

  public function getReferenceSolutionList($solutions) {
    return array_map([$this, "getReferenceSolution"], $solutions);
  }
}
