<?php
namespace App\Model\Repository;
use App\Exceptions\NotFoundException;
use Doctrine\Common\Collections\Criteria;
use Kdyby\Doctrine\EntityManager;

class BaseSoftDeleteRepository extends BaseRepository {
  private $softDeleteColumn;

  public function __construct(EntityManager $em, $entityType, $softDeleteColumn = "deletedAt")
  {
    parent::__construct($em, $entityType);
    $this->softDeleteColumn = $softDeleteColumn;
  }

  public function findAll() {
    return $this->repository->findBy([
      $this->softDeleteColumn => NULL
    ]);
  }

  public function findBy($params) {
    return $this->repository->findBy(array_merge($params, [
      $this->softDeleteColumn => NULL
    ]));
  }

  public function findOneBy($params) {
    return $this->repository->findOneBy(array_merge($params, [
      $this->softDeleteColumn => NULL
    ]));
  }

  public function matching(Criteria $params) {
    $params->andWhere(Criteria::expr()->isNull($this->softDeleteColumn));
    return $this->repository->matching($params);
  }
}