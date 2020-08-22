<?php

namespace App\Model\Repository;

use App\Exceptions\NotFoundException;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\Criteria;
use Kdyby\Doctrine\EntityManager;
use Nette;
use DateTime;

class BaseRepository
{
    use Nette\SmartObject;

    protected $em;
    protected $repository;

    public function __construct(EntityManager $em, $entityType)
    {
        $this->em = $em;
        $this->repository = $em->getRepository($entityType);
    }

    public function get($id)
    {
        return $this->repository->find($id);
    }

    public function getTotalCount()
    {
        return $this->repository->count([]);
    }

    public function findAll()
    {
        return $this->repository->findAll();
    }

    public function findBy($params, $orderBy = [])
    {
        return $this->repository->findBy($params, $orderBy);
    }

    public function findOneBy($params)
    {
        return $this->repository->findOneBy($params);
    }

    public function findByIds(array $ids)
    {
        return $this->findBy(["id" => $ids]);
    }

    /**
     * Find an entity by id and throw an exception if no such entity exists
     * @param $id
     * @return mixed
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
     */
    protected function findByDateTimeColumn(?DateTime $since, ?DateTime $until, $column = 'createdAt')
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
     */
    public function findByCreatedAt(?DateTime $since, ?DateTime $until)
    {
        return $this->findByDateTimeColumn($since, $until);
    }

    public function persist($entity, $autoFlush = true)
    {
        $this->em->persist($entity);
        if ($autoFlush === true) {
            $this->flush();
        }
    }

    public function remove($entity, $autoFlush = true)
    {
        $this->em->remove($entity);
        if ($autoFlush === true) {
            $this->flush();
        }
    }

    public function flush()
    {
        $this->em->flush();
    }

    public function refresh($entity)
    {
        $this->em->refresh($entity);
    }

    public function matching(Criteria $params)
    {
        return $this->repository->matching($params);
    }

    protected function createQueryBuilder(string $alias, string $indexBy = null)
    {
        return $this->repository->createQueryBuilder($alias, $indexBy);
    }

    /**
     * Internal simple search of repository based on given string.
     * @param array $columns
     * @param string|null $search
     * @return array
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
     * @return array
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

    protected function searchHelper(?string $search, $searchFunction)
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

    public function beginTransaction()
    {
        $this->em->getConnection()->beginTransaction();
    }

    public function commit()
    {
        $this->em->getConnection()->commit();
    }

    public function rollBack()
    {
        $this->em->getConnection()->rollBack();
    }
}
