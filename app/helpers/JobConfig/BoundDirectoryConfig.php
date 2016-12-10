<?php

namespace App\Helpers\JobConfig;
use Symfony\Component\Yaml\Yaml;


/**
 * BoundDirectory helper holder structure.
 */
class BoundDirectoryConfig {
  /** Source folder key */
  const SRC_KEY = "src";
  /** Destination folder key */
  const DST_KEY = "dst";
  /** Mode key */
  const MODE_KEY = "mode";

  /** @var string Source folder for bound directory */
  private $source = "";
  /** @var string Destination folder for bound directory */
  private $destination = "";
  /** @var string Mode in which folder is loaded */
  private $mode = "";
  /** @var array Additional data */
  private $data = [];

  /**
   * Get source folder for bound directory.
   * @return string
   */
  public function getSource(): string {
    return $this->source;
  }

  /**
   * Set source folder for bound directory.
   * @param string $src source folder
   * @return $this
   */
  public function setSource(string $src) {
    $this->source = $src;
    return $this;
  }

  /**
   * Get destination folder for source directory.
   * @return string
   */
  public function getDestination(): string {
    return $this->destination;
  }

  /**
   * Set destination folder for source directory.
   * @param string $dst destination folder
   * @return $this
   */
  public function setDestination(string $dst) {
    $this->destination = $dst;
    return $this;
  }

  /**
   * Get mounting mode of bounded directory.
   * @return string
   */
  public function getMode(): string {
    return $this->mode;
  }

  /**
   * Set mounting mode of bounded directory.
   * @param string $mode
   * @return $this
   */
  public function setMode(string $mode) {
    $this->mode = $mode;
    return $this;
  }

  /**
   * Get additional data.
   * Needed for forward compatibility.
   * @return array
   */
  public function getAdditionalData() {
    return $this->data;
  }

  /**
   * Set additional data, which cannot be parsed into structure.
   * Needed for forward compatibility.
   * @param array $data
   * @return $this
   */
  public function setAdditionalData(array $data) {
    $this->data = $data;
    return $this;
  }

  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
    $data = $this->data;
    $data[self::SRC_KEY] = $this->source;
    $data[self::DST_KEY] = $this->destination;
    $data[self::MODE_KEY] = $this->mode;
    return $data;
  }

  /**
   * Serialize the config.
   * @return string
   */
  public function __toString(): string {
    return Yaml::dump($this->toArray());
  }

}
