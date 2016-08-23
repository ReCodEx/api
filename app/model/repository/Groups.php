<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

class Groups extends BaseRepository {
  protected $entityName = "Group";

  public function findAllByInstance(Instance $instance) {
    
  }
}
