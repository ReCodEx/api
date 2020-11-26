<?php

namespace App\Security;

use Nette;
use Nette\Utils\Arrays;

class SecurityExtension extends Nette\DI\CompilerExtension
{
    private $tempDirectory;

    public function __construct($tempDirectory)
    {
        $this->tempDirectory = $tempDirectory;
    }

    public function loadConfiguration()
    {
        $acl = Arrays::get($this->config, "acl", []);
        $policies = Arrays::get($this->config, "policies", []);
        $configFilePath = Arrays::get($this->config, "config");
        $builder = $this->getContainerBuilder();

        $policyRegistry = $builder->addDefinition($this->prefix("policyRegistry"));
        $policyRegistry->setType(PolicyRegistry::class);

        foreach ($policies as $name => $policy) {
            $serviceName = $this->prefix("policy_" . $name);
            $service = $builder->addDefinition($serviceName);
            $service->setType($policy);

            $policyRegistry->addSetup("addPolicy", [$service]);
        }

        $loader = $builder->addDefinition($this->prefix("loader"));
        $loader->setFactory(Loader::class, [$this->tempDirectory . "/security", $configFilePath, $acl]);

        $roles = $builder->addDefinition($this->prefix("roles"));
        $roles->setFactory(sprintf("@%s::loadRoles", Loader::class));
        $roles->addSetup("setup");

        $authorizator = $builder->addDefinition($this->prefix("authorizator"));
        $authorizator->setFactory(sprintf("@%s::loadAuthorizator", Loader::class));

        foreach ($acl as $name => $interface) {
            $module = $builder->addDefinition($this->prefix("aclModule_" . $name));
            $module->setType($interface);
            $module->setFactory(sprintf('@%s::loadAclModule', Loader::class), [$interface]);
        }
    }
}
