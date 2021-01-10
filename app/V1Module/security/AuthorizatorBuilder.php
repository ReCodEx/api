<?php

namespace App\Security;

use LogicException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\Dumper;
use Nette\PhpGenerator\PhpLiteral;
use Nette\PhpGenerator\Helpers;
use Nette\Security\Permission;
use Nette\Reflection;
use Nette\Utils\Arrays;
use ReflectionException;

class AuthorizatorBuilder
{
    private $checkCounter;
    private $dumper;

    public function __construct() {
        $this->dumper = new Dumper();
    }

    public function getClassName($uniqueId)
    {
        return "AuthorizatorImpl_" . $uniqueId;
    }

    public function build($aclInterfaces, $permissions, $uniqueId): ClassType
    {
        $this->checkCounter = 0;

        $class = new ClassType($this->getClassName($uniqueId));
        $class->addExtend(Authorizator::class);

        $check = $class->addMethod("checkPermissions");
        $check->setReturnType("bool");
        $check->addParameter("role")->setType("string");
        $check->addParameter("resource")->setType("string");
        $check->addParameter("privilege")->setType("string");

        foreach ($permissions as $i => $rule) {
            $all = new PhpLiteral(sprintf("%s::ALL", Permission::class));

            $allow = Arrays::get($rule, "allow", true);
            $role = Arrays::get($rule, "role", null);
            $resource = Arrays::get($rule, "resource", null);
            $interface = $resource !== null ? Reflection\ClassType::from(Arrays::get($aclInterfaces, $resource)) : null;
            $actions = Arrays::get($rule, "actions", []);
            $actions = $actions !== $all ? (array)$actions : $actions;

            $assertion = null;
            $conditions = (array)Arrays::get($rule, "conditions", []);

            if (count($conditions) > 0) {
                $assertion = $class->addMethod(sprintf("_assertion_%s", $i));

                $checkVariables = [];
                $condition = $this->loadConditionClauses($conditions, $interface, $actions, $checkVariables);

                foreach ($checkVariables as $variableName => $variableValue) {
                    $assertion->addBody("? = ?;", [new PhpLiteral($variableName), new PhpLiteral($variableValue)]);
                }

                $assertion->addBody("return ?;", [new PhpLiteral($condition)]);
            }

            $actionsString = '"' . implode('", "', $actions) . '"';

            $check->addBody(
                'if (? && ? && ? && ?) {',
                [
                    $role !== null ? new PhpLiteral(sprintf('$this->isInRole($role, "%s")', $role)) : true,
                    $resource !== null ? new PhpLiteral(sprintf('$resource === "%s"', $resource)) : true,
                    count($actions) > 0 ? new PhpLiteral(sprintf('in_array($privilege, [%s])', $actionsString)) : true,
                    $assertion !== null ? new PhpLiteral(sprintf('$this->%s()', $assertion->getName())) : true
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
            list($conditionTarget, $condition) = explode(".", $conditions, 2);

            foreach ($actions as $action) {
                $this->checkActionProvidesContext($interface, $action, $conditionTarget);
            }

            $checkVariable = "\$check_" . $this->checkCounter++;
            $checkValues[$checkVariable] = $this->dumper->format(
                '$this->policy->check(?, ?, $this->queriedIdentity)',
                new PhpLiteral(sprintf('$this->queriedContext["%s"]', $conditionTarget)),
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
            return $this->dumper->format("(?)", new PhpLiteral(join(" && ", $children)));
        } elseif ($type === "or") {
            return $this->dumper->format("(?)", new PhpLiteral(join(" || ", $children)));
        } else {
            return new PhpLiteral("true");
        }
    }

    private function checkActionProvidesContext(?Reflection\ClassType $interface, string $action, string $contextItem)
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
