<?php

namespace App\Model\Helpers;

use App\Helpers\Pagination;
use Doctrine\ORM\QueryBuilder;
use App\Exceptions\InvalidApiArgumentException;
use Doctrine\ORM\Query;
use DoctrineExtensions\Query\OrderByCollationInjectionMysqlWalker;

/**
 * Helper class that adds common features (search and order by) to query builder
 * from given Pagination descriptor.
 */
class PaginationDbHelper
{
    /**
     * Translation table from locale to MySQL collation specification
     */
    private static $knownCollations = [
        'cs' => 'utf8mb4_czech_ci'
    ];

    /**
     * Non-string columns may not have collation in order by clause.
     * This is actually a hack, since detecting column type is rather complicated.
     * Column names on this list are passed on to the collation injector,
     * so they are avoided in the injection process.
     */
    private static $forbiddenColumns = ['created_at'];


    /**
     * @var array|null
     */
    private $searchCols = null;

    /**
     * @var string|null
     */
    private $localizedTextsClass = null;

    /**
     * @var array|null
     */
    private $orderByColumns = null;

    /**
     * Internal function that appends andWhere clause to query builder handling
     * full text search of one search token.
     */
    private function addSearchCondition(QueryBuilder $qb, string $searchToken, string $alias)
    {
        // Sanitize search token ...
        $searchToken = trim($searchToken);
        if (!$searchToken) {
            return;
        }
        $searchToken = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $searchToken);

        // Build OR expression with LIKE matching subexpressions...
        $expr = $qb->expr()->orX();
        foreach ($this->searchCols as $column) {
            if (strpos($column, '.') === false) {
                $column = $this->localizedTextsClass ? "loc.$column" : "$alias.$column";
            }
            $expr->add($qb->expr()->like($column, $qb->expr()->literal('%' . $searchToken . '%')));
        }

        if ($this->localizedTextsClass) {
            // We need to use external entity which holds localized texts.
            $sub = $qb->getEntityManager()->createQueryBuilder()->select("loc")->from(
                $this->localizedTextsClass,
                "loc"
            );
            $sub->andWhere($sub->expr()->isMemberOf("loc", "$alias.localizedTexts"))->andWhere($expr);
            $qb->andWhere($qb->expr()->exists($sub->getDQL()));
        } else {
            $qb->andWhere($expr);
        }
    }

    /**
     * Create and initialize the helper.
     * @param array $orderByColumns Known order by names (sent from UI), each holding a list
     *                              of corresponding order by DB columns.
     * @param array $searchCols List of columns which are tested by full text search filter.
     * @param string|null $localizedTextsClass Name of an entity class which is used for localization texts.
     *                                         If null, no localization is expected.
     */
    public function __construct(array $orderByColumns, array $searchCols, ?string $localizedTextsClass = null)
    {
        $this->orderByColumns = $orderByColumns;
        $this->searchCols = $searchCols;
        $this->localizedTextsClass = $localizedTextsClass;
    }

    /**
     * Apply only the search filter on a query builder.
     * @param QueryBuilder $qb Query builder being augmented.
     * @param string $search Search query string.
     * @param string|null $alias Alias of the main table use in the query builder. If null, alias is auto-detected.
     */
    public function applySearchFilter(QueryBuilder $qb, string $search, ?string $alias = null)
    {
        // Make sure we know the alias of the main table.
        if (!$alias) {
            $aliases = $qb->getRootAliases();
            $alias = reset($aliases);
        }

        $tokens = preg_split('/\s+/', $search, PREG_SPLIT_NO_EMPTY);
        foreach ($tokens as $token) {
            $this->addSearchCondition($qb, $token, $alias);
        }
    }

    /**
     * Apply the helper on a query builder (add common clauses) using pagination metadata.
     * @param QueryBuilder $qb Query builder being augmented.
     * @param Pagination $pagination Pagination object which holds the filter and order by parameters.
     * @param string|null $alias Alias of the main table use in the query builder. If null, alias is auto-detected.
     */
    public function apply(QueryBuilder $qb, Pagination $pagination, ?string $alias = null)
    {
        // Make sure we know the alias of the main table.
        if (!$alias) {
            $aliases = $qb->getRootAliases();
            $alias = reset($aliases);
        }

        // Add search conditions ...
        if ($this->searchCols && $pagination->hasFilter("search")) {
            $search = trim($pagination->getFilter("search"));
            if (!$search) {
                throw new InvalidApiArgumentException('filter', "search query value is empty");
            }
            $this->applySearchFilter($qb, $search, $alias);
        }

        // Set final ordering ...
        if (
            $this->orderByColumns && $pagination->getOrderBy()
            && !empty($this->orderByColumns[$pagination->getOrderBy()])
        ) {
            foreach ($this->orderByColumns[$pagination->getOrderBy()] as $orderBy) {
                $qb->addOrderBy($orderBy, $pagination->isOrderAscending() ? 'ASC' : 'DESC');
            }
        }

        return $qb; // support chaining
    }

    /**
     * Apply collation patches on the query yielded from the builder and fetch the results.
     * @param QueryBuilder $qb Query builder holding the final query.
     * @param Pagination $pagination Pagination object which holds the filter and order by parameters.
     * @return array
     */
    public function getResult(QueryBuilder $qb, Pagination $pagination)
    {
        // Augment the collation if necessary ...
        $query = $qb->getQuery();
        $locale = $pagination->getLocale();
        if (
            $locale && !empty(self::$knownCollations[$locale]) && $pagination->getOrderBy()
        ) { // collation correction based on given locale
            $query->setHint(
                Query::HINT_CUSTOM_OUTPUT_WALKER,
                'DoctrineExtensions\Query\OrderByCollationInjectionMysqlWalker'
            );
            $query->setHint(OrderByCollationInjectionMysqlWalker::HINT_COLLATION, self::$knownCollations[$locale]);
            $query->setHint(
                OrderByCollationInjectionMysqlWalker::HINT_COLLATION_FORBIDDEN_COLUMNS,
                self::$forbiddenColumns
            );
        }

        return $query->getResult();
    }
}
