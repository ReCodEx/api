<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Variable;

class JavaUtils
{
  const CURRENT_DIR = ".";
  const PATH_DELIM = ":";

  public static function constructClasspath(?Variable $jarFiles) {
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
