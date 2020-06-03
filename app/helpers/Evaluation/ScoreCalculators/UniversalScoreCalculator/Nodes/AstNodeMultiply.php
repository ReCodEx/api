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
        $sum = 1.0;
        foreach ($this->children as $child) {
            $sum *= $child->evaluate($testResults);
        }
        return $sum;
    }
}
