<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\Permission;

class BaseRepository extends Nette\Object {

  protected $em;
  protected $repository;
  protected $entityName;

  public function __construct(EntityManager $em) {
    $this->em = $em;
    $this->repository = $em->getRepository("App\\Model\\Entity\\" . $this->entityName);
  }

  public function findAll() {
    return $this->repository->findAll();
  }

  public function get($id) {
    return $this->repository->findOneById($id);
  }

  public function persist($entity, $autoFlush = TRUE) {
    $this->em->persist($entity);
    if ($autoFlush === TRUE) {
      $this->flush();
    }
  }

  public function remove($entity, $autoFlush = TRUE) {
    $this->em->remove($entity);
    if ($autoFlush === TRUE) {
      $this->flush();
    }
  }

  public function flush() {
    $this->em->flush();
  }
}
