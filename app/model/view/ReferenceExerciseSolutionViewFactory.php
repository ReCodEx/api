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
            "authorId" => $solution->getSolution()->getAuthorId(),
            "createdAt" => $solution->getSolution()->getCreatedAt()->getTimestamp(),
            "runtimeEnvironmentId" => $solution->getSolution()->getRuntimeEnvironment()->getId(),
            "visibility" => $solution->getVisibility(),
            "submissions" => $solution->getSubmissions()->map(
                function (ReferenceSolutionSubmission $evaluation) {
                    return $evaluation->getId();
                }
            )->getValues(),
            "lastSubmission" => $solution->getLastSubmission(),
            "permissionHints" => PermissionHints::get($this->referenceSolutionAcl, $solution)
        ];
    }

    public function getReferenceSolutionList($solutions)
    {
        return array_map([$this, "getReferenceSolution"], $solutions);
    }
}
