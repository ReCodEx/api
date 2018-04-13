<?php

namespace App\Helpers;

use Nette\Utils\Strings;


/**
 * Pagination helper structure.
 */
class Pagination {

  /**
   * @var int
   */
  private $offset;

  /**
   * @var int|null
   */
  private $limit;

  /**
   * @var bool
   */
  private $order;

  /**
   * @var string|null
   */
  private $orderBy;

  /**
   * @var string|null
   */
  private $originalOrderBy;

  /**
   * Pagination constructor.
   * @param int $offset
   * @param int|null $limit
   * @param string|null $orderBy
   */
  public function __construct(int $offset = 0, int $limit = null, string $orderBy = null) {
    $this->offset = $offset < 0 ? 0 : $offset;
    $this->limit = $limit < 0 ? null : $limit;
    $this->originalOrderBy = $orderBy;
    $this->order = !Strings::startsWith($orderBy, "!");
    $this->orderBy = $this->order ? $orderBy : Strings::substring($orderBy, 1);
  }

  /**
   * @return int
   */
  public function getOffset(): int {
    return $this->offset;
  }

  /**
   * @return int|null
   */
  public function getLimit(): ?int {
    return $this->limit;
  }

  /**
   * True if orderBy parameter was not negated.
   * @return bool
   */
  public function getOrder(): bool {
    return $this->order;
  }

  /**
   * Without leading negation if provided.
   * @return null|string
   */
  public function getOrderBy(): ?string {
    return $this->orderBy;
  }

  public function getOriginalOrderBy(): ?string {
    return $this->originalOrderBy;
  }

}
