<?php

namespace App\Model\Repository;

use App\Exceptions\NotFoundException;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Nette;
use Kdyby\Doctrine\EntityManager;


class BaseRepository extends Nette\Object {

  protected $em;
  protected $repository;

  public function __construct(EntityManager $em, $entityType) {
    $this->em = $em;
    $this->repository = $em->getRepository($entityType);
  }

  public function get($id) {
    return $this->repository->find($id);
  }

  public function findAll() {
    return $this->repository->findAll();
  }

  public function findBy($params, $orderBy = []) {
    return $this->repository->findBy($params, $orderBy);
  }

  public function findOneBy($params) {
    return $this->repository->findOneBy($params);
  }

  /**
   * Find an entity by id and throw an exception if no such entity exists
   * @param $id
   * @return mixed
   * @throws NotFoundException
   */
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

  public function matching(Criteria $params) {
    return $this->repository->matching($params);
  }


  /**
   * Internal simple search of repository based on given string.
   * @param array $columns
   * @param string|null $search
   * @return Collection
   */
  protected function search(array $columns, string $search = null): Collection {
    $filter = Criteria::create();

    if ($search !== null && !empty($search)) {
      foreach ($columns as $column) {
        $filter->orWhere(Criteria::expr()->contains($column, $search));
      }
    }

    return $this->matching($filter);
  }

  /**
   * Search repository based on given search string within given columns.
   * @param array $columns
   * @param null|string $search
   * @return array
   */
  public function searchBy(array $columns, string $search = null): array {
    $filtered = $this->search($columns, $search);
    if ($filtered->count() > 0) {
      return $filtered->toArray();
    }

    // weaker filter - the strict one did not match anything
    $filtered = array();
    foreach (explode(" ", $search) as $part) {
      // skip empty parts
      $part = trim($part);
      if (empty($part)) {
        continue;
      }

      $weaker = $this->search($columns, $part);
      $filtered = array_merge($filtered, $weaker->toArray());
    }

    return $filtered;
  }

}
