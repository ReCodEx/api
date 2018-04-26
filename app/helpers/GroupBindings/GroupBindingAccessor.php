<?php
namespace App\Helpers\GroupBindings;

use App\Model\Entity\Group;

/**
 * Provides access to instances of IGroupBindingProvider
 */
class GroupBindingAccessor {
  /** @var IGroupBindingProvider[] */
  private $providers = [];

  public function addProvider(IGroupBindingProvider $provider) {
    $this->providers[] = $provider;
  }

  public function getBindingsForGroup(Group $group) {
    $result = [];
    foreach ($this->providers as $provider) {
      $result[$provider->getGroupBindingIdentifier()] = $provider->findGroupBindings($group);
    }
    return $result;
  }
}