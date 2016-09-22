<?php

namespace App\Helpers\EvaluationStatus;

use App\Model\Entity\Evaluation;

interface IEvaluable {

  function hasEvaluation(): bool;
  function getEvaluation(): Evaluation;
  function canBeEvaluated(): bool;

}
