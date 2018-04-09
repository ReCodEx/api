<?php

namespace App\Helpers;


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
   * Pagination constructor.
   * @param int $offset
   * @param int|null $limit
   */
  public function __construct(int $offset = 0, int $limit = null) {
    $this->offset = $offset < 0 ? 0 : $offset;
    $this->limit = $limit < 0 ? null : $limit;
  }

  /**
   * @return int
   */
  public function getOffset(): int {
    return $this->offset;
  }

  /**
   * @param int $offset
   */
  public function setOffset(int $offset): void {
    $this->offset = $offset;
  }

  /**
   * @return int|null
   */
  public function getLimit(): ?int {
    return $this->limit;
  }

  /**
   * @param int|null $limit
   */
  public function setLimit(?int $limit): void {
    $this->limit = $limit;
  }

}
