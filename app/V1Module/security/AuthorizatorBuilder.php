<?php

namespace App\Security;

use LogicException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\Literal;
use Nette\Utils\Arrays;
use ReflectionClass;
use ReflectionException;

class AuthorizatorBuilder
{
    private $checkCounter;
    private $dumper;

    public function __construct()
    {
        $this->dumper = new Dumper();
    }

    public function getClassName($uniqueId)
    {
        return "AuthorizatorImpl_" . $uniqueId;
    }

    public function build($aclInterfaces, array $permissions, $uniqueId): ClassType
    {
        $this->checkCounter = 0;

        $class = new ClassType($this->getClassName($uniqueId));
        $class->setExtends(Authorizator::class);

        $check = $class->addMethod("checkPermissions");
        $check->setReturnType("bool");
        $check->addParameter("role")->setType("string");
        $check->addParameter("resource")->setType("string");
        $check->addParameter("privilege")->setType("string");

        foreach ($permissions as $i => $rule) {
            if (!is_array($rule)) {
                continue;
            }

            $allow = Arrays::get($rule, "allow", true);
            $role = Arrays::get($rule, "role", null);
            $resource = Arrays::get($rule, "resource", null);
            $interface = $resource !== null ? new ReflectionClass(Arrays::get($aclInterfaces, $resource) ?? '') : null;
            $actions = (array)Arrays::get($rule, "actions", []);

            $assertion = null;
            $conditions = (array)Arrays::get($rule, "conditions", []);

            if (count($conditions) > 0) {
                $assertion = $class->addMethod(sprintf("_assertion_%s", $i));

                $checkVariables = [];
                $condition = $this->loadConditionClauses($conditions, $interface, $actions, $checkVariables);

                foreach ($checkVariables as $variableName => $variableValue) {
                    $assertion->addBody("? = ?;", [new Literal($variableName), new Literal($variableValue)]);
                }

                $assertion->addBody("return ?;", [new Literal($condition)]);
            }

            $actionsString = '"' . implode('", "', $actions) . '"';

            $check->addBody(
                'if (? && ? && ? && ?) {',
                [
                    $role !== null ? new Literal(sprintf('$this->isInRole($role, "%s")', $role)) : true,
                    $resource !== null ? new Literal(sprintf('$resource === "%s"', $resource)) : true,
                    count($actions) > 0 ? new Literal(sprintf('in_array($privilege, [%s])', $actionsString)) : true,
                    $assertion !== null ? new Literal(sprintf('$this->%s()', $assertion->getName())) : true
                ]
            );
            $check->addBody('return ?;', [$allow]);
            $check->addBody('}');
        }

        $check->addBody('return false;');

        return $class;
    }

    private function loadConditionClauses($conditions, $interface, &$actions, array &$checkValues)
    {
        $type = "and";

        if (!is_array($conditions)) {
            $tokens = explode(".", $conditions, 2);
            if (count($tokens) === 1) {
                // if '.' is missing ... let's use base policy class (identified by null target)
                $conditionTarget = null;
                $condition = $conditions;
            } else {
                list($conditionTarget, $condition) = $tokens;
            }

            foreach ($actions as $action) {
                $this->checkActionProvidesContext($interface, $action, $conditionTarget);
            }

            $checkVariable = "\$check_" . $this->checkCounter++;
            $checkValues[$checkVariable] = $this->dumper->format(
                '$this->policy->check(?, ?, $this->queriedIdentity)',
                $conditionTarget ? new Literal(sprintf('$this->queriedContext["%s"]', $conditionTarget)) : null,
                $condition
            );

            return $checkVariable;
        }

        if (count($conditions) === 1) {
            if (array_key_exists("or", $conditions)) {
                $type = "or";
                $conditions = $conditions["or"];
            } else {
                if (array_key_exists("and", $conditions)) {
                    $conditions = $conditions["and"];
                }
            }
        }

        if (!Arrays::isList($conditions)) {
            throw new LogicException("Incorrect usage of and/or condition clauses");
        }

        $children = [];
        foreach ($conditions as $condition) {
            $children[] = $this->loadConditionClauses($condition, $interface, $actions, $checkValues);
        }

        if ($type === "and") {
            return $this->dumper->format("(?)", new Literal(join(" && ", $children)));
        } /* @phpstan-ignore identical.alwaysTrue */ elseif ($type === "or") {
            return $this->dumper->format("(?)", new Literal(join(" || ", $children)));
        } else {
            return new Literal("true");
        }
    }

    private function checkActionProvidesContext(?ReflectionClass $interface, string $action, ?string $contextItem)
    {
        if ($interface === null) {
            throw new LogicException(
                "No resource was specified for this rule - context (and condition checking) is not available"
            );
        }

        try {
            $method = $interface->getMethod("can" . ucfirst($action));
        } catch (ReflectionException $e) {
            throw new LogicException(
                sprintf(
                    "No method for action '%s' exists in interface '%s'",
                    $action,
                    $interface->getName()
                )
            );
        }

        if ($contextItem === null) {
            return;
        }

        foreach ($method->getParameters() as $parameter) {
            if ($parameter->getName() === $contextItem) {
                return;
            }
        }

        throw new LogicException(
            sprintf(
                "Context item '%s' is not available for action '%s' of interface '%s'",
                $contextItem,
                $action,
                $interface->getName()
            )
        );
    }
}
