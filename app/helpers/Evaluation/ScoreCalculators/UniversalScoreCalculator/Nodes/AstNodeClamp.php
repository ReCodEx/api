<?php

namespace App\Helpers\Evaluation;

/**
 * Class representing unary clamping function that forces the value between 0 and 1.
 * Values less than 0 are raised to 0, values greater than 1 are lowered to 1.
 */
class AstNodeClamp extends AstNodeUnary
{
    public static $TYPE_NAME = 'clamp';

    public function evaluate(array $testResults): float
    {
        $value = $this->getOperand()->evaluate($testResults);
        return min(1.0, max(0.0, $value));
    }
}
