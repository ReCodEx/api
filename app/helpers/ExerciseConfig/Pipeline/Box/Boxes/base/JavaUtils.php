<?php

namespace App\Helpers\ExerciseConfig\Pipeline\Box;

use App\Helpers\ExerciseConfig\Variable;

class JavaUtils
{
    const CURRENT_DIR = ".";
    const PATH_DELIM = ":";

    public static function constructClasspath(?Variable $jarFiles, ?string $compiledClassesDirectory = null, ?Variable $classpath = null)
    {
        $result = [];

        // jar filed specified by exercise author
        if ($jarFiles && !$jarFiles->isEmpty()) {
            foreach ($jarFiles->getValueAsArray() as $jar) {
                $result[] = $jar;
            }
        }

        if (!empty($compiledClassesDirectory)) {
            $result[] = $compiledClassesDirectory;
        }

        // might be used for worker local jar files (groovy stdlib or kotlin stdlib)
        if ($classpath && !$classpath->isEmpty()) {
            foreach ($classpath->getValueAsArray() as $cp) {
                $result[] = $cp;
            }
        }

        if (empty($result)) {
            return [];
        }

        return ["-classpath", JavaUtils::CURRENT_DIR . self::PATH_DELIM . join(self::PATH_DELIM, $result)];
    }
}
