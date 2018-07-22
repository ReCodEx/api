<?php

namespace DoctrineExtensions\Query;

use Doctrine\ORM\Query\SqlWalker;


/**
 * Custom extension of SQL walker responsible for generating SQL from AST.
 * It injects custom collation in ORDER BY clause for every column.
 */
class OrderByCollationInjectionMysqlWalker extends SqlWalker
{
  /**
   * Name of the hint, which holds the actual collation.
   */
  const HINT_COLLATION = 'orderByCollationInjectionMysqlWalker.collation';

  public function walkOrderByItem($orderByItem)
  {
    $sql = parent::walkOrderByItem($orderByItem);
    $collation = $this->getQuery()->getHint(self::HINT_COLLATION);

    $tokens = explode(' ', $sql);
    if ($collation && count($tokens) > 1) {
      // 'colname ASC|DESC' => 'colname COLLATE colation ASC|DESC'
      $direction = array_pop($tokens);
      array_push($tokens, 'COLLATE', $collation, $direction);
    }
    return implode(' ', $tokens);
  }
}
