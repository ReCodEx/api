<?php

namespace App\Model\Repository;

use App\Model\Entity\GroupExternalAttribute;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use InvalidArgumentException;

/**
 * @extends BaseRepository<GroupExternalAttribute>
 */
class GroupExternalAttributes extends BaseRepository
{
    public function __construct(EntityManagerInterface $em)
    {
        parent::__construct($em, GroupExternalAttribute::class);
    }

    /**
     * Helper function that constructs AND expression representing a filter clause for find query builder.
     * @param QueryBuilder $qb
     * @param int $counter used for generating unique parameter identifiers
     * @param array $clause represented as associative array
     * @param string $filterLocation used to identify the clause in exception messages
     * @throws InvalidArgumentException if the clause format is invalid
     */
    private static function createFilterClause(QueryBuilder $qb, int &$counter, array $clause, string $filterLocation)
    {
        $expr = $qb->expr()->andX();
        $set = false;
        foreach (['group', 'service', 'key', 'value'] as $key) {
            if (array_key_exists($key, $clause) && $clause[$key] !== null) {
                if (!is_string($clause[$key])) {
                    throw new InvalidArgumentException("Clause parameter $filterLocation.$key must be a string.");
                }
                $paramName = $key . $counter++;
                $expr->add("ea.$key = :$paramName");
                $qb->setParameter($paramName, $clause[$key]);
                $set = true;
            }
        }

        if (!$set) {
            throw new InvalidArgumentException("Clause $filterLocation does not have any known keys.");
        }
        return $expr;
    }

    /**
     * Find all external attributes that match given filter (disjunction of clauses).
     * @param array $filter clause represented as and array of OR clauses where each clause is an object
     *                      (or assoc. array) with a subset of 'group', 'service', 'key', 'value' keys
     * @return GroupExternalAttribute[] attributes that match given filter
     * @throws InvalidArgumentException if the clause format is invalid
     */
    public function findByFilter(array $filter): array
    {
        if (!$filter) {
            throw new InvalidArgumentException("Argument filter is empty.");
        }

        $qb = $this->createQueryBuilder('ea')->join('ea.group', 'g');
        $qb->where('g.archivedAt IS NULL');

        $expr = $qb->expr()->orX();
        $counter = 0;
        foreach (array_values($filter) as $idx => $clause) {
            if (!is_object($clause) && !is_array($clause)) {
                throw new InvalidArgumentException("Invalid clause type in filter[$idx], object or array expected.");
            }
            $expr->add(self::createFIlterClause($qb, $counter, (array)$clause, "filter[$idx]"));
        }
        $qb->andWhere($expr);

        return $qb->getQuery()->getResult();
    }
}
