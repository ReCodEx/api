<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\UploadedFile;

class UploadedFiles extends BaseRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, UploadedFile::CLASS);
  }

  public function findAllById($ids) {
    return $this->repository->findBy([ "id" => $ids ]);
  }

}
