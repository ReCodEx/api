<?php

namespace App\Model\View;

use App\Helpers\PermissionHints;
use App\Model\Repository\AssignmentSolutions;
use App\Model\Entity\PlagiarismDetectedSimilarity;
use App\Model\Entity\PlagiarismDetectedSimilarFile;
use App\Security\ACL\IAssignmentPermissions;
use App\Security\ACL\IAssignmentSolutionPermissions;
use App\Exceptions\InternalServerException;

/**
 * Factory for solution views which somehow do not fit into json serialization of entities.
 */
class PlagiarismViewFactory
{
    /**
     * @var IAssignmentPermissions
     */
    public $assignmentAcl;

    /**
     * @var IAssignmentSolutionPermissions
     */
    public $assignmentSolutionAcl;

    public function __construct(
        IAssignmentPermissions $assignmentAcl,
        IAssignmentSolutionPermissions $assignmentSolutionAcl
    ) {
        $this->assignmentAcl = $assignmentAcl;
        $this->assignmentSolutionAcl = $assignmentSolutionAcl;
    }

    /**
     * Parametrized view.
     * @param PlagiarismDetectedSimilarity $similarity
     * @return array
     * @throws InternalServerException
     */
    public function getPlagiarismSimilaityData(PlagiarismDetectedSimilarity $similarity)
    {
        // transform nested similar files
        $files = array_map(function (PlagiarismDetectedSimilarFile $file) {
            $solution = $file->getSolution();
            $assignment = $group = null;
            if ($solution) {
                $assignment = $solution->getAssignment();
                $solution = [
                    // a restricted (safe) view of the solution
                    "id" => $solution->getId(),
                    "attemptIndex" => $solution->getAttemptIndex(),
                    "createdAt" => $solution->getSolution()->getCreatedAt()->getTimestamp(),
                    "runtimeEnvironmentId" => $solution->getSolution()->getRuntimeEnvironment()->getId(),
                    "canViewDetail" => $this->assignmentSolutionAcl->canViewDetail($solution), // single permission hint
                ];

                if ($assignment) {
                    $group = $assignment->getGroup()->getId();
                    $assignment = [
                        "id" => $assignment->getId(),
                        "canViewDetail" => $this->assignmentAcl->canViewDetail($assignment), // single permission hint
                    ];
                }
            }

            return [
                "id" => $file->getId(),
                "solution" => $solution,
                "assignment" => $assignment,
                "groupId" => $group,
                "solutionFile" => $file->getSolutionFile(),
                "fileEntry" => $file->getFileEntry(),
                "fragments" => $file->getFragments(),
            ];
        }, $similarity->getSimilarFiles()->toArray());

        return [
            "id" => $similarity->getId(),
            "batchId" => $similarity->getBatch()->getId(),
            "authorId" => $similarity->getAuthor() ? $similarity->getAuthor()->getId() : null,
            "testedSolutionId" => $similarity->getTestedSolution()->getId(),
            "solutionFileId" => $similarity->getSolutionFile()->getId(),
            "fileEntry" => $similarity->getFileEntry(),
            "similarity" => $similarity->getSimilarity(),
            "files" => $files,
        ];
    }
}
