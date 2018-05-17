<?php
namespace App\Security\ACL;


interface IEmailPermissions {
  function canSendToAll();
}
