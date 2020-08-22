<?php

namespace App\Helpers\Evaluation;

/**
 * Class representing value leaf nodes with constant literals.
 */
class AstNodeValue extends AstNodeLeaf
{
    public static $TYPE_NAME = 'value';

    private const KEY_VALUE = 'value';
    private $value = null;

    /**
     * Get the value of the literal.
     * @return float
     * @throws AstNodeException
     */
    public function getValue(): float
    {
        $this->internalValidation(); // throws if value is not initialized
        return $this->value;
    }

    /**
     * Set the value of the literal.
     * @param float $value
     */
    public function setValue(float $value)
    {
        $this->value = $value;
    }

    /*
     * AstNode interface implementation/overrides
     */

    public function __construct(array $config = [])
    {
        parent::__construct($config);

        if ($config) {
            if (!array_key_exists(self::KEY_VALUE, $config) || !is_numeric($config[self::KEY_VALUE])) {
                throw new AstNodeException("The value AST node does not hold any value attribute.");
            }
            $this->setValue((float)$config[self::KEY_VALUE]);
        }
    }

    protected function internalValidation(array $testNames = [])
    {
        if ($this->value === null) {
            throw new AstNodeException("Value AST node does not have any actual value set.");
        }
    }

    public function evaluate(array $testResults): float
    {
        return $this->getValue();
    }

    public function serialize()
    {
        if (!$this->associatedData && $this->parent) {
            // optimization -- represent the value as scalar
            return $this->getValue();
        }

        // if this is the root node or we carry associated data -> fallback to full serialization
        $res = parent::serialize();
        $res[self::KEY_VALUE] = $this->getValue();
        return $res;
    }
}
