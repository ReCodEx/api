<?php

namespace App\Model\Repository;

use App\Helpers\Pagination;
use Kdyby\Doctrine\EntityManager;
use Doctrine\ORM\Query;
use Doctrine\Common\Collections\Criteria;
use DoctrineExtensions\Query\OrderByCollationInjectionMysqlWalker;
use App\Model\Entity\User;
use App\Exceptions\InvalidArgumentException;

/**
 * @method User findOrThrow(string $id)
 */
class Users extends BaseSoftDeleteRepository {
  public function __construct(EntityManager $em) {
    parent::__construct($em, User::class);
  }

  public function getByEmail(string $email): ?User {
    return $this->findOneBy([ "email" => $email ]);
  }

  // Known order by commands and their translation to Doctrine criteria.
  private static $knownOrderBy = [
    'name' =>      [ 'u.lastName', 'u.firstName' ],
    'email' =>     [ 'u.email' ],
    'createdAt' => [ 'u.createdAt' ],
  ];

  private static function getOrderByCriteria($orderBy, $order)
  {
    if (!array_key_exists($orderBy, self::$knownOrderBy)) {
      return null;
    }
    if (!$order) {
      $res = [];
      foreach (self::$knownOrderBy[$orderBy] as $key => $unused) {
        $res[$key] = Criteria::DESC;
      }
    } else {
      return self::$knownOrderBy[$orderBy];
    }
  }

  /**
   * Fetch users for pagination endpoint (filtered and sorted).
   * @param Pagination $pagination The object holding pagination metadata.
   * @param $totalCount Referenced variable, into which the total amount of items is returned.
   */
  public function getPaginated(Pagination $pagination, &$totalCount)
  {
    $qb = $this->createQueryBuilder('u'); // takes care of softdelete cases

    // Set filters ...
    if ($pagination->hasFilter("search")) {
      $search = trim($pagination->getFilter("search"));
      if (!$search) {
        throw new InvalidArgumentException("filter", "search query value is empty");
      }

      $expr = $qb->expr()->orX();
      foreach (["u.firstName", "u.lastName"] as $column) {
        $expr->add($qb->expr()->like($column, $qb->expr()->literal('%' . $search . '%')));
      }
      $qb->andWhere($expr);
    }

    if ($pagination->hasFilter("instanceId")) {
      $instanceId = trim($pagination->getFilter("instanceId"));
      $qb->join('u.instances', 'i')->andWhere("i.id = :instanceId")->setParameter('instanceId', $instanceId);
    }

    if ($pagination->hasFilter("roles")) {
      $roles = $pagination->getFilter("roles");
      if (!is_array($roles)) {
        $roles = [ $roles ];
      }
      $qb->andWhere($qb->expr()->in("u.role", $roles));
    }

    // Get total count of results ...
    $qb->select('count(u.id)');
    $totalCount = (int)$qb->getQuery()->getSingleScalarResult();
    $qb->select('u');

    // Finalize for pagination
    if ($pagination->getOrderBy() && !empty(self::$knownOrderBy[$pagination->getOrderBy()])) {
      foreach (self::$knownOrderBy[$pagination->getOrderBy()] as $orderBy) {
        $qb->addOrderBy($orderBy, $pagination->getOrder() ? 'ASC' : 'DESC');
      }
    }

    // Set range for pagination result...
    $qb->setFirstResult($pagination->getOffset());
    if ($pagination->getLimit()) {
      $qb->setMaxResults($pagination->getLimit());
    }

    $query = $qb->getQuery();
    $collation = $pagination->getLocaleCollation();
    if ($collation && $pagination->getOrderBy()) { // collation correction based on given locale
      $query->setHint(Query::HINT_CUSTOM_OUTPUT_WALKER, 'DoctrineExtensions\Query\OrderByCollationInjectionMysqlWalker');
      $query->setHint(OrderByCollationInjectionMysqlWalker::HINT_COLLATION, $collation);
    }
    return $query->getResult();
  }


  /**
   * Search users firstnames and surnames based on given string.
   * @param string|null $search
   * @return User[]
   */
  public function searchByNames(?string $search): array {
    return $this->searchBy(["firstName", "lastName"], $search);
  }

  public function findByRoles(string ...$roles): array {
    return $this->findBy([ "role" => $roles ]);
  }

}
