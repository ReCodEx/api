<?php

namespace App\Helpers;

interface IScoreCalculator {
  public function computeScore(array $testResults);
  public static function isScoreConfigValid(string $scoreConfig);
}
