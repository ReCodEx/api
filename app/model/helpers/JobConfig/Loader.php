<?php

namespace App\Model\Helpers\JobConfig;

use App\Model\Entity\Submission;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

class Loader {
    
  /**
   * @return string YAML config for the evaluation server for this submission (updated job-id)
   */
  public static function getJobConfig(Submission $submission): JobConfig {
    $path = $submission->getExerciseAssignment()->getJobConfigFilePath();
    $jobConfig = self::getParsedJobConfig($submission->getId(), $path);
    return Yaml::dump($parsedConfig);
  }

  /** @var string Job config file contents cache */
  private static $cache = NULL;

  /**
   * [getFromCache description]
   * @param  string $path    Path of the job config
   * @param  mixed  $default The value, which will be returned when there is nothing in the cache for the path
   * @return JobConfig|NULL
   */
  private static function loadFromCache(string $path, $default = NULL) {
    if (!isset(self::$cache[$path])) {
      return $default;
    }

    return self::$cache[$path];
  }

  private static function storeInCache(string $path, JobConfig $data) {
    self::$cache[$path] = $data; // override any previous data
    return $data;
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
    $cached = self::loadFromCache($path, NULL);
    if ($cached === NULL) {
      $yml = self::loadConfig($path);
      $jobConfig = self::parseJobConfig($jobId, $yml);
      $cached = self::storeInCache($path, $jobConfig);
    }

    return $cached;
  }

}
