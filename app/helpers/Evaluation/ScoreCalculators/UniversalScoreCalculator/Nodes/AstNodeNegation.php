<?php

namespace App\Helpers\Evaluation;

/**
 * Class representing unary negation.
 */
class AstNodeNegation extends AstNodeUnary
{
    public static $TYPE_NAME = 'neg';

    public function evaluate(array $testResults): float
    {
        return -$this->getChild()->evaluate($testResults);
    }
}
