<?php

namespace App\Helpers\Evaluation;

/**
 * Base class for all AST nodes representing universal score configuration.
 */
abstract class AstNode
{
    // keys used in serialized structure
    private const KEY_TYPE = 'type';
    private const KEY_CHILDREN = 'children';

    // List of all known classes of concrete AstNodes (reflection would be too slow)
    private static $knownAstNodeClasses = [
        AstNodeAverage::class,
        AstNodeClamp::class,
        AstNodeDivision::class,
        AstNodeMaximum::class,
        AstNodeMinimum::class,
        AstNodeMultiply::class,
        AstNodeNegation::class,
        AstNodeSubtraction::class,
        AstNodeSum::class,
        AstNodeTestResult::class,
        AstNodeValue::class,
    ];

    /**
     * Cache for all known nodes (type name => representing class)
     */
    private static $typesCache = null;

    private static function getClassTypes(): array
    {
        if (self::$typesCache === null) {
            foreach (self::$knownAstNodeClasses as $className) {
                $typesCache[$className::$TYPE_NAME] = $className;
            }
        }
        return self::$typesCache;
    }

    /**
     * Deserialization routine that creates AST node from config structure (array).
     * It determines the right type of the AST node, create appropriate class and pass
     * the config structure to the constructor.
     * @param array $config Config structure deserialized from Json or Yaml
     * @throws AstNodeException if deserialization fails
     */
    public static function createFromConfig(array $config): AstNode
    {
        $types = self::getClassTypes();

        if (!array_key_exists(self::KEY_TYPE, $config)) {
            throw new AstNodeException("Node type is not specified in the score config.");
        }
        
        $type = $config[self::KEY_TYPE];
        if (!array_key_exists($type, $types)) {
            throw new AstNodeException("Unknown AST node type '$type' found in the score config.");
        }

        // construct the node
        $class = $types[$type];
        $node = new $class($config);

        return $node;
    }

    // Default value for per-class static variable accessed by late static binding
    public static $TYPE_NAME = '';

    /**
     * Return type identifier for given node class.
     * This identifier is used in (de)serialization.
     * @return string
     */
    public function getTypeName(): string
    {
        return static::$TYPE_NAME;
    }

    // Internal data common to all nodes
    protected $parent = null; // reference to parent node
    protected $children = []; // list of subnodes
    protected $associatedData = []; // keeps extra data (with `x-` prefix) from deserialization, so they are not lost

    /**
     * Create and initialize empty instance of the node.
     * @param array $config Optional parameter used in deserialization.
     *                      Data from $config are deserialized into the node.
     * @throws AstNodeException if config data are not valid and deserialization fails
     */
    public function __construct(array $config = [])
    {
        // load children recursively
        if (array_key_exists(self::KEY_CHILDREN, $config)) {
            if (!is_array($config[self::KEY_CHILDREN])) {
                throw new AstNodeException("AST node children must be represented as array.");
            }

            foreach ($config[self::KEY_CHILDREN] as $childConfig) {
                if (is_numeric($childConfig)) {
                    // the only special case in the config (number literal may be encoded as scalar values)
                    $child = new AstNodeValue();
                    $child->setValue((float)$childConfig);
                } else {
                    // regular case
                    if (!is_array($childConfig)) {
                        throw new AstNodeException("AST node configuration must be an associative array.");
                    }
                    $child = self::createFromConfig($childConfig);
                }
                $this->addChild($child);
            }
        }

        $this->associatedData = array_filter($config, function ($key) {
            return substr($key, 0, 2) === 'x-'; // only extension fields are kept (without interpreting)
        }, ARRAY_FILTER_USE_KEY);
    }

    /**
     * Append another child node to this node.
     * @param AstNode $child New node to be appended
     */
    public function addChild(AstNode $child)
    {
        if ($child->parent) {
            throw new AstNodeException("Unable to add child to a node. The child already belongs to another node.");
        }
        $child->parent = $this;
        $this->children[] = $child;
    }

    /**
     * Perform internal validation of the node itself (not the subtree).
     * This method stands aside, so it can be reused and the main interface
     * (validate() method) does not have to be overriden in derived classes.
     * @param array $testNames List of allowed test names (array of strings)
     * @throws AstNodeException if something is not valid
     */
    protected function internalValidation(array $testNames = [])
    {
        // expected to be overriden in derived classes
    }

    /**
     * Validate this node and its subtree.
     * @param array $testNames List of allowed test names (array of strings)
     * @throws AstNodeException if something is not valid
     */
    public function validate(array $testNames = [])
    {
        // default behavior only ensures that all nodes in the subtree are validated
        foreach ($this->children as $child) {
            if (!$child instanceof AstNode) {
                throw new AstNodeException("Invalid structure. Node which is not instance of AstNode was found.");
            }
            $child->validate($testNames);
        }

        $this->internalValidation($testNames);
    }
    
    /**
     * Compute the value of this node.
     * @param array $testResults Array with test results (keys are test names) which may be used in evaluation.
     * @return float Computed score value
     * @throws AstNodeException if something fails (should not throw if the AST structure is valid)
     */
    abstract public function evaluate(array $testResults): float;

    /**
     * Return an array representation of the node and its subtree.
     * The array is ready to be serialized as JSON or YAML.
     * @return mixed array in most cases, literals may be used for special well-known nodes
     * @throws AstNodeException if something fails (should not throw if the AST structure is valid)
     */
    public function serialize()
    {
        $res = $this->associatedData;
        $res[self::KEY_TYPE] = $this->getTypeName();

        // serialize children recursively
        if ($this->children) {
            $res[self::KEY_CHILDREN] = array_map(function ($child) {
                return $child->serialize();
            }, $this->children);
        }

        return $res;
    }
}
