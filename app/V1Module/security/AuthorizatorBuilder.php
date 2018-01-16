<?php
namespace App\Security;

use LogicException;
use Nette\PhpGenerator\ClassType;
use Nette\PhpGenerator\PhpLiteral;
use Nette\PhpGenerator\Helpers;
use Nette\Security\Permission;
use Nette\Reflection;
use Nette\Utils\Arrays;
use ReflectionException;

class AuthorizatorBuilder {
  private $checkCounter;

  public function getClassName($uniqueId) {
    return "AuthorizatorImpl_" . $uniqueId;
  }

  public function build($aclInterfaces, $roles, $permissions, $uniqueId): ClassType {
    $this->checkCounter = 0;

    $class = new ClassType($this->getClassName($uniqueId));
    $class->addExtend(Authorizator::class);
    $setup = $class->addMethod("setup");
    $setup->setVisibility("protected");

    foreach ($roles as $role) {
      $setup->addBody('$this->addRole(?, [...?]);', [
        $role["name"],
        (array) Arrays::get($role, "parents", []),
      ]);
    }

    $check = $class->addMethod("checkPermissions");
    $check->setReturnType("bool");
    $check->addParameter("role")->setTypeHint("string");
    $check->addParameter("resource")->setTypeHint("string");
    $check->addParameter("privilege")->setTypeHint("string");

    foreach ($permissions as $i => $rule) {
      $all = new PhpLiteral(sprintf("%s::ALL", Permission::class));

      $allow = Arrays::get($rule, "allow", TRUE);
      $role = Arrays::get($rule, "role", NULL);
      $resource = Arrays::get($rule, "resource", NULL);
      $interface = $resource !== NULL ? Reflection\ClassType::from(Arrays::get($aclInterfaces, $resource)) : NULL;
      $actions = Arrays::get($rule, "actions", []);
      $actions = $actions !== $all ? (array) $actions : $actions;

      $assertion = NULL;
      $conditions = (array) Arrays::get($rule, "conditions", []);

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

      $check->addBody('if (? && ? && ? && ?) {', [
        $role !== NULL ? new PhpLiteral(sprintf('$this->isInRole($role, "%s")', $role)) : TRUE,
        $resource !== NULL ? new PhpLiteral(sprintf('$resource === "%s"', $resource)) : TRUE,
        count($actions) > 0 ? new PhpLiteral(sprintf('in_array($privilege, [%s])', $actionsString)) : TRUE,
        $assertion !== NULL ? new PhpLiteral(sprintf('$this->%s()', $assertion->getName())) : TRUE
      ]);
      $check->addBody('return ?;', [$allow]);
      $check->addBody('}');
    }

    $check->addBody('return FALSE;');

    return $class;
  }

  private function loadConditionClauses($conditions, $interface, &$actions, array &$checkValues) {
    $type = "and";

    if (!is_array($conditions)) {
      list($conditionTarget, $condition) = explode(".", $conditions, 2);

      foreach ($actions as $action) {
        $this->checkActionProvidesContext($interface, $action, $conditionTarget);
      }

      $checkVariable = "\$check_" . $this->checkCounter++;
      $checkValues[$checkVariable] = Helpers::format(
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
      } else if (array_key_exists("and", $conditions)) {
        $conditions = $conditions["and"];
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
      return Helpers::format("(?)", new PhpLiteral(join(" && ", $children)));
    } else if ($type === "or") {
      return Helpers::format("(?)", new PhpLiteral(join(" || ", $children)));
    } else {
      return new PhpLiteral("true");
    }
  }

  private function checkActionProvidesContext(?Reflection\ClassType $interface, string $action, string $contextItem) {
    if ($interface === NULL) {
      throw new LogicException("No resource was specified for this rule - context (and condition checking) is not available");
    }

    try {
      $method = $interface->getMethod("can" . ucfirst($action));
    } catch (ReflectionException $e) {
      throw new LogicException(sprintf(
        "No method for action '%s' exists in interface '%s'",
        $action,
        $interface->getName()
      ));
    }

    foreach ($method->getParameters() as $parameter) {
      if ($parameter->getName() === $contextItem) {
        return;
      }
    }

    throw new LogicException(sprintf(
      "Context item '%s' is not available for action '%s' of interface '%s'",
      $contextItem,
      $action,
      $interface->getName()
    ));
  }
}
