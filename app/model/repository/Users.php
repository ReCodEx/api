<?php

namespace App\Model\Repository;

use App\Helpers\Pagination;
use App\Model\Helpers\PaginationDbHelper;
use App\Model\Entity\User;
use DateTime;
use Doctrine\ORM\EntityManagerInterface;

/**
 * @extends BaseSoftDeleteRepository<User>
 */
class Users extends BaseSoftDeleteRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, User::class);
    }

    public function getByEmail(string $email): ?User
    {
        return $this->findOneBy(["email" => $email]);
    }

    /**
     * Fetch users for pagination endpoint (filtered and sorted).
     * @param Pagination $pagination The object holding pagination metadata.
     * @param int $totalCount Referenced variable, into which the total amount of items is returned.
     * @return User[]
     */
    public function getPaginated(Pagination $pagination, &$totalCount): array
    {
        $qb = $this->createQueryBuilder('u'); // takes care of soft delete cases

        // Filter by instance ID ...
        if ($pagination->hasFilter("instanceId")) {
            $instanceId = trim($pagination->getFilter("instanceId"));
            $qb->andWhere(':instanceId MEMBER OF u.instances')->setParameter('instanceId', $instanceId);
        }

        // Filter by selected roles ...
        if ($pagination->hasFilter("roles")) {
            $roles = $pagination->getFilter("roles");
            if (!is_array($roles)) {
                $roles = [$roles];
            }
            $qb->andWhere($qb->expr()->in("u.role", $roles));
        }

        // Apply common pagination stuff (search and ordering) and yield the results ...
        $paginationDbHelper = new PaginationDbHelper(
            [ // known order by columns
                'name' => ['u.lastName', 'u.firstName'],
                'email' => ['u.email'],
                'createdAt' => ['u.createdAt'],
            ],
            ['firstName', 'lastName'] // search column names
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
     * Search users first names and surnames based on given string.
     * @param string|null $search
     * @return User[]
     */
    public function searchByNames(?string $search): array
    {
        return $this->searchBy(["firstName", "lastName"], $search);
    }

    /**
     * @param string ...$roles
     * @return User[]
     */
    public function findByRoles(string ...$roles): array
    {
        return $this->findBy(["role" => $roles]);
    }

    /**
     * Find all users who have not authenticated to the system for some time.
     * @param DateTime|null $before Only users with last activity before given date
     *                              (i.e., not active after given date) are returned.
     * @param bool|null $allowed if not null, only users with particular isAllowed state are returned
     * @param string[] $roles only users of these roles are listed
     * @return User[]
     */
    public function findByLastAuthentication(?DateTime $before, ?bool $allowed = null, array $roles = []): array
    {
        $qb = $this->createQueryBuilder('u'); // takes care of soft delete cases
        if ($before) {
            $qb->andWhere(
                'u.createdAt <= :before AND (u.lastAuthenticationAt <= :before OR u.lastAuthenticationAt IS NULL)'
            )->setParameter('before', $before);
        }
        if ($allowed !== null) {
            $qb->andWhere('u.isAllowed = :allowed')->setParameter('allowed', $allowed);
        }
        if ($roles) {
            $qb->andWhere('u.role IN (:roles)')->setParameter('roles', $roles);
        }
        return $qb->getQuery()->getResult();
    }
}
