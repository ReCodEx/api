<?php

namespace App\Model\Repository;

use Nette;
use DateTime;
use Kdyby\Doctrine\EntityManager;

use App\Model\Entity\Permission;

class BaseRepository extends Nette\Object {

  protected $em;
  protected $repository;

  public function __construct(EntityManager $em, $entityType) {
    $this->em = $em;
    $this->repository = $em->getRepository($entityType);
  }

  public function get($id) {
    return $this->repository->findOneById($id);
  }

  public function findAll() {
    return $this->repository->findAll();
  }

  public function findOneBy($params) {
    return $this->repository->findOneBy($params);
  }

  public function findOrThrow($id) {
    $entity = $this->get($id);
    if (!$entity) {
      throw new NotFoundException("Cannot find '$id'");
    }
    return $entity;
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
