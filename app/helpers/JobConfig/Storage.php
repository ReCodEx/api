<?php

namespace App\Helpers\JobConfig;

use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\MalformedJobConfigException;
use App\Exceptions\JobConfigStorageException;
use Nette\Caching\Cache;
use Nette\Caching\Storages\MemoryStorage;
use Symfony\Component\Yaml\Yaml;
use Symfony\Component\Yaml\Exception\ParseException;

/**
 * Storage of job configuration which is designed to load them from
 * given files, parse configuration and of course save it. MemoryCache is used
 * for smart caching of loaded configurations.
 */
class Storage {

  /** @var Cache|NULL Run-time memory cache for job configurations */
  private static $cache = NULL;

  /**
   * Loader of job configuration.
   * @var Loader
   */
  private $jobLoader;

  public function __construct() {
    $this->jobLoader = new Loader;
  }

  /**
   * Lazy construction of configuration cache.
   * @return Cache
   */
  protected function getCache(): Cache {
    if (self::$cache === NULL) {
      self::$cache = new Cache(new MemoryStorage());
    }

    return self::$cache;
  }

  /**
   * Try to load configuration from cache if present, if not load it from given
   * path and parse it info JobConfig structure.
   * @return JobConfig Config for the evaluation server for this submission (updated job-id)
   */
  public function getJobConfig(string $path): JobConfig {
    return $this->getCache()->load($path, function () use ($path) {
      $yml = $this->loadConfig($path);
      return $this->parseJobConfig($yml);
    });
  }

  /**
   * Store new configuration object to the file storage.
   * @param  JobConfig   $config        The configuration to be stored
   * @param  string      $path          Path of the configuration file
   * @param  boolean     $doNotArchive  Whether to archive the file (if there is some existing file)
   * @return string|NULL                Path of the archived configuration file.
   * @throws JobConfigStorageException In case of any error
   */
  public function saveJobConfig(JobConfig $config, string $path, $doNotArchive = FALSE) {
    $archivedConfigPath = NULL;
    if (is_file($path) && $doNotArchive !== TRUE) {
      $archivedConfigPath = $this->archiveJobConfig($path);
    }

    // make sure the directory exists and that the file is stored correctly
    $dirname = dirname($path);
    if (!is_dir($dirname) && mkdir($dirname, 0777, TRUE) === FALSE) {
      throw new JobConfigStorageException("Cannot create the directory for the job config.");
    }

    if (!file_put_contents($path, (string) $config)) {
      throw new JobConfigStorageException("Cannot write the new job config to the storage.");
    }

    // save the config to the cache
    $this->getCache()->save($path, $config);

    return $archivedConfigPath;
  }

  /**
   * Archive job config on the given path, file will be moved and renamed.
   * @param  string $path     Path of the old config
   * @param  string $prefix  Prefix of the archived file name
   * @return string           New file path
   * @throws JobConfigStorageException  When the file cannot be renamed (within in the same directory)
   */
  public function archiveJobConfig(string $path, string $prefix = "arch_") {
    $dirname = dirname($path);
    $filename = pathinfo($path, PATHINFO_FILENAME);
    $ext = pathinfo($path, PATHINFO_EXTENSION);
    $copyId = 0;

    do {
      $copyId++;
      $newFileName = "{$prefix}{$filename}_{$copyId}.{$ext}";
    } while (is_file("$dirname/$newFileName"));

    $newPath = "$dirname/$newFileName";
    if (!rename($path, $newPath)) {
      throw new JobConfigStorageException("Cannot archive job config.");
    }

    return $newPath;
  }

  /**
   * Load file content from given path and return it.
   * @throws MalformedJobConfigException In case of file reading error
   * @return string YAML config file contents
   */
  private function loadConfig(string $path): string {
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
   * Parse configuration from given string a create and return new instance
   * of JobConfig.
   * @param string $config raw string configuration
   * @throws MalformedJobConfigException In case of YAML parsing error
   * @throws JobConfigLoadingException In case of semantic error
   * @return JobConfig Parsed YAML config
   */
  public function parseJobConfig(string $config): JobConfig {
    try {
      $parsedConfig = Yaml::parse($config);
    } catch (ParseException $e) {
      throw new MalformedJobConfigException("Assignment configuration file is not a valid YAML file and it cannot be parsed.", $e);
    }

    return $this->jobLoader->loadJobConfig($parsedConfig);
  }

}
