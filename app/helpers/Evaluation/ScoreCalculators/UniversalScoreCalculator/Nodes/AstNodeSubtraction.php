<?php

namespace App\Helpers\Evaluation;

/**
 * Class representing binary function that comuptes A - B.
 */
class AstNodeSubtraction extends AstNodeBinary
{
    public static $TYPE_NAME = 'sub';

    public function evaluate(array $testResults): float
    {
        return $this->getLeft()->evaluate($testResults) - $this->getRight()->evaluate($testResults);
    }
}
