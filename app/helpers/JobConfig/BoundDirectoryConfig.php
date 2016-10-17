<?php

namespace App\Helpers\JobConfig;
use App\Exceptions\JobConfigLoadingException;
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
  private $source;
  /** @var string Destination folder for bound directory */
  private $destination;
  /** @var string Mode in which folder is loaded */
  private $mode;
  /** @var array Additional data */
  private $data;

  /**
   * Construct BoundDirectory from given structured configuration.
   * @param array $data Structured configuration
   * @throws JobConfigLoadingException In case of any parsing error
   */
  public function __construct(array $data) {

    if (!isset($data[self::SRC_KEY])) {
      throw new JobConfigLoadingException("Bound directory does not contain required field '" . self::SRC_KEY . "'");
    }
    $this->source = $data[self::SRC_KEY];
    unset($data[self::SRC_KEY]);

    if (!isset($data[self::DST_KEY])) {
      throw new JobConfigLoadingException("Bound directory does not contain required field '" . self::DST_KEY . "'");
    }
    $this->destination = $data[self::DST_KEY];
    unset($data[self::DST_KEY]);

    if (!isset($data[self::MODE_KEY])) {
      throw new JobConfigLoadingException("Bound directory does not contain required field '" . self::MODE_KEY . "'");
    }
    $this->mode = $data[self::MODE_KEY];
    unset($data[self::MODE_KEY]);

    // *** LOAD REMAINING DATA
    $this->data = $data;
  }

  /**
   * Get source folder for bound directory.
   * @return string
   */
  public function getSource(): string {
    return $this->source;
  }

  /**
   * Get destination folder for source directory.
   * @return string
   */
  public function getDestination(): string {
    return $this->destination;
  }

  /**
   * Get mounting mode of bounded directory.
   * @return string
   */
  public function getMode(): string {
    return $this->mode;
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
