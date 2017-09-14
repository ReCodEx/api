<?php
namespace App\Security\ACL;


use App\Model\Entity\Exercise;
use App\Model\Entity\Pipeline;

interface IPipelinePermissions {
  function canViewAll(): bool;
  function canViewDetail(Pipeline $pipeline): bool;
  function canUpdate(Pipeline $pipeline): bool;
  function canCreate(): bool;
  function canRemove(Pipeline $pipeline): bool;
  function canFork(Pipeline $pipeline): bool;
}
