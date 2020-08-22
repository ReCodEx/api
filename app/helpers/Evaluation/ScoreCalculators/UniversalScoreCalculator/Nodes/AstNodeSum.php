<?php

namespace App\Helpers\Evaluation;

/**
 * Class representing function that adds the results of all children.
 */
class AstNodeSum extends AstNodeVariadic
{
    public static $TYPE_NAME = 'sum';

    public function evaluate(array $testResults): float
    {
        $this->internalValidation();

        $sum = 0.0;
        foreach ($this->children as $child) {
            $sum += $child->evaluate($testResults);
        }
        return $sum;
    }
}
