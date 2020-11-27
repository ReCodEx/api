<?php

namespace App\Helpers\GroupBindings;

use Nette;
use Nette\DI\CompilerExtension;
use Nette\DI\Definitions\ServiceDefinition;
use Nette\DI\Definitions\Statement;

/**
 * Looks up all IGroupBindingProvider implementations and registers them in a GroupBindingAccessor
 */
class GroupBindingsExtension extends CompilerExtension
{
    public function loadConfiguration()
    {
        $accessor = $this->getContainerBuilder()->addDefinition($this->prefix("groupBindingsAccessor"));
        $accessor->factory = new Statement(GroupBindingAccessor::class);
    }

    public function beforeCompile()
    {
        $builder = $this->getContainerBuilder();
        /** @var ServiceDefinition $accessor */
        $accessor = $builder->getDefinition($this->prefix("groupBindingsAccessor"));
        foreach (array_keys($builder->findByType(IGroupBindingProvider::class)) as $name) {
            $accessor->addSetup("addProvider", ["@" . $name]);
        }
    }
}
