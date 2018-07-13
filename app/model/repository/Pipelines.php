<?php

namespace App\Model\Repository;

use Doctrine\Common\Collections\Collection;
use Kdyby\Doctrine\EntityManager;
use Doctrine\ORM\Query;
use DoctrineExtensions\Query\OrderByCollationInjectionMysqlWalker;
use App\Model\Entity\Pipeline;
use App\Helpers\Pagination;
use App\Exceptions\InvalidArgumentException;


/**
 * @method Pipeline findOrThrow($id)
 * @method Pipeline get($id)
 */
class Pipelines extends BaseSoftDeleteRepository {

  public function __construct(EntityManager $em) {
    parent::__construct($em, Pipeline::class);
  }

  // Known order by commands and their translation to Doctrine column names.
  private static $knownOrderBy = [
    'name' =>      [ 'p.name' ],
    'createdAt' => [ 'p.createdAt' ],
  ];


  /**
   * Search pipelines based on parameters (filters and orderBy) in pagination config.
   * The result is not sliced as pipelines must be filtered by ACL first.
   * @param Pagination $pagination Pagination configuration object.
   * @return Pipeline[]
   */
  public function getPreparedForPagination(Pagination $pagination): array {
    $qb = $this->createQueryBuilder('p'); // takes care of softdelete cases

    // Set filters ...
    if ($pagination->hasFilter("search")) {
      $search = trim($pagination->getFilter("search"));
      if (!$search) {
        throw new InvalidArgumentException("filter", "search query value is empty");
      }

      $qb->andWhere($qb->expr()->like("p.name", $qb->expr()->literal('%' . $search . '%')));
    }

    // Add order by
    if ($pagination->getOrderBy() && !empty(self::$knownOrderBy[$pagination->getOrderBy()])) {
      foreach (self::$knownOrderBy[$pagination->getOrderBy()] as $orderBy) {
        $qb->addOrderBy($orderBy, $pagination->isOrderAscending() ? 'ASC' : 'DESC');
      }
    }

    $query = $qb->getQuery();
    $collation = $pagination->getLocaleCollation();
    if ($collation && $pagination->getOrderBy()) { // collation correction based on given locale
      $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, 'DoctrineExtensions\Query\OrderByCollationInjectionMysqlWalker');
      $query->setHint(OrderByCollationInjectionMysqlWalker::HINT_COLLATION, $collation);
    }
    return $query->getResult();
  }

}
