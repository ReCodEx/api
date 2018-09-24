<?php
namespace App\Security\ACL;


use App\Model\Entity\Instance;
use App\Model\Entity\Licence;

interface IInstancePermissions {
  function canViewAll(): bool;
  function canViewDetail(Instance $instance): bool;
  function canViewLicences(Instance $instance): bool;
  function canAddLicence(Instance $instance): bool;
  function canUpdateLicence(Licence $licence): bool;
  function canRemoveLicence(Licence $licence): bool;
  function canAdd(): bool;
  function canUpdate(Instance $instance): bool;
  function canRemove(Instance $instance): bool;
}
