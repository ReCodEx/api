<?php
namespace App\Security;

use Nette\InvalidArgumentException;
use App\Security\Policies\IPermissionPolicy;
use Nette\Reflection\Method;
use Nette\Utils\Arrays;

class PolicyRegistry {
  /** @var IPermissionPolicy[] */
  private $policies = [];

  public function addPolicy($policy) {
    $this->policies[] = $policy;
  }

  public function get($resource, $policyName) {
    $policy = $this->findPolicyOrThrow($resource);
    $this->checkPolicy($policy, $policyName);

    return function (Identity $queriedIdentity, $subject) use ($policyName, $resource) {
      return $this->check($subject, $policyName, $queriedIdentity);
    };
  }

  public function check($subject, $policyName, $queriedIdentity): bool {
    $policyObject = $this->findPolicyOrThrow($subject);
    $this->checkPolicy($policyObject, $policyName);

    return $policyObject->$policyName($queriedIdentity, $subject);
  }

  private function findPolicyOrThrow($subject): IPermissionPolicy {
    foreach ($this->policies as $policy) {
      $associatedClass = $policy->getAssociatedClass();
      if ($subject instanceof $associatedClass) {
        return $policy;
      }
    }

    throw new InvalidArgumentException(sprintf("No policy for resource of type '%s'", get_class($subject)));
  }

  private function checkPolicy($policy, $policyName): void {
    if (!method_exists($policy, $policyName)) {
      throw new InvalidArgumentException(sprintf(
        "Unknown policy '%s' for class '%s'",
        $policyName,
        get_class($policy)
      ));
    }
  }
}