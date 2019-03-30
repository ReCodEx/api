<?php

namespace App\Model\View;

use App\Helpers\Localizations;
use App\Model\Entity\Instance;
use App\Model\Entity\LocalizedGroup;


/**
 * Factory for instance views which somehow do not fit into json serialization
 * of entities.
 */
class InstanceViewFactory {

  /** @var GroupViewFactory */
  private $groupViewFactory;

  public function __construct(GroupViewFactory $groupViewFactory) {
    $this->groupViewFactory = $groupViewFactory;
  }


  /**
   * Get as much instance detail info as your permissions grants you.
   * @param Instance $instance
   * @return array
   */
  public function getInstance(Instance $instance): array {
    /** @var LocalizedGroup $localizedRootGroup */
    $localizedRootGroup = Localizations::getPrimaryLocalization($instance->getRootGroup()->getLocalizedTexts());

    return [
      "id" => $instance->getId(),
      "name" => $localizedRootGroup ? $localizedRootGroup->getName() : "", // BC
      "description" => $localizedRootGroup ? $localizedRootGroup->getDescription() : "", // BC
      "hasValidLicence" => $instance->hasValidLicence(),
      "isOpen" => $instance->isOpen(),
      "isAllowed" => $instance->isAllowed(),
      "createdAt" => $instance->getCreatedAt()->getTimestamp(),
      "updatedAt" => $instance->getUpdatedAt()->getTimestamp(),
      "deletedAt" => $instance->getDeletedAt() ? $instance->getDeletedAt()->getTimestamp() : null,
      "adminId" => $instance->getAdmin() ? $instance->getAdmin()->getId() : null,
      "rootGroup" => $this->groupViewFactory->getGroup($instance->getRootGroup()),
      "rootGroupId" => $instance->getRootGroup()->getId()
    ];
  }

  /**
   * Get instance data.
   * @param Instance[] $instances
   * @return array
   */
  public function getInstances(array $instances): array {
    $instances = array_values($instances);
    return array_map(function (Instance $instance) {
      return $this->getInstance($instance);
    }, $instances);
  }

}
