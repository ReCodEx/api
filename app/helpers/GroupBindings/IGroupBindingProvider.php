<?php

namespace App\Helpers\GroupBindings;

use App\Model\Entity\Group;

interface IGroupBindingProvider
{
    /**
     * @return string a unique identifier of the type of the binding
     */
    public function getGroupBindingIdentifier(): string;

    /**
     * @param Group $group
     * @return array all entities bound to the group (they must have __toString() implemented)
     */
    public function findGroupBindings(Group $group): array;
}
