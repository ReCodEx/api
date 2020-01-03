<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Variable;

class JavaUtils
{
    const CURRENT_DIR = ".";
    const PATH_DELIM = ":";

    public static function constructClasspath(?Variable $jarFiles)
    {
        if ($jarFiles && !$jarFiles->isEmpty()) {
            $classpath = JavaUtils::CURRENT_DIR;
            foreach ($jarFiles->getValueAsArray() as $jar) {
                $classpath .= JavaUtils::PATH_DELIM . $jar;
            }

            return [
                "-classpath",
                $classpath
            ];
        }

        return [];
    }
}
