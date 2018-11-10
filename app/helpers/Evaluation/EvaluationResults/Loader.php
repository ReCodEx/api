<?php

namespace App\Helpers\EvaluationResults;

use App\Helpers\JobConfig\JobConfig;
use App\Exceptions\SubmissionEvaluationFailedException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Loader class providing factory method for parsing evaluation results
 */
class Loader {

  /**
   * Factory method for parsing evaluation results from YAML string.
   * @param string    $results YAML encoded results received from backend
   * @param JobConfig $config  Configuration of evaluated job
   * @return EvaluationResults Parsed evaluation results for the job
   * @throws SubmissionEvaluationFailedException when results are malformed
   */
  public static function parseResults(string $results, JobConfig $config): EvaluationResults {
    try {
      $parsedResults = Yaml::parse($results);
    } catch (ParseException $e) {
      throw new SubmissionEvaluationFailedException("YAML parsing error - {$e->getMessage()}");
    }

    return new EvaluationResults($parsedResults, $config);
  }


}
