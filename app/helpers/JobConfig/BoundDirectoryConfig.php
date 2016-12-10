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

  public function setSource($src) {
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

  public function setDestination($dst) {
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

  public function setMode($mode) {
    $this->mode = $mode;
    return $this;
  }

  public function getAdditionalData() {
    return $this->data;
  }

  public function setAdditionalData($data) {
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
