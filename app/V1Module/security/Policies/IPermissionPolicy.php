<?php
namespace App\Security\Policies;

interface IPermissionPolicy {
  function getByID($id);
}