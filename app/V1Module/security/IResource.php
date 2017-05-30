<?php
namespace App\Security;
use Nette;

interface IResource extends Nette\Security\IResource
{
  function getEntity();
}