<?php

namespace App\Helpers\Evaluation;

/**
 * Base class for all leaf nodes (nodes without children).
 */
abstract class AstNodeLeaf extends AstNode
{
    protected function internalValidation(array $testNames = [])
    {
        if (count($this->children) > 0) {
            throw new AstNodeException(
                sprintf("AST leaf nodes must not have children (but %d children found).", count($this->children))
            );
        }
    }

    public function addChild(AstNode $child)
    {
        throw new AstNodeException("AST leaf nodes must not have children, so no child may be added.");
    }
}
