<?php

namespace App\Helpers\Evaluation;

/**
 * Class representing function that returns minimal value of all children.
 */
class AstNodeMinimum extends AstNodeVariadic
{
    public static $TYPE_NAME = 'min';

    public function evaluate(array $testResults): float
    {
        $this->internalValidation();

        $results = [];
        foreach ($this->children as $child) {
            $results[] = $child->evaluate($testResults);
        }
        return min($results);
    }
}
