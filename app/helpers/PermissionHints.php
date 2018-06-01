<?php
namespace App\Helpers;

use Generator;
use Nette\Reflection\ClassType;
use Nette\Reflection\Method;
use Nette\StaticClassException;
use Nette\Utils\Strings;

class PermissionHints {
  protected static $methodCache = [];

  public function __construct() {
    throw new StaticClassException();
  }

  /**
   * Get a generator of a permission hints array for an ACL module and a resource object
   * @param $aclModule object An ACL module
   * @param $subject object The resource checked for permissions
   * @return Generator
   */
  public static function generate($aclModule, $subject) {
    foreach (static::getAclMethods($aclModule) as $method) {
      $parameter = $method->getParameters()[0];
      $className = $parameter->getClass() ? $parameter->getClass()->getName() : null;
      if ($className !== null && $subject instanceof $className) {
        yield lcfirst(substr($method->getName(), 3)) => $method->invoke($aclModule, $subject);
      }
    }
  }

  /**
   * Get an array of permission hints for an ACL module and a resource object
   * @param $aclModule object An ACL module
   * @param $subject object The resource checked for permissions
   * @return bool[] an associative array where keys are action names and values are boolean flags
   */
  public static function get($aclModule, $subject) {
    return iterator_to_array(static::generate($aclModule, $subject));
  }

  /**
   * Find single-parameter ACL check methods on an ACL module - i.e. public methods whose name starts with "can" and
   * that do not have more than one required parameter.
   * @param $aclModule
   * @return Generator
   */
  protected static function generateAclMethods($aclModule) {
    $reflection = ClassType::from($aclModule);
    foreach ($reflection->getMethods(Method::IS_PUBLIC) as $method) {
      if (!Strings::startsWith($method->getName(), "can")) {
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
   * @param $aclModule
   * @return Method[]
   */
  protected static function getAclMethods($aclModule) {
    $class = get_class($aclModule);

    if (!array_key_exists($class, static::$methodCache)) {
      static::$methodCache[$class] = iterator_to_array(static::generateAclMethods($aclModule));
    }

    return static::$methodCache[$class];
  }
}