<?php

namespace App\Helpers;

use Nette\StaticClass;
use ReflectionNamedType;
use ReflectionClass;
use ReflectionMethod;
use Generator;

class PermissionHints
{
    use StaticClass;

    protected static $methodCache = [];

    /**
     * Get a generator of a permission hints array for an ACL module and a resource object
     * @param object $aclModule An ACL module
     * @param object $subject The resource checked for permissions
     * @return Generator
     */
    public static function generate($aclModule, $subject)
    {
        foreach (static::getAclMethods($aclModule) as $method) {
            $parameter = $method->getParameters()[0];
            /** @var ?ReflectionNamedType $classObj */
            $classObj = $parameter->getType();
            $className = $classObj ? $classObj->getName() : null;
            if ($className !== null && $subject instanceof $className) {
                yield lcfirst(substr($method->getName(), 3)) => $method->invoke($aclModule, $subject);
            }
        }
    }

    /**
     * Get an array of permission hints for an ACL module and a resource object
     * @param object $aclModule An ACL module
     * @param object $subject The resource checked for permissions
     * @return bool[] an associative array where keys are action names and values are boolean flags
     */
    public static function get($aclModule, $subject)
    {
        return iterator_to_array(static::generate($aclModule, $subject));
    }

    /**
     * Find single-parameter ACL check methods on an ACL module - i.e. public methods whose name starts with "can" and
     * that do not have more than one required parameter.
     * @param object $aclModule
     * @return Generator
     */
    protected static function generateAclMethods($aclModule)
    {
        $reflectionClass = new ReflectionClass($aclModule);
        foreach ($reflectionClass->getMethods(ReflectionMethod::IS_PUBLIC) as $method) {
            if (!str_starts_with($method->getName(), "can")) {
                continue;
            }

            if ($method->getNumberOfRequiredParameters() > 1 || $method->getNumberOfParameters() === 0) {
                continue;
            }

            yield $method;
        }
    }

    /**
     * Get an array of ACL method reflections for an ACL module. The results are cached for better performance.
     * @param object $aclModule
     * @return ReflectionMethod[]
     */
    protected static function getAclMethods($aclModule)
    {
        $class = get_class($aclModule);

        if (!array_key_exists($class, static::$methodCache)) {
            static::$methodCache[$class] = iterator_to_array(static::generateAclMethods($aclModule));
        }

        return static::$methodCache[$class];
    }
}
