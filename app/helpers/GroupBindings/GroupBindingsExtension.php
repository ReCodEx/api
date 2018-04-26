<?php
namespace App\Helpers\GroupBindings;
use Nette;

/**
 * Looks up all IGroupBindingProvider implementations and registers them in a GroupBindingAccessor
 */
class GroupBindingsExtension extends Nette\DI\CompilerExtension {
  public function loadConfiguration() {
    $accessor = $this->getContainerBuilder()->addDefinition($this->prefix("groupBindingsAccessor"));
    $accessor->factory = new Nette\DI\Statement(GroupBindingAccessor::class);
  }

  public function beforeCompile() {
    $builder = $this->getContainerBuilder();
    $accessor = $builder->getDefinition($this->prefix("groupBindingsAccessor"));
    foreach (array_keys($builder->findByType(IGroupBindingProvider::class)) as $name) {
      $accessor->addSetup("addProvider", ["@" . $name]);
    }
  }
}