<?php

namespace App\Security\ACL;

use App\Model\Entity\Instance;
use App\Model\Entity\Licence;

interface IInstancePermissions
{
    public function canViewAll(): bool;

    public function canViewDetail(Instance $instance): bool;

    public function canViewLicences(Instance $instance): bool;

    public function canAddLicence(Instance $instance): bool;

    public function canUpdateLicence(Licence $licence): bool;

    public function canRemoveLicence(Licence $licence): bool;

    public function canAdd(): bool;

    public function canUpdate(Instance $instance): bool;

    public function canRemove(Instance $instance): bool;
}
