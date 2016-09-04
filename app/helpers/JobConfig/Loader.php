<?php

namespace App\Helpers\JobConfig;

use App\Helpers\MemoryCache;
use App\Model\Entity\Submission;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class Loader {
    
  /** @var MemoryCache */
  private static $cache = NULL;

  protected static function getCache() {
    if (self::$cache = NULL) {
      self::$cache = new MemoryCache(NULL);
    }

    return self::$cache;
  } 

  /**
   * @return JobConfig Config for the evaluation server for this submission (updated job-id)
   */
  public static function getJobConfig(Submission $submission): JobConfig {
    $path = $submission->getExerciseAssignment()->getJobConfigFilePath();
    $jobConfig = self::getParsedJobConfig($submission->getId(), $path);
    return $jobConfig;
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
   * @return array Parsed YAML config with updated job-id
   */
  private static function parseJobConfig(string $jobId, string $config): JobConfig {
    try {
      $parsedConfig = Yaml::parse($config);
    } catch (ParseException $e) {
      throw new MalformedJobConfigException("Assignment configuration file is not a valid YAML file and it cannot be parsed.");
    }

    return new JobConfig($jobId, $parsedConfig);
  }

  /**
   * @param  string $path Path of the configuration file
   * @return array        The data structure stored in the config file
   */
  private static function getParsedJobConfig(string $jobId, string $path): JobConfig {
    $cached = self::getCache()->load($path);
    if ($cached === NULL) {
      $yml = self::loadConfig($path);
      $jobConfig = self::parseJobConfig($jobId, $yml);
      $cached = self::getCache()->store($path, $jobConfig);
    }

    return $cached;
  }

}
