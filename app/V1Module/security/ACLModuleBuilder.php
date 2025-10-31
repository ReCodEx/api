<?php

namespace App\Security;

use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Literal;
use Nette\Utils\Strings;
use ReflectionClass;
use ReflectionMethod;
use ReflectionNamedType;

class ACLModuleBuilder
{
    public function getClassName($interfaceName, $uniqueId)
    {
        $interfaceName = Strings::after($interfaceName, '\\', -1) ?: $interfaceName;
        if (str_starts_with($interfaceName, "I")) {
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
        $class->setExtends(ACLModule::class);

        $interface = new ReflectionClass($interfaceName);

        $class->addMethod("getResourceName")->addBody('return ?;', [$name]);

        foreach ($interface->getMethods(ReflectionMethod::IS_ABSTRACT) as $method) {
            $isNameCorrect = str_starts_with($method->getName(), "can");
            /** @var ?ReflectionNamedType $methodReturnType */
            $methodReturnType = $method->getReturnType();
            $isBoolean = $methodReturnType !== null ? $methodReturnType->getName() === "bool" : false;

            if (!($isNameCorrect && $isBoolean)) {
                throw new \LogicException(sprintf('Method %s cannot be implemented automatically', $method->getName()));
            }

            $action = lcfirst(Strings::after($method->getName(), "can"));
            $methodImpl = $class->addMethod($method->getName());
            $methodImpl->setReturnType("bool");
            $contextStrings = [];

            foreach ($method->getParameters() as $parameter) {
                $contextStrings[] = sprintf('"%s" => $%s', $parameter->getName(), $parameter->getName());
                /** @var ?ReflectionNamedType $parameterType */
                $parameterType = $parameter->getType();
                $newParameter = $methodImpl->addParameter($parameter->getName())->setType(
                    $parameterType !== null ? $parameterType->getName() : null
                );
                if ($parameter->allowsNull()) {
                    $newParameter->setNullable();
                }
            }

            $methodImpl->addBody(
                'return $this->check(?, ?);',
                [
                    $action,
                    new Literal("[" . implode(", ", $contextStrings) . "]")
                ]
            );
        }

        return $class;
    }
}
