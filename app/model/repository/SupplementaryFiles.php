<?php

namespace App\Model\Repository;

use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\SupplementaryFile;

class SupplementaryFiles extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, SupplementaryFile::CLASS);
  }

}
