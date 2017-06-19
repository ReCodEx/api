<?php
namespace App\Security;
use Nette;
use Nette\Utils\Arrays;


class SecurityExtension extends Nette\DI\CompilerExtension {
  private $tempDirectory;

  public function __construct($tempDirectory) {
    $this->tempDirectory = $tempDirectory;
  }

  public function loadConfiguration() {
    $acl = Arrays::get($this->config, "acl", []);
    $policies = Arrays::get($this->config, "policies", []);
    $configFilePath = Arrays::get($this->config, "config");
    $builder = $this->getContainerBuilder();

    $policyRegistry = $builder->addDefinition($this->prefix("policyRegistry"));
    $policyRegistry->setClass(PolicyRegistry::class);

    foreach ($policies as $name => $policy) {
      $serviceName = $this->prefix("policy_" . $name);
      $service = $builder->addDefinition($serviceName);
      $service->setClass($policy);

      $policyRegistry->addSetup("addPolicy", [ $service ]);
    }

    $loader = $builder->addDefinition($this->prefix("loader"));
    $loader->setClass(Loader::class, [ $this->tempDirectory . "/security", $configFilePath, $acl ]);

    $authorizator = $builder->addDefinition($this->prefix("authorizator"));
    $authorizator->setFactory(sprintf("@%s::loadAuthorizator", Loader::class));

    foreach ($acl as $name => $interface) {
      $module = $builder->addDefinition($this->prefix("aclModule_" . $name));
      $module->setClass($interface);
      $module->setFactory(sprintf('@%s::loadAclModule', Loader::class), [$name]);
    }
  }
}