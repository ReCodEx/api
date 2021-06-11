<?php

namespace App\Helpers;

use App\Exceptions\SubmissionEvaluationFailedException;
use App\Helpers\Evaluation\IExercise;
use App\Helpers\Evaluation\ScoreCalculatorAccessor;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Model\Entity\SolutionEvaluation;

/**
 * Load points for given solution evaluation.
 */
class EvaluationPointsLoader
{
    /**
     * @var ScoreCalculatorAccessor
     */
    private $calculators;

    /**
     * EvaluationPointsLoader constructor.
     * @param ScoreCalculatorAccessor $calculators
     */
    public function __construct(ScoreCalculatorAccessor $calculators)
    {
        $this->calculators = $calculators;
    }

    /**
     * Set score and points to given evaluation of student submission.
     * @throws SubmissionEvaluationFailedException
     */
    public function setStudentScoreAndPoints(?AssignmentSolutionSubmission $submission)
    {
        $this->setStudentScore($submission);
        $this->setStudentPoints($submission);
    }

    /**
     * Set score to evaluation of student submission.
     * @param ?AssignmentSolutionSubmission $submission
     * @throws SubmissionEvaluationFailedException
     */
    private function setStudentScore(?AssignmentSolutionSubmission $submission)
    {
        if ($submission === null || !$submission->hasEvaluation()) {
            return;
        }

        $evaluation = $submission->getEvaluation();
        $this->setScore($evaluation, $submission->getAssignmentSolution()->getAssignment());
    }

    /**
     * Set points to evaluation of student submission.
     * @note Score has to be calculated before call of this function.
     * @param AssignmentSolutionSubmission|null $submission
     */
    public function setStudentPoints(?AssignmentSolutionSubmission $submission)
    {
        if (
            $submission === null
            || !$submission->hasEvaluation()
            || !$submission->getAssignmentSolution()
            || !$submission->getAssignmentSolution()->getAssignment()
        ) {
            return;
        }

        // setup
        $evaluation = $submission->getEvaluation();
        $maxPoints = $submission->getAssignmentSolution()->getMaxPoints();
        $threshold = $submission->getAssignmentSolution()->getAssignment()->getPointsPercentualThreshold();
        $score = $evaluation->getScore();

        // calculate points from the score
        $points = ($score >= $threshold) ? floor($score * $maxPoints) : 0;

        // ... and set results
        $evaluation->setPoints($points);
    }

    /**
     * Determine if student submission is correct.
     */
    public static function isStudentCorrect(?AssignmentSolutionSubmission $submission): bool
    {
        if ($submission === null || !$submission->hasEvaluation()) {
            // not a student submission
            return false;
        }

        $evaluation = $submission->getEvaluation();
        $assignment = $submission->getAssignmentSolution()->getAssignment();
        if ($assignment && $assignment->hasAssignedPoints()) {
            return $evaluation->getPoints() > 0;
        }

        // points for assignment are all zeroes, this means simple checking of
        // evaluation points is not sufficient, so lets get craaazy

        if ($submission->getAssignmentSolution()->isAfterDeadline()) {
            // submitted after deadline -> automatically incorrect
            return false;
        }

        if ($evaluation->getScore() == 0) {
            // none of the tests was correct -> whole solution incorrect
            return false;
        }

        return true;
    }

    /**
     * Evaluation score calculated with exercise calculator.
     * @throws SubmissionEvaluationFailedException
     */
    public function setReferenceScore(?ReferenceSolutionSubmission $submission)
    {
        if ($submission === null || !$submission->hasEvaluation()) {
            // not a reference submission
            return;
        }

        $evaluation = $submission->getEvaluation();
        $referenceSolution = $submission->getReferenceSolution();
        $this->setScore($evaluation, $referenceSolution->getExercise());
    }

    /**
     * Helper function which handle the same score setting functionality for
     * student and reference solution.
     * @param SolutionEvaluation $evaluation
     * @param IExercise|null $exercise
     * @throws SubmissionEvaluationFailedException
     */
    private function setScore(SolutionEvaluation $evaluation, ?IExercise $exercise)
    {
        if ($exercise === null) {
            throw new SubmissionEvaluationFailedException("Exercise was deleted");
        }

        // setup
        $score = 0;

        // calculate scores for all tests
        $testResults = [];
        foreach ($evaluation->getTestResults() as $testResult) {
            $testResults[$testResult->getTestName()] = $testResult;
        }

        // calculate percentual score of whole solution
        $calculator = $this->calculators->getCalculator($exercise->getScoreConfig()->getCalculator());
        if ($calculator !== null) {
            $score = $calculator->computeScore($exercise->getScoreConfig()->getConfigParsed(), $testResults);
        }

        // ... and set results
        $evaluation->setScore($score, $exercise->getScoreConfig());
    }
}
