<?php

namespace App\Security;

use Nette\InvalidArgumentException;
use App\Security\Policies\IPermissionPolicy;

class PolicyRegistry
{
    /** @var IPermissionPolicy[] */
    private $policies = [];
    private $basePolicy = null;

    public function addPolicy($policy)
    {
        $class = $policy->getAssociatedClass();
        if ($class) {
            $this->policies[] = $policy;
        } else {
            $this->basePolicy = $policy;
        }
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
        if ($subject !== null) {
            foreach ($this->policies as $policy) {
                // se need this search due to entity inheritance (TODO: find a better way in the future)
                $associatedClass = $policy->getAssociatedClass();
                if ($subject instanceof $associatedClass && method_exists($policy, $policyName)) {
                    return $policy;
                }
            }
        } elseif ($this->basePolicy !== null && method_exists($this->basePolicy, $policyName)) {
            // if no subject is given, lets try base policy...
            return $this->basePolicy;
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
