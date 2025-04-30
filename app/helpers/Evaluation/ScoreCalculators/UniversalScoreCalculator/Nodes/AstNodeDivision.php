<?php

namespace App\Helpers\Evaluation;

/**
 * Class representing binary function that computes A / B.
 * If B == 0, the result is (unexpectedly) zero.
 */
class AstNodeDivision extends AstNodeBinary
{
    public static $TYPE_NAME = 'div';

    public function evaluate(array $testResults): float
    {
        $divisor = $this->getRight()->evaluate($testResults);
        return $divisor !== 0.0 ? $this->getLeft()->evaluate($testResults) / $divisor : 0.0;
    }
}
