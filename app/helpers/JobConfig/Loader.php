<?php

namespace App\Helpers\JobConfig;

use App\Exceptions\MalformedJobConfigException;
use App\Helpers\MemoryCache;
use App\Model\Entity\Submission;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class Loader {
    
  /** @var MemoryCache */
  private static $cache = NULL;

  protected static function getCache() {
    if (self::$cache === NULL) {
      self::$cache = new MemoryCache(NULL);
    }

    return self::$cache;
  } 

  /**
   * @return JobConfig Config for the evaluation server for this submission (updated job-id)
   */
  public static function getJobConfig(string $path): JobConfig {
    $cached = self::getFromCache($path);
    if ($cached === NULL) {
      $yml = self::loadConfig($path);
      $jobConfig = self::parseJobConfig($yml);
      $cached = self::getCache()->store($path, $jobConfig);
    }

    return $cached;
  }

  public static function getFromCache(string $path) {
    return self::getCache()->load($path);
  }

  public static function invalidateCache(string $path) {
    self::getCache()->remove($path);
  }

  /**
   * @throws MalformedJobConfigException
   * @return string YAML config file contents
   */
  private static function loadConfig(string $path): string {
    $configFileName = realpath($path);
    if ($configFileName === FALSE) {
      throw new MalformedJobConfigException("The configuration file does not exist on the server.");
    }

    $config = file_get_contents($configFileName);
    if ($config === FALSE) {
      throw new MalformedJobConfigException("Cannot open the configuration file for reading.");
    }

    return $config;
  }

  /**
   * @throws MalformedJobConfigException
   * @return array Parsed YAML config
   */
  public static function parseJobConfig(string $config): JobConfig {
    try {
      $parsedConfig = Yaml::parse($config);
    } catch (ParseException $e) {
      throw new MalformedJobConfigException("Assignment configuration file is not a valid YAML file and it cannot be parsed. ({$e->getMessage()})");
    }

    return new JobConfig($parsedConfig);
  }

}
