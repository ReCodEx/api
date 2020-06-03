<?php

namespace App\Helpers\Evaluation;

/**
 * Base class for all nodes representing binary operations (must have 2 children).
 */
abstract class AstNodeBinary extends AstNode
{
    protected function internalValidation(array $testNames = [])
    {
        if (count($this->children) !== 2) {
            throw new AstNodeException(
                sprintf(
                    "Binary AST nodes must have exactly two children (but %d children found).",
                    count($this->children)
                )
            );
        }
    }
    
    public function addChild(AstNode $child)
    {
        if (count($this->children) >= 2) {
            throw new AstNodeException("Binary AST nodes must have exactly two children. Unable to add third child.");
        }
        parent::addChild($child);
    }

    /**
     * Get left (first) operand of this node.
     * @return AstNode
     */
    public function getLeft(): AstNode
    {
        return $this->children[0];
    }

    /**
     * Get right (second) operand of this node.
     * @return AstNode
     */
    public function getRight(): AstNode
    {
        return $this->children[1];
    }
}
