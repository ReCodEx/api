<?php

namespace App\Helpers;

use App\Exceptions\InternalServerException;
use App\Exceptions\SubmissionEvaluationFailedException;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Entity\Submission;
use App\Model\Entity\SubmissionFailure;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\OptimisticLockException;
use Nette;


class EvaluationLoadingHelper {
  use Nette\SmartObject;

  /** @var EvaluationLoader */
  private $evaluationLoader;

  /** @var FailureHelper */
  private $failureHelper;

  /** @var EntityManager */
  private $entityManager;

  /**
   * @param EvaluationLoader $evaluationLoader
   * @param FailureHelper $failureHelper
   * @param EntityManager $entityManager
   */
  public function __construct(EvaluationLoader $evaluationLoader, FailureHelper $failureHelper, EntityManager $entityManager)
  {
    $this->evaluationLoader = $evaluationLoader;
    $this->failureHelper = $failureHelper;
    $this->entityManager = $entityManager;
  }

  /**
   * Try to get evaluation of given submission. If found, then evaluate it and
   * save results into database.
   *
   * @param Submission $submission
   * @return bool
   * @throws InternalServerException
   */
  public function loadEvaluation(Submission $submission): bool {
    if ($submission->hasEvaluation()) {
      return true;
    }

    if (!$submission->canBeEvaluated() || $submission->isFailed()) {
      return false;
    }

    try {
      try {
        $evaluation = $this->evaluationLoader->load($submission);

        if ($evaluation !== null) { // If null is returned and no exception was thrown, the evaluation is not ready yet
          $this->entityManager->persist($evaluation);
          $this->entityManager->flush($submission);
        }
      } catch (SubmissionEvaluationFailedException $e) {
        // the result cannot be loaded even though the result MUST be ready at this point
        $message = "Loading evaluation results failed with exception '{$e->getMessage()}'";

        if ($submission instanceof AssignmentSolutionSubmission) {
          $failure = SubmissionFailure::forSubmission(
            SubmissionFailure::TYPE_LOADING_FAILURE,
            $message,
            $submission
          );
        } else if ($submission instanceof ReferenceSolutionSubmission) {
          $failure = SubmissionFailure::forReferenceSubmission(
            SubmissionFailure::TYPE_LOADING_FAILURE,
            $message,
            $submission
          );
        } else {
          throw new InternalServerException("Unknown submission type '{$submission->getClassName()}'");
        }

        $this->entityManager->persist($failure);
        $this->entityManager->flush($failure);

        $this->failureHelper->reportSubmissionFailure($failure, FailureHelper::TYPE_API_ERROR);
        return false;
      }
    } catch (OptimisticLockException $e) {}

    return true;
  }
}
