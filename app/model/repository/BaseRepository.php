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

  public function persist($entity) {
    $this->em->persist($entity);
  }

  public function flush() {
    $this->em->flush();
  }
}
