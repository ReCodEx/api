<?php

namespace App\Model\View;

use App\Model\Entity\Instance;
use App\Model\Entity\LocalizedGroup;
use App\Model\Entity\User;
use App\Helpers\Localizations;
use App\Helpers\Extensions;

/**
 * Factory for instance views which somehow do not fit into json serialization
 * of entities.
 */
class InstanceViewFactory
{
    /** @var GroupViewFactory */
    private $groupViewFactory;

    /** @var Extensions */
    private $extensions;

    public function __construct(GroupViewFactory $groupViewFactory, Extensions $extensions)
    {
        $this->groupViewFactory = $groupViewFactory;
        $this->extensions = $extensions;
    }


    /**
     * Get as much instance detail info as your permissions grants you.
     * @param Instance $instance
     * @param User|null $loggedUser (to better target available extensions)
     * @return array
     */
    public function getInstance(Instance $instance, ?User $loggedUser = null): array
    {
        /** @var LocalizedGroup|null $localizedRootGroup */
        $localizedRootGroup = Localizations::getPrimaryLocalization($instance->getRootGroup()->getLocalizedTexts());
        $extensions = [];
        foreach ($this->extensions->getAccessibleExtensions($instance, $loggedUser) as $ext) {
            $extensions[$ext->getId()] = $ext->getCaption();
        }

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
            "rootGroupId" => $instance->getRootGroup()->getId(),
            "extensions" => $extensions,
        ];
    }

    /**
     * Get instance data.
     * @param Instance[] $instances
     * @param User|null $loggedUser (to better target available extensions)
     * @return array
     */
    public function getInstances(array $instances, ?User $loggedUser = null): array
    {
        $instances = array_values($instances);
        return array_map(
            function (Instance $instance) use ($loggedUser) {
                return $this->getInstance($instance, $loggedUser);
            },
            $instances
        );
    }
}
