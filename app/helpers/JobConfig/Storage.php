<?php

namespace App\Helpers\JobConfig;

use App\Exceptions\JobConfigLoadingException;
use App\Exceptions\MalformedJobConfigException;
use App\Exceptions\JobConfigStorageException;
use App\Model\Entity\User;
use Nette\Caching\Cache;
use Nette\Caching\Storages\MemoryStorage;
use App\Helpers\Yaml;
use App\Helpers\YamlException;

/**
 * Storage of job configuration which is designed to load them from
 * given files, parse configuration and of course save it. MemoryCache is used
 * for smart caching of loaded configurations.
 */
class Storage
{

    const DEFAULT_MKDIR_MODE = 0777;

    /** @var Cache|null Run-time memory cache for job configurations */
    private static $cache = null;

    /**
     * Loader of job configuration.
     * @var Loader
     */
    private $jobLoader;

    /**
     * Target directory, where the files will be stored
     * @var string
     */
    private $jobConfigDir;

    public function __construct(string $jobConfigDir)
    {
        $this->jobConfigDir = $jobConfigDir;
        $this->jobLoader = new Loader();
    }

    /**
     * Lazy construction of configuration cache.
     * @return Cache
     */
    protected function getCache(): Cache
    {
        if (self::$cache === null) {
            self::$cache = new Cache(new MemoryStorage());
        }

        return self::$cache;
    }

    /**
     * Try to load configuration from cache if present, if not load it from given
     * path and parse it info JobConfig structure.
     * @param string $path
     * @return JobConfig Config for the evaluation server for this submission (updated job-id)
     */
    public function get(string $path): JobConfig
    {
        return $this->getCache()->load(
            $path,
            function () use ($path) {
                $yml = $this->loadConfig($path);
                return $this->parse($yml);
            }
        );
    }

    /**
     * Update stored job config with the given one.
     * @param JobConfig $config
     * @param string $filePath
     * @throws JobConfigStorageException
     */
    public function update(JobConfig $config, string $filePath)
    {
        if (!file_put_contents($filePath, (string)$config)) {
            throw new JobConfigStorageException("Cannot write the new job config to the storage.");
        }

        $this->getCache()->save($filePath, $config);
    }

    /**
     * Store given content into job config file.
     * @param JobConfig $config
     * @param User $user
     * @return string Path to newly stored file
     * @throws JobConfigStorageException
     */
    public function save(JobConfig $config, User $user): string
    {
        $filePath = $this->getFilePath($user->getId());

        // make sure the directory exists and that the file is stored correctly
        $dirname = dirname($filePath);
        if (!is_dir($dirname) && mkdir($dirname, self::DEFAULT_MKDIR_MODE, true) === false) {
            throw new JobConfigStorageException("Cannot create the directory for the job config.");
        }

        if (!file_put_contents($filePath, (string)$config)) {
            throw new JobConfigStorageException("Cannot write the new job config to the storage.");
        }

        // save the config to the cache
        $this->getCache()->save($filePath, $config);

        return $filePath;
    }

    /**
     * Archive job config on the given path, file will be moved and renamed.
     * @param string $path Path of the old config
     * @param string $prefix Prefix of the archived file name
     * @return string New file path
     * @throws JobConfigStorageException  When the file cannot be renamed (within in the same directory)
     */
    public function archive(string $path, string $prefix = "arch_")
    {
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
     * Return storage file path for given information.
     * @param string $userId
     * @param string $fileName
     * @return string Path, where the newly stored file will be saved
     */
    protected function getFilePath($userId, $fileName = "job-config.yml"): string
    {
        $fileNameOnly = pathinfo($fileName, PATHINFO_FILENAME);
        $ext = pathinfo($fileName, PATHINFO_EXTENSION);
        $uniqueId = uniqid();
        return "{$this->jobConfigDir}/user_{$userId}/{$fileNameOnly}_{$uniqueId}.$ext";
    }

    /**
     * Load file content from given path and return it.
     * @param string $path
     * @return string In case of file reading error
     * @throws MalformedJobConfigException In case of file reading error
     */
    private function loadConfig(string $path): string
    {
        $configFileName = realpath($path);
        if ($configFileName === false) {
            throw new MalformedJobConfigException("The configuration file does not exist on the server.");
        }

        $config = file_get_contents($configFileName);
        if ($config === false) {
            throw new MalformedJobConfigException("Cannot open the configuration file for reading.");
        }

        return $config;
    }

    /**
     * Parse configuration from given string a create and return new instance
     * of JobConfig.
     * @param string $config raw string configuration
     * @return JobConfig Parsed YAML config
     * @throws JobConfigLoadingException In case of semantic error
     * @throws MalformedJobConfigException In case of YAML parsing error
     */
    public function parse(string $config): JobConfig
    {
        try {
            $parsedConfig = Yaml::parse($config);
        } catch (YamlException $e) {
            throw new MalformedJobConfigException(
                "Assignment configuration file is not a valid YAML file and it cannot be parsed.",
                $e
            );
        }

        return $this->jobLoader->loadJobConfig($parsedConfig);
    }
}
