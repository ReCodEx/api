<?php

namespace App\Helpers;

use App\Exceptions\InternalServerException;
use App\Exceptions\SubmissionEvaluationFailedException;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Entity\Submission;
use App\Model\Entity\SubmissionFailure;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\OptimisticLockException;
use Nette;

class EvaluationLoadingHelper
{
    use Nette\SmartObject;

    /** @var EvaluationLoader */
    private $evaluationLoader;

    /** @var FailureHelper */
    private $failureHelper;

    /** @var EntityManagerInterface */
    private $entityManager;

    /**
     * @param EvaluationLoader $evaluationLoader
     * @param FailureHelper $failureHelper
     * @param EntityManagerInterface $entityManager
     */
    public function __construct(
        EvaluationLoader $evaluationLoader,
        FailureHelper $failureHelper,
        EntityManagerInterface $entityManager
    ) {
        $this->evaluationLoader = $evaluationLoader;
        $this->failureHelper = $failureHelper;
        $this->entityManager = $entityManager;
    }

    /**
     * Try to get evaluation of given submission. If found, then evaluate it and
     * save results into database.
     *
     * @param Submission $submission
     * @return bool result of the loading process
     *              (true = loaded or failure duly reported, false = silent failure, results are not available yet)
     * @throws InternalServerException
     */
    public function loadEvaluation(Submission $submission): bool
    {
        if ($submission->hasEvaluation() || $submission->isFailed()) {
            return true;
        }

        try {
            try {
                $evaluation = $this->evaluationLoader->load($submission);
                if (!$evaluation) {
                    return false; // the evaluation is not ready yet (soft failure)
                }

                $this->entityManager->persist($evaluation);
                $this->entityManager->flush();
            } catch (SubmissionEvaluationFailedException $e) {
                // the result cannot be loaded even though the result MUST be ready at this point
                $message = "Loading evaluation results failed with exception '{$e->getMessage()}'";

                if (
                    $submission instanceof AssignmentSolutionSubmission
                    || $submission instanceof ReferenceSolutionSubmission
                ) {
                    $failure = SubmissionFailure::create(SubmissionFailure::TYPE_LOADING_FAILURE, $message);
                    $submission->setFailure($failure);
                } else {
                    $className = get_class($submission);
                    throw new InternalServerException("Unknown submission type '{$className}'");
                }

                $this->entityManager->persist($failure);
                $this->entityManager->persist($submission);
                $this->entityManager->flush();

                $this->failureHelper->reportSubmissionFailure($submission, FailureHelper::TYPE_API_ERROR);
            }
        } catch (OptimisticLockException $e) {
        }

        return true; // either results are available, or we correctly saved failure
    }
}
