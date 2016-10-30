<?php

namespace App\Helpers;

class MemoryCache {

  /** @var array Cached values, indexed by their keys */
  private $cache = [];

  /**
   * Load some value from the cache
   * @param  string $key      Cache key
   * @param  mixed  $default  The value, which will be returned when there is nothing in the cache for the key
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
   * @return mixed        The stored data
   */
  public function store(string $key, $data) {
    $this->cache[$key] = $data;
    return $data;
  }

  /**
   * Remove an item into the cache
   * @param string $key   Cache key
   * @return void
   */
  public function remove(string $key) {
    unset($this->cache[$key]);
  }

}
