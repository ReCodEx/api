<?php
namespace App\Model\Repository;
use App\Exceptions\NotFoundException;
use Doctrine\Common\Collections\Criteria;
use Kdyby\Doctrine\EntityManager;

class BaseSoftDeleteRepository extends BaseRepository {
  protected $softDeleteColumn;

  public function __construct(EntityManager $em, $entityType, $softDeleteColumn = "deletedAt")
  {
    parent::__construct($em, $entityType);
    $this->softDeleteColumn = $softDeleteColumn;
  }

  public function findAll() {
    return $this->repository->findBy([
      $this->softDeleteColumn => null
    ]);
  }

  public function findAllAndIReallyMeanAllOkay() {
    return $this->repository->findAll();
  }

  public function findBy($params, $orderBy = []) {
    return $this->repository->findBy(array_merge($params, [
      $this->softDeleteColumn => null
    ]), $orderBy);
  }

  public function findOneBy($params) {
    return $this->repository->findOneBy(array_merge($params, [
      $this->softDeleteColumn => null
    ]));
  }

  public function findOrThrow($id) {
    $entity = $this->findOneBy(['id' => $id]);
    if (!$entity) {
      throw new NotFoundException("Cannot find '$id'");
    }
    return $entity;
  }

  public function matching(Criteria $params) {
    $params->andWhere(Criteria::expr()->isNull($this->softDeleteColumn));
    return $this->repository->matching($params);
  }

  public function createQueryBuilder(string $alias, string $indexBy = null)
  {
    $qb = $this->repository->createQueryBuilder($alias, $indexBy);
    $softDeleteColumn = $alias ? "$alias.$this->softDeleteColumn" : $this->softDeleteColumn;
    $qb->andWhere($qb->expr()->isNull($softDeleteColumn));
    return $qb;
  }
}
