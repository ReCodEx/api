<?php

namespace App\Helpers\Evaluation;

/**
 * Base class for all nodes representing unary operations (must have 1 child).
 */
abstract class AstNodeUnary extends AstNode
{
    protected function internalValidation(array $testNames = [])
    {
        if (count($this->children) !== 1) {
            throw new AstNodeException(
                sprintf("Unary AST nodes must have exactly one child (but %d children found).", count($this->children))
            );
        }
    }

    public function addChild(AstNode $child)
    {
        if (count($this->children) >= 1) {
            throw new AstNodeException("Unary AST nodes must have exactly one child. Unable to add another child.");
        }
        parent::addChild($child);
    }

    /**
     * Get the only operand of this node.
     * @return AstNode
     * @throws AstNodeException if the child node is missing
     */
    public function getOperand(): AstNode
    {
        if (count($this->children) === 0) {
            throw new AstNodeException("Unary node is not fully initialized yet, its child node is missing.");
        }
        return $this->children[0];
    }
}
