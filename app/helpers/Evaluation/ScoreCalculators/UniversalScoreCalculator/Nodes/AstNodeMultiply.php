<?php

namespace App\Helpers\Evaluation;

/**
 * Class representing function that multiplies the results of all children.
 */
class AstNodeMultiply extends AstNodeVariadic
{
    public static $TYPE_NAME = 'mul';

    public function evaluate(array $testResults): float
    {
        $this->internalValidation();

        $mul = 1.0;
        foreach ($this->children as $child) {
            $mul *= $child->evaluate($testResults);
        }
        return $mul;
    }
}
