<?php

namespace App\Security;

use Nette\InvalidArgumentException;
use App\Security\Policies\IPermissionPolicy;

class PolicyRegistry
{
    /** @var IPermissionPolicy[] */
    private $policies = [];

    public function addPolicy($policy)
    {
        $this->policies[] = $policy;
    }

    public function get($resource, $policyName)
    {
        $this->findPolicyOrThrow($resource, $policyName);

        return function (Identity $queriedIdentity, $subject) use ($policyName) {
            return $this->check($subject, $policyName, $queriedIdentity);
        };
    }

    public function check($subject, $policyName, $queriedIdentity): bool
    {
        $policyObject = $this->findPolicyOrThrow($subject, $policyName);
        return $policyObject->$policyName($queriedIdentity, $subject);
    }

    private function findPolicyOrThrow($subject, $policyName): IPermissionPolicy
    {
        foreach ($this->policies as $policy) {
            $associatedClass = $policy->getAssociatedClass();
            if ($subject instanceof $associatedClass && method_exists($policy, $policyName)) {
                return $policy;
            }
        }

        throw new InvalidArgumentException(
            sprintf(
                "Policy '%s' not found for resource of type '%s'",
                $policyName,
                get_class($subject)
            )
        );
    }
}
