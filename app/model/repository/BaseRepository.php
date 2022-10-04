<?php

namespace App\Model\Repository;

use App\Exceptions\NotFoundException;
use DateTime;
use Doctrine\Common\Collections\AbstractLazyCollection;
use Doctrine\Common\Collections\Criteria;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\QueryBuilder;
use Nette;

/**
 * @template T
 */
class BaseRepository
{
    use Nette\SmartObject;

    /**
     * @var EntityManagerInterface
     */
    protected $em;

    /**
     * @var EntityRepository
     */
    protected $repository;

    public function __construct(EntityManagerInterface $em, $entityType)
    {
        $this->em = $em;
        /** @var EntityRepository $repository */
        $repository = $em->getRepository($entityType);
        $this->repository = $repository;
    }

    public function getEntityManagerForDebugging(): EntityManagerInterface
    {
        return $this->em;
    }

    /**
     * @param mixed $id
     * @return T|null
     */
    public function get($id)
    {
        return $this->repository->find($id);
    }

    public function getTotalCount(): int
    {
        return $this->repository->count([]);
    }

    /**
     * @return T[]
     */
    public function findAll(): array
    {
        return $this->repository->findAll();
    }

    /**
     * @return T[]
     */
    public function findBy(array $criteria, array $orderBy = null, $limit = null, $offset = null): array
    {
        return $this->repository->findBy($criteria, $orderBy, $limit, $offset);
    }

    /**
     * @param array $criteria
     * @return T|null
     */
    public function findOneBy(array $criteria)
    {
        return $this->repository->findOneBy($criteria);
    }

    /**
     * @param array $ids
     * @return T[]
     */
    public function findByIds(array $ids): array
    {
        return $this->findBy(["id" => $ids]);
    }

    /**
     * Find an entity by id and throw an exception if no such entity exists
     * @param mixed $id
     * @return T
     * @throws NotFoundException
     */
    public function findOrThrow($id)
    {
        $entity = $this->get($id);
        if (!$entity) {
            throw new NotFoundException("Cannot find '$id'");
        }
        return $entity;
    }


    /**
     * Find all entities which have selected datetime column in given time interval.
     * @param DateTime|null $since Only entities created after this date are returned.
     * @param DateTime|null $until Only entities created before this date are returned.
     * @param string $column Name of the column used for filtering.
     * @return T[]
     */
    protected function findByDateTimeColumn(?DateTime $since, ?DateTime $until, $column = 'createdAt'): array
    {
        $qb = $this->createQueryBuilder('e'); // takes care of softdelete cases
        if ($since) {
            $qb->andWhere("e.$column >= :since")->setParameter('since', $since);
        }
        if ($until) {
            $qb->andWhere("e.$column <= :until")->setParameter('until', $until);
        }
        return $qb->getQuery()->getResult();
    }

    /**
     * Find all entities created in given time interval.
     * @param DateTime|null $since Only entities created after this date are returned.
     * @param DateTime|null $until Only entities created before this date are returned.
     * @return T[]
     */
    public function findByCreatedAt(?DateTime $since, ?DateTime $until): array
    {
        return $this->findByDateTimeColumn($since, $until);
    }

    public function persist($entity, $autoFlush = true): void
    {
        $this->em->persist($entity);
        if ($autoFlush === true) {
            $this->flush();
        }
    }

    public function remove($entity, $autoFlush = true): void
    {
        $this->em->remove($entity);
        if ($autoFlush === true) {
            $this->flush();
        }
    }

    public function flush(): void
    {
        $this->em->flush();
    }

    public function refresh($entity): void
    {
        $this->em->refresh($entity);
    }

    /**
     * @param Criteria $params
     * @return AbstractLazyCollection<int, object>
     */
    public function matching(Criteria $params): AbstractLazyCollection
    {
        return $this->repository->matching($params);
    }

    /**
     * Create query builder for entity related to this repository
     * @param string $alias
     * @param string|null $indexBy
     * @return QueryBuilder
     */
    protected function createQueryBuilder(string $alias, string $indexBy = null): QueryBuilder
    {
        return $this->repository->createQueryBuilder($alias, $indexBy);
    }

    /**
     * Internal simple search of repository based on given string.
     * @param array $columns
     * @param string|null $search
     * @return T[]
     */
    protected function search(array $columns, string $search = null): array
    {
        $filter = Criteria::create();

        if ($search !== null && !empty($search)) {
            foreach ($columns as $column) {
                $filter->orWhere(Criteria::expr()->contains($column, $search));
            }
        }

        return $this->matching($filter)->toArray();
    }

    /**
     * Search repository based on given search string within given columns.
     * @param array $columns
     * @param null|string $search
     * @return T[]
     */
    protected function searchBy(array $columns, string $search = null): array
    {
        return $this->searchHelper(
            $search,
            function ($search) use ($columns) {
                return $this->search($columns, $search);
            }
        );
    }

    /**
     * @param string|null $search
     * @param callable $searchFunction
     * @return T[]
     */
    protected function searchHelper(?string $search, $searchFunction): array
    {
        /** @var array $filtered */
        $filtered = $searchFunction($search);

        if (count($filtered) > 0) {
            return $filtered;
        }

        foreach (explode(" ", $search) as $part) {
            // skip empty parts
            $part = trim($part);
            if (empty($part)) {
                continue;
            }

            /** @var array $weaker */
            $weaker = $searchFunction($part);
            $filtered = array_merge($filtered, $weaker);
        }

        return array_unique($filtered);
    }


    /*
     * Repositories provide access to low-level transaction control.
     */

    public function beginTransaction(): void
    {
        $this->em->getConnection()->beginTransaction();
    }

    public function commit(): void
    {
        $this->em->getConnection()->commit();
    }

    public function rollBack(): void
    {
        $this->em->getConnection()->rollBack();
    }

    /*
     * Static helper functions
     */

    /**
     * Convert collection of entities into an associative array indexed by IDs.
     * @param iterable $entities collection of entities (entity must have getId() method)
     * @param mixed $value common value for the records in the output array;
     *                     if null, the original entities are used as values
     * @return array indexed by string IDs
     */
    public static function createIdIndex(iterable $entities, $value = null): array
    {
        $res = [];
        foreach ($entities as $entity) {
            $res[$entity->getId()] = $value === null ? $entity : $value;
        }
        return $res;
    }
}
