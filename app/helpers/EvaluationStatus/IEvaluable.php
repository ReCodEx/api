<?php

namespace App\Helpers\EvaluationStatus;

use App\Model\Entity\SolutionEvaluation;

interface IEvaluable {

  function hasEvaluation(): bool;
  function getEvaluation(): SolutionEvaluation;
  function canBeEvaluated(): bool;

}
