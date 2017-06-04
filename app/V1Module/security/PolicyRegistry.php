<?php
namespace App\Security;

use Nette\InvalidArgumentException;
use App\Security\Policies\IPermissionPolicy;
use Nette\Reflection\Method;
use Nette\Utils\Arrays;

class PolicyRegistry {
  /** @var IPermissionPolicy[] */
  private $policies = [];

  public function addPolicy($resource, $policy) {
    $this->policies[$resource] = $policy;
  }

  public function get($resource, $policyName) {
    if (!array_key_exists($resource, $this->policies)) {
      throw new InvalidArgumentException(sprintf("Unknown resource '%s'", $resource));
    }

    if (!method_exists($this->policies[$resource], $policyName)) {
      throw new InvalidArgumentException(sprintf("Unknown policy '%s' for resource '%s'", $policyName, $resource));
    }

    return function (Identity $queriedUser, $context) use ($policyName, $resource) {
      $policyObject = $this->policies[$resource];
      $contextId = Arrays::get($context, $resource, NULL);
      $contextObject = $contextId !== NULL ? $policyObject->getByID($contextId) : NULL;

      if ($contextObject == NULL) {
        $reflection = Method::from($policyObject, $policyName);
        if (!$reflection->parameters[1]->optional) {
          return FALSE;
        }
      }

      return $policyObject->$policyName($queriedUser, $contextObject);
    };
  }
}