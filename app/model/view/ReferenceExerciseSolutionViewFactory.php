<?php

namespace App\Model\View;

use App\Helpers\PermissionHints;
use App\Model\Entity\ReferenceExerciseSolution;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Security\ACL\IReferenceExerciseSolutionPermissions;

class ReferenceExerciseSolutionViewFactory
{
    /**
     * @var IReferenceExerciseSolutionPermissions
     */
    private $referenceSolutionAcl;

    public function __construct(IReferenceExerciseSolutionPermissions $referenceSolutionAcl)
    {
        $this->referenceSolutionAcl = $referenceSolutionAcl;
    }

    public function getReferenceSolution(ReferenceExerciseSolution $solution)
    {
        return [
            "id" => $solution->getId(),
            "exerciseId" => $solution->getExercise() ? $solution->getExercise()->getId() : null,
            "description" => $solution->getDescription(),
            "solution" => $solution->getSolution(),
            "runtimeEnvironmentId" => $solution->getSolution()->getRuntimeEnvironment()->getId(),
            "submissions" => $solution->getSubmissions()->map(
                function (ReferenceSolutionSubmission $evaluation) {
                    return $evaluation->getId();
                }
            )->getValues(),
            "permissionHints" => PermissionHints::get($this->referenceSolutionAcl, $solution)
        ];
    }

    public function getReferenceSolutionList($solutions)
    {
        return array_map([$this, "getReferenceSolution"], $solutions);
    }
}
