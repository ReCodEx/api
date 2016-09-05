<?php

namespace App\Helpers;

class MemoryCache {
  
  /** @var string Job config file contents cache */
  private $cache = [];

  /** @var mixed */
  private $default;

  public function __construct($default = NULL) {
    $this->default = $default;
  }

  /**
   * Load some value from the cache
   * @param  string $key      Cache key
   * @param  mixed  $default  The value, which will be returned when there is nothing in the cache for the path
   * @return mixed|NULL
   */
  public function load(string $key, $default = NULL) {
    if (!isset($this->cache[$key])) {
      return $default;
    }

    return $this->cache[$key];
  }

  /**
   * Store item into the cache
   * @param string $key   Cache key
   * @param mixed  $data  The data to store
   * @return mixed        The data to store
   */
  public function store(string $key, $data) {
    $this->cache[$key] = $data;
    return $data;
  }

}
