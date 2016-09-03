<?php

namespace App\Model\Helpers\EvaluationResults;

use App\Exception\SubmissionEvaluationFailedException;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class Loader {

  public static function parseResults(string $results): EvaluationResults {
    try {
      $parsedResults = Yaml::parse($results);
    } catch (ParseException $e) {
      throw new SubmissionEvaluationFailedException("The results received from the file server are malformed.");
    }

    return new EvaluationResults($parsedResults);
  }

  
}
