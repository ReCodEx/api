<?php

namespace App\Model\Repository;

use App\Helpers\Pagination;
use App\Model\Helpers\PaginationDbHelper;
use Kdyby\Doctrine\EntityManager;
use Doctrine\ORM\Query;
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

  /**
   * Fetch users for pagination endpoint (filtered and sorted).
   * @param Pagination $pagination The object holding pagination metadata.
   * @param $totalCount Referenced variable, into which the total amount of items is returned.
   */
  public function getPaginated(Pagination $pagination, &$totalCount)
  {
    $qb = $this->createQueryBuilder('u'); // takes care of softdelete cases

    // Filter by instance ID ...
    if ($pagination->hasFilter("instanceId")) {
      $instanceId = trim($pagination->getFilter("instanceId"));
      $qb->andWhere(':instanceId MEMBER OF u.instances')->setParameter('instanceId', $instanceId);
    }

    // Filter by selected roles ...
    if ($pagination->hasFilter("roles")) {
      $roles = $pagination->getFilter("roles");
      if (!is_array($roles)) {
        $roles = [ $roles ];
      }
      $qb->andWhere($qb->expr()->in("u.role", $roles));
    }

    // Apply common pagination stuff (search and ordering) and yield the results ...
    $paginationDbHelper = new PaginationDbHelper(
      [ // known order by columns
        'name' =>      [ 'u.lastName', 'u.firstName' ],
        'email' =>     [ 'u.email' ],
        'createdAt' => [ 'u.createdAt' ],
      ],
      [ 'firstName', 'lastName' ] // search column names
    );
    $paginationDbHelper->apply($qb, $pagination);

    // Get total count of results ...
    $qb->select('count(u.id)');
    $totalCount = (int)$qb->getQuery()->getSingleScalarResult();
    $qb->select('DISTINCT u');

    // Set range for pagination result...
    $qb->setFirstResult($pagination->getOffset());
    if ($pagination->getLimit()) {
      $qb->setMaxResults($pagination->getLimit());
    }

    return $paginationDbHelper->getResult($qb, $pagination);
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
