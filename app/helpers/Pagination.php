<?php

namespace App\Helpers;

use Nette\Utils\Strings;
use App\Exceptions\InvalidArgumentException;
use App\Exceptions\InternalServerException;


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
   * @var bool True if the items should be in ascending order, false means descending order.
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
   * @var array
   */
  private $filters;

  /**
   * @var string|null
   */
  private $locale;


  /**
   * Pagination constructor.
   * @param int $offset Index of the first item to be returned.
   * @param int|null $limit Total amount of items being returned.
   * @param string|null $locale Selected locale ('en', 'cs, ...) which could affect ordering and possibly some filters.
   * @param string|null $orderBy String representing the ordering (typically a column name).
   *                             If the ordering identifier is prefixed with '!', DESC ordering is used instead of ASC.
   * @param array $filters Array of filters and their values. Filters are endpoint-specific.
   * @param array|null $knownFilters Array of known filter names. If present, unknown filters will trigger exception.
   * @throws InvalidArgumentException
   */
  public function __construct(int $offset = 0, int $limit = null, string $locale = null, string $orderBy = null, array $filters = [], array $knownFilters = null) {
    $this->offset = $offset < 0 ? 0 : $offset;
    $this->limit = $limit < 0 ? null : $limit;
    $this->originalOrderBy = $orderBy;
    $this->locale = $locale;
    $this->order = !Strings::startsWith($orderBy, "!");
    $this->orderBy = $this->order ? $orderBy : Strings::substring($orderBy, 1);

    if ($knownFilters !== null) {
      $knownFilters = array_flip($knownFilters);
      foreach ($filters as $name => $unused) {
        if (!array_key_exists($name, $knownFilters)) {
          throw new InvalidArgumentException("filter", "unknown filter '$name'");
        }
      }
    }
    $this->filters = $filters;
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
  public function isOrderAscending(): bool {
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

  /**
   * True if filter of given name is present.
   * @param string $name Identifier of the filter
   * @param bool $notEmpty If true, the filter value must also be not empty.
   */
  public function hasFilter(string $name, bool $notEmpty = false)
  {
    if ($notEmpty) {
      return !empty($this->filters[$name]);
    } else {
      return array_key_exists($name, $this->filters);
    }
  }

  /**
   * Return the filter value.
   * @throws InternalServerException If the filter is not present.
   */
  public function getFilter(string $name)
  {
    if (!$this->hasFilter($name)) {
      throw new InternalServerException("Filter $name is not present.");
    }
    return $this->filters[$name];
  }

  public function getRawFilters()
  {
    return $this->filters;
  }


  public function getLocale()
  {
    return $this->locale;
  }
}
