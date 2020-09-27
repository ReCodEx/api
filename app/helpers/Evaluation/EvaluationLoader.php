<?php

namespace App\Helpers;

use App\Model\Entity\AssignmentSolution;
use App\Model\Entity\AssignmentSolutionSubmission;
use App\Model\Entity\SolutionEvaluation;
use App\Model\Entity\ReferenceSolutionSubmission;
use App\Helpers\EvaluationResults\EvaluationResults;
use App\Helpers\EvaluationResults\Loader as EvaluationResultsLoader;
use App\Helpers\JobConfig\Loader as JobConfigLoader;
use App\Helpers\FileStorageManager;
use App\Helpers\Yaml;
use App\Helpers\YamlException;
use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\ResultsLoadingException;
use App\Exceptions\SubmissionEvaluationFailedException;
use App\Model\Entity\Submission;
use Mockery\Exception;

/**
 * Load evaluation for given submission. This may require connecting to the file server,
 * download the results, parsing and evaluating them.
 */
class EvaluationLoader
{
    /** @var FileStorageManager */
    private $fileStorage;

    /** @var EvaluationPointsLoader */
    private $pointsLoader;

    /** @var JobConfigLoader */
    private $jobConfigLoader;

    /**
     * Constructor
     * @param FileStorageManager $fileStorage
     * @param EvaluationPointsLoader $pointsLoader
     */
    public function __construct(FileStorageManager $fileStorage, EvaluationPointsLoader $pointsLoader)
    {
        $this->fileStorage = $fileStorage;
        $this->pointsLoader = $pointsLoader;
        $this->jobConfigLoader = new JobConfigLoader();
    }

    /**
     * Downloads and processes the results for the given submission.
     * @param Submission $submission The submission
     * @return SolutionEvaluation|null  Evaluated results for given submission
     * @throws SubmissionEvaluationFailedException
     */
    public function load(Submission $submission)
    {
        $results = $this->getResults($submission);
        if (!$results) {
            return null;
        }

        $evaluation = new SolutionEvaluation($results);
        $submission->setEvaluation($evaluation);
        if ($submission instanceof AssignmentSolutionSubmission) {
            $this->pointsLoader->setStudentScoreAndPoints($submission);
        } else {
            if ($submission instanceof ReferenceSolutionSubmission) {
                $this->pointsLoader->setReferenceScore($submission);
            }
        }
        return $evaluation;
    }

    /**
     * Downloads and parses the results report from the server.
     * @param Submission $submission The submission
     * @return EvaluationResults Parsed submission results
     * @throws SubmissionEvaluationFailedException
     */
    private function getResults(Submission $submission)
    {
        $jobConfigFile = $this->fileStorage->getJobConfig($submission);
        if ($jobConfigFile === null) {
            throw new SubmissionEvaluationFailedException("Job config of the submission not found.");
        }

        $resultsYamlFile = $this->fileStorage->getResultsYamlFile($submission);
        if ($resultsYamlFile === null) {
            throw new SubmissionEvaluationFailedException("Job results file is missing.");
        }

        try {
            $jobConfigYaml = Yaml::parse($jobConfigFile->getContents());
            $jobConfig = $this->jobConfigLoader->loadJobConfig($jobConfigYaml);
            $jobConfig->getSubmissionHeader()->setId($submission->getId())->setType($submission->getJobType());
            $resultsYml = $resultsYamlFile->getContents();
            return EvaluationResultsLoader::parseResults($resultsYml, $jobConfig);
        } catch (YamlException $e) {
            throw new SubmissionEvaluationFailedException("Job config is malformed - {$e->getMessage()}");
        } catch (JobConfigLoadingException $e) {
            throw new SubmissionEvaluationFailedException("Cannot load or parse job config - {$e->getMessage()}");
        } catch (Exception $e) {
            throw new SubmissionEvaluationFailedException("Cannot load results - " . $e->getMessage());
        }
    }
}
