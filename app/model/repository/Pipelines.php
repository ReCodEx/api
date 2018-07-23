<?php

namespace App\Model\Repository;

use Doctrine\Common\Collections\Collection;
use Kdyby\Doctrine\EntityManager;
use Doctrine\ORM\Query;
use App\Model\Entity\Pipeline;
use App\Helpers\Pagination;
use App\Model\Helpers\PaginationDbHelper;
use App\Exceptions\InvalidArgumentException;


/**
 * @method Pipeline findOrThrow($id)
 * @method Pipeline get($id)
 */
class Pipelines extends BaseSoftDeleteRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Pipeline::class);
  }

  /**
   * Search pipelines based on parameters (filters and orderBy) in pagination config.
   * The result is not sliced as pipelines must be filtered by ACL first.
   * @param Pagination $pagination Pagination configuration object.
   * @return Pipeline[]
   */
  public function getPreparedForPagination(Pagination $pagination): array {
    $qb = $this->createQueryBuilder('p'); // takes care of softdelete cases

    // Apply common pagination stuff (search and ordering) and yield the results ...
    $paginationDbHelper = new PaginationDbHelper(
      [ // known order by columns
        'name' =>      [ 'p.name' ],
        'createdAt' => [ 'p.createdAt' ],
      ],
      [ 'name' ] // search column names
    );
    $paginationDbHelper->apply($qb, $pagination);
    return $paginationDbHelper->getResult($qb, $pagination);
  }
}
