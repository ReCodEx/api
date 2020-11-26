<?php

namespace App\Security;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpLiteral;
use Nette\Reflection;
use Nette\Reflection\Method;
use Nette\Utils\Strings;

class ACLModuleBuilder
{
    public function getClassName($interfaceName, $uniqueId)
    {
        $interfaceName = Strings::after($interfaceName, '\\', -1) ?: $interfaceName;
        if (Strings::startsWith($interfaceName, "I")) {
            $rest = Strings::after($interfaceName, "I");

            if (Strings::firstUpper($rest) === $rest) {
                $interfaceName = $rest;
            }
        }

        return $interfaceName . "Impl_" . $uniqueId;
    }

    /**
     * @param string $interfaceName
     * @param string $name
     * @param string $uniqueId
     * @return ClassType the newly created class
     */
    public function build($interfaceName, $name, $uniqueId): ClassType
    {
        $class = new ClassType($this->getClassName($interfaceName, $uniqueId));
        $class->addImplement($interfaceName);
        $class->addExtend(ACLModule::class);

        $interface = Reflection\ClassType::from($interfaceName);

        $class->addMethod("getResourceName")->addBody('return ?;', [$name]);

        foreach ($interface->getMethods(Method::IS_ABSTRACT) as $method) {
            $isNameCorrect = Strings::startsWith($method->getName(), "can");
            $isBoolean = $method->getReturnType() !== null ? $method->getReturnType()->getName() === "bool" : false;

            if (!($isNameCorrect && $isBoolean)) {
                throw new \LogicException(sprintf('Method %s cannot be implemented automatically', $method->getName()));
            }

            $action = lcfirst(Strings::after($method->getName(), "can"));
            $methodImpl = $class->addMethod($method->getName());
            $methodImpl->setReturnType("bool");
            $contextStrings = [];

            foreach ($method->getParameters() as $parameter) {
                $contextStrings[] = sprintf('"%s" => $%s', $parameter->getName(), $parameter->getName());
                $newParameter = $methodImpl->addParameter($parameter->getName())->setType(
                    $parameter->getType() !== null ? $parameter->getType()->getName() : null
                );
                if ($parameter->allowsNull()) {
                    $newParameter->setNullable();
                }
            }

            $methodImpl->addBody(
                'return $this->check(?, ?);',
                [
                    $action,
                    new PhpLiteral("[" . implode(", ", $contextStrings) . "]")
                ]
            );
        }

        return $class;
    }
}
