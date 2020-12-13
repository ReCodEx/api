<?php

namespace App\Model\Repository;

use App\Exceptions\NotFoundException;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;

class BaseSoftDeleteRepository extends BaseRepository
{
    protected $softDeleteColumn;

    public function __construct(EntityManagerInterface $em, $entityType, $softDeleteColumn = "deletedAt")
    {
        parent::__construct($em, $entityType);
        $this->softDeleteColumn = $softDeleteColumn;
    }

    public function getTotalCount()
    {
        return $this->repository->count(
            [
                $this->softDeleteColumn => null
            ]
        );
    }

    public function findAll()
    {
        return $this->repository->findBy(
            [
                $this->softDeleteColumn => null
            ]
        );
    }

    public function findAllAndIReallyMeanAllOkay()
    {
        return $this->repository->findAll();
    }

    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null)
    {
        return $this->repository->findBy(
            array_merge(
                $criteria,
                [
                    $this->softDeleteColumn => null
                ]
            ),
            $orderBy,
            $limit,
            $offset
        );
    }

    public function findOneBy(array $criteria)
    {
        return $this->repository->findOneBy(
            array_merge(
                $criteria,
                [
                    $this->softDeleteColumn => null
                ]
            )
        );
    }

    public function findOneByEvenIfDeleted($params)
    {
        return $this->repository->findOneBy($params);
    }

    public function findOrThrow($id)
    {
        $entity = $this->findOneBy(['id' => $id]);
        if (!$entity) {
            throw new NotFoundException("Cannot find '$id'");
        }
        return $entity;
    }

    public function matching(Criteria $params)
    {
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
