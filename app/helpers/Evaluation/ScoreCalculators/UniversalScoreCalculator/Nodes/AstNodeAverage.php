<?php

namespace App\Helpers\Evaluation;

/**
 * Class representing function that computes arithmetic average of all children.
 */
class AstNodeAverage extends AstNodeVariadic
{
    public static $TYPE_NAME = 'avg';

    public function evaluate(array $testResults): float
    {
        $this->internalValidation();
        
        if (count($this->children) === 0) {
            return 0.0; // this should not happen, but just to be safe...
        }

        $sum = 0.0;
        foreach ($this->children as $child) {
            $sum += $child->evaluate($testResults);
        }
        return $sum / (float)count($this->children);
    }
}
