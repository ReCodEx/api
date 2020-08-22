<?php

namespace App\Helpers\Evaluation;

/**
 * Class representing variadic operations with arbitrary (nonzero) number of children.
 */
abstract class AstNodeVariadic extends AstNode
{
    protected function internalValidation(array $testNames = [])
    {
        if (count($this->children) === 0) {
            throw new AstNodeException("Variadic AST nodes expect at least one child (but no children found).");
        }
    }
}
