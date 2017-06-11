<?php
namespace App\Security;


use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpLiteral;
use Nette\Reflection;
use Nette\Reflection\Method;
use Nette\Utils\Strings;

class ACLModuleBuilder {
  public function getClassName($interfaceName) {
    $interfaceName = Strings::after($interfaceName, '\\', -1) ?: $interfaceName;
    if (Strings::startsWith($interfaceName, "I")) {
      $rest = Strings::after($interfaceName, "I");

      if (Strings::firstUpper($rest) === $rest) {
        $interfaceName = $rest;
      }
    }

    return $interfaceName . "Impl";
  }

  public function build($interfaceName, $name): ClassType {
    $class = new ClassType($this->getClassName($interfaceName));
    $class->addImplement($interfaceName);
    $class->addExtend(ACLModule::class);

    $interface = Reflection\ClassType::from($interfaceName);

    $class->addMethod("getResourceName")->addBody('return ?;', [$name]);

    foreach ($interface->getMethods(Method::IS_ABSTRACT) as $method) {
      $isNameCorrect = Strings::startsWith($method->getName(), "can");
      $isBoolean = (string) $method->getReturnType() === "bool";

      if (!($isNameCorrect && $isBoolean)) {
        throw new \LogicException(sprintf('Method %s cannot be implemented automatically', $method->getName()));
      }

      $action = lcfirst(Strings::after($method->getName(), "can"));
      $methodImpl = $class->addMethod($method->getName());
      $methodImpl->setReturnType("bool");
      $contextStrings = [];

      foreach ($method->getParameters() as $parameter) {
        $contextStrings[] = sprintf('"%s" => $%s', $parameter->getName(), $parameter->getName());
        $methodImpl->addParameter($parameter->getName())->setTypeHint((string) $parameter->getType());
      }

      $methodImpl->addBody(
        'return $this->check(?, ?);', [
          $action,
          new PhpLiteral("[" . implode(", ", $contextStrings) . "]")
        ]
      );
    }

    return $class;
  }
}