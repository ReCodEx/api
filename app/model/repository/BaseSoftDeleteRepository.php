<?php

namespace App\Model\Repository;

use App\Exceptions\NotFoundException;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;

/**
 * @template T
 * @extends BaseRepository<T>
 */
class BaseSoftDeleteRepository extends BaseRepository
{
    protected $softDeleteColumn;

    public function __construct(EntityManagerInterface $em, $entityType, $softDeleteColumn = "deletedAt")
    {
        parent::__construct($em, $entityType);
        $this->softDeleteColumn = $softDeleteColumn;
    }

    /**
     * @param mixed $id
     * @return T|null
     */
    public function get($id)
    {
        return $this->findOneBy(['id' => $id]);
    }

    public function getTotalCount(): int
    {
        return $this->repository->count(
            [
                $this->softDeleteColumn => null
            ]
        );
    }

    /**
     * @return T[]
     */
    public function findAll(): array
    {
        return $this->repository->findBy(
            [
                $this->softDeleteColumn => null
            ]
        );
    }

    /**
     * @return T[]
     */
    public function findAllAndIReallyMeanAllOkay(): array
    {
        return $this->repository->findAll();
    }

    /**
     * @param array $criteria
     * @param array|null $orderBy
     * @param null $limit
     * @param null $offset
     * @return T[]
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null): array
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

    /**
     * @param array $criteria
     * @return T|null
     */
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

    /**
     * @param array $params
     * @return T|null
     */
    public function findOneByEvenIfDeleted(array $params)
    {
        return $this->repository->findOneBy($params);
    }

    /**
     * @param mixed $id
     * @return T
     * @throws NotFoundException
     */
    public function findOrThrow($id)
    {
        $entity = $this->findOneBy(['id' => $id]);
        if (!$entity) {
            throw new NotFoundException("Cannot find '$id'");
        }
        return $entity;
    }

    /**
     * @param Criteria $params
     * @return AbstractLazyCollection<int, object>
     */
    public function matching(Criteria $params): AbstractLazyCollection
    {
        $params->andWhere(Criteria::expr()->isNull($this->softDeleteColumn));
        return $this->repository->matching($params);
    }

    /**
     * @param string $alias
     * @param string|null $indexBy
     * @return QueryBuilder
     */
    public function createQueryBuilder(string $alias, string $indexBy = null): QueryBuilder
    {
        $qb = $this->repository->createQueryBuilder($alias, $indexBy);
        $softDeleteColumn = $alias ? "$alias.$this->softDeleteColumn" : $this->softDeleteColumn;
        $qb->andWhere($qb->expr()->isNull($softDeleteColumn));
        return $qb;
    }
}
