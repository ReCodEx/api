<?php

namespace App\Helpers\Evaluation;

/**
 * Class representing function that returns maximal value of all children.
 */
class AstNodeMaximum extends AstNodeVariadic
{
    public static $TYPE_NAME = 'max';

    public function evaluate(array $testResults): float
    {
        $results = [];
        foreach ($this->children as $child) {
            $results[] = $child->evaluate($testResults);
        }
        return max($results);
    }
}
