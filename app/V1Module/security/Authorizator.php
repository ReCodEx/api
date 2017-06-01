<?php

namespace App\Security;

use Nette\InvalidArgumentException;
use Nette\Neon\Neon;
use Nette\Security\User;
use App\Security\Policies\GroupPermissionPolicy;
use Nette\Security as NS;
use Nette\Utils\Arrays;
use TypeError;

class Authorizator implements IAuthorizator {
  /** @var NS\Permission */
  private $acl;

  /** @var Identity */
  private $queriedIdentity;

  /** @var PolicyRegistry */
  private $policy;

  private $configPath;

  public function __construct(string $configPath, PolicyRegistry $policy) {
    $this->policy = $policy;
    $this->configPath = $configPath;
  }

  private function setup() {
    $this->acl = new NS\Permission();

    $this->acl->addResource("groups");
    $this->acl->addResource("users");
    $this->acl->addResource("instances");

    $this->loadConfig();
  }

  private function loadConfig() {
    $config = Neon::decode(file_get_contents($this->configPath));

    if (!array_key_exists("roles", $config)) {
      throw new InvalidArgumentException("The configuration file does not contain a 'roles' section");
    }

    foreach ($config["roles"] as $roleDefinition) {
      $this->acl->addRole($roleDefinition["name"], (array) Arrays::get($roleDefinition, "parents", []));
    }

    foreach (Arrays::get($config, "permissions", []) as $ruleDefinition) {
      $role = Arrays::get($ruleDefinition, "role", NS\Permission::ALL);
      $resource = Arrays::get($ruleDefinition, "resource", NS\Permission::ALL);
      $actions = Arrays::get($ruleDefinition, "actions", NS\Permission::ALL);
      $actions = $actions !== NS\Permission::ALL ? (array) $actions : $actions;
      $conditions = (array) Arrays::get($ruleDefinition, "conditions", []);
      $callbacks = [];

      foreach ($conditions as $assertion) {
        $callbacks[] = $this->policy->get($resource, $assertion);
      }

      $this->acl->{Arrays::get($ruleDefinition, "allow", TRUE) ? "allow" : "deny"}(
        $role,
        $resource,
        $actions,
        $this->assert($callbacks)
      );
    }
  }

  private function assert($callbacks) {
    return function (NS\Permission $acl, $role, $resource, $privilege) use ($callbacks) {
      foreach ($callbacks as $callback) {
        try {
          if (!$callback($this->queriedIdentity, $acl->getQueriedResource())) {
            return FALSE;
          }
        } catch (TypeError $e) {
          return FALSE;
        }
      }

      return TRUE;
    };
  }

  public function isAllowed(Identity $identity, $resource, string $privilege): bool {
    $this->queriedIdentity = $identity;

    if ($this->acl === NULL) {
      $this->setup();
    }

    return $this->acl->isAllowed($identity->getRoles()[0], $resource, $privilege);
  }
}
