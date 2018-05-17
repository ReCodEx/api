<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Variable;

trait JavaUtilsTrait
{
  protected function constructClasspath(?Variable $jarFiles) {
    if ($jarFiles && !$jarFiles->isEmpty()) {
      $classpath = ".";
      foreach ($jarFiles->getValueAsArray() as $jar) {
        $classpath .= ":" . $jar;
      }

      return [
        "-classpath",
        $classpath
      ];
    }

    return [];
  }
}
