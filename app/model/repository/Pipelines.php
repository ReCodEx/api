<?php

namespace App\Model\Repository;

use App\Model\Entity\Pipeline;
use App\Helpers\Pagination;
use App\Model\Helpers\PaginationDbHelper;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseSoftDeleteRepository<Pipeline>
 */
class Pipelines extends BaseSoftDeleteRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, Pipeline::class);
    }

    public function findByName(string $name): array
    {
        return $this->findBy([ 'name' => $name ]);
    }

    /**
     * Search pipelines based on parameters (filters and orderBy) in pagination config.
     * The result is not sliced as pipelines must be filtered by ACL first.
     * @param Pagination $pagination Pagination configuration object.
     * @return Pipeline[]
     */
    public function getPreparedForPagination(Pagination $pagination): array
    {
        $qb = $this->createQueryBuilder('p'); // takes care of softdelete cases

        // Only pipelines of given author ...
        if ($pagination->hasFilter("authorId")) {
            $authorId = $pagination->getFilter("authorId");
            $qb->andWhere($qb->expr()->eq("p.author", $qb->expr()->literal($authorId)));
        }

        // Only pipelines attached to given exercise ...
        if ($pagination->hasFilter("exerciseId")) {
            $exerciseId = $pagination->getFilter("exerciseId");
            $qb->andWhere(":exerciseId MEMBER OF p.exercises")->setParameter('exerciseId', $exerciseId);
        }

        // Apply common pagination stuff (search and ordering) and yield the results ...
        $paginationDbHelper = new PaginationDbHelper(
            [ // known order by columns
                'name' => ['p.name'],
                'createdAt' => ['p.createdAt'],
            ],
            ['name'] // search column names
        );
        $paginationDbHelper->apply($qb, $pagination);
        return $paginationDbHelper->getResult($qb, $pagination);
    }

    /**
     * Retrieve all pipelines which are associated with given runtime environment.
     */
    public function getRuntimeEnvironmentPipelines(string $runtimeId): array
    {
        $qb = $this->createQueryBuilder('p'); // takes care of softdelete cases
        $qb->andWhere(":rteId MEMBER OF p.runtimeEnvironments")->setParameter('rteId', $runtimeId);
        return $qb->getQuery()->getResult();
    }
}
