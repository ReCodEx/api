<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\UploadedFile;

class UploadedFiles extends Nette\Object {

  private $em;
  private $uploadedFiles;

  public function __construct(EntityManager $em) {
    $this->em = $em;
    $this->uploadedFiles = $em->getRepository('App\Model\Entity\UploadedFile');
  }

  public function findAll() {
    return $this->uploadedFiles->findAll();
  }

  public function get($id) {
    return $this->uploadedFiles->findOneById($id);
  }

  public function persist(UploadedFile $uploadedFile) {
    $this->em->persist($uploadedFile);
  }

  public function flush() {
    $this->em->flush();
  }
}
