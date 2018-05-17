<?php
namespace App\Helpers;

use Nette\Reflection\ClassType;
use Nette\Reflection\Method;
use Nette\StaticClassException;
use Nette\Utils\Strings;

class PermissionHints {
  public function __construct() {
    throw new StaticClassException();
  }

  public static function generate($aclModule, $subject) {
    $reflection = ClassType::from($aclModule);
    foreach ($reflection->getMethods(Method::IS_PUBLIC) as $method) {
      if (!Strings::startsWith($method->getName(), "can")) {
        continue;
      }

      if ($method->getNumberOfRequiredParameters() > 1 || $method->getNumberOfParameters() === 0) {
        continue;
      }

      $parameter = $method->getParameters()[0];
      $className = $parameter->getClass() ? $parameter->getClass()->getName() : null;
      if ($className !== null && $subject instanceof $className) {
        yield lcfirst(substr($method->getName(), 3)) => $method->invoke($aclModule, $subject);
      }
    }
  }

  public static function get($aclModule, $subject) {
    return iterator_to_array(static::generate($aclModule, $subject));
  }
}