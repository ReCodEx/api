<?php

namespace App\Helpers\JobConfig;

use App\Helpers\Yaml;
use JsonSerializable;

/**
 * Limits helper holder structure which holds every possible information about
 * limits and keep forward compatibility.
 */
class Limits implements JsonSerializable
{
    /** Hardware group identification key */
    public const HW_GROUP_ID_KEY = "hw-group-id";
    /** Cpu time limit key */
    public const TIME_KEY = "time";
    /** Wall time limit key */
    public const WALL_TIME_KEY = "wall-time";
    /** Extra time limit key */
    public const EXTRA_TIME_KEY = "extra-time";
    /** Stack size limit key */
    public const STACK_SIZE_KEY = "stack-size";
    /** Memory limit key */
    public const MEMORY_KEY = "memory";
    /** Parallel executions key */
    public const PARALLEL_KEY = "parallel";
    /** Disk size key */
    public const DISK_SIZE_KEY = "disk-size";
    /** Disk files key */
    public const DISK_FILES_KEY = "disk-files";
    /** Environmental variables key */
    public const ENVIRON_KEY = "environ-variable";
    /** Bound directories key */
    public const BOUND_DIRECTORIES_KEY = "bound-directories";

    /** @var string ID of the hardware group */
    protected $id = "";
    /** @var float Time cpu limit */
    protected $time = 0;
    /** @var float Wall time limit */
    protected $wallTime = 0;
    /** @var float Extra time limit */
    protected $extraTime = 0;
    /** @var int Stack size limit */
    protected $stackSize = 0;
    /** @var int Memory limit */
    protected $memory = 0;
    /** @var int Parallel processes/threads count limit */
    protected $parallel = 0;
    /** @var int Disk size limit */
    protected $diskSize = 0;
    /** @var int Disk files limit */
    protected $diskFiles = 0;
    /** @var string[] Environmental variables array */
    protected $environVariables = [];
    /** @var BoundDirectoryConfig[] Bound directories array */
    protected $boundDirectories = [];
    /** @var array Additional data */
    protected $data = [];

    /**
     * Hardware group identification.
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * Set hardware group identification.
     * @param string $id hardware group identification
     * @return $this
     */
    public function setId(string $id)
    {
        $this->id = $id;
        return $this;
    }

    /**
     * Returns the cpu time limit in seconds.
     * @return float Number of seconds
     */
    public function getTimeLimit(): float
    {
        return $this->time;
    }

    /**
     * Set cpu time limit in seconds.
     * @param float $time time limit
     * @return $this
     */
    public function setTimeLimit(float $time)
    {
        $this->time = $time;
        return $this;
    }

    /**
     * Returns wall time limit.
     * @return float Number of seconds
     */
    public function getWallTime(): float
    {
        return $this->wallTime;
    }

    /**
     * Set wall time limit in seconds.
     * @param float $time wall time limit
     * @return $this
     */
    public function setWallTime(float $time)
    {
        $this->wallTime = $time;
        return $this;
    }

    /**
     * Returns extra time limit.
     * @return float Number of seconds
     */
    public function getExtraTime(): float
    {
        return $this->extraTime;
    }

    /**
     * Set extra time limit in seconds.
     * @param float $time extra time limit
     * @return $this
     */
    public function setExtraTime(float $time)
    {
        $this->extraTime = $time;
        return $this;
    }

    /**
     * Get maximum stack size.
     * @return int Number in kilobytes
     */
    public function getStackSize(): int
    {
        return $this->stackSize;
    }

    /**
     * Set maximum stack size.
     * @param int $size stack size
     * @return $this
     */
    public function setStackSize(int $size)
    {
        $this->stackSize = $size;
        return $this;
    }

    /**
     * Returns the memory limit in kilobytes.
     * @return int Number of kilobytes
     */
    public function getMemoryLimit(): int
    {
        return $this->memory;
    }

    /**
     * Set memory limit in kilobytes.
     * @param int $memory memory limit
     * @return $this
     */
    public function setMemoryLimit(int $memory)
    {
        $this->memory = $memory;
        return $this;
    }

    /**
     * Gets number of processes/threads which can be created in sand-boxed program.
     * @return int Number of processes/threads
     */
    public function getParallel(): int
    {
        return $this->parallel;
    }

    /**
     * Set number of parallel processes.
     * @param int $parallel number of processes
     * @return $this
     */
    public function setParallel(int $parallel)
    {
        $this->parallel = $parallel;
        return $this;
    }

    /**
     * Returns maximum disk IO operations count in kilobytes.
     * @return int Number in kilobytes
     */
    public function getDiskSize(): int
    {
        return $this->diskSize;
    }

    /**
     * Set maximum disk operations count in kilobytes.
     * @param int $size number in kilobytes
     * @return $this
     */
    public function setDiskSize(int $size)
    {
        $this->diskSize = $size;
        return $this;
    }

    /**
     * Gets maximum number of opened files in sand-boxed application.
     * @return int Number of files
     */
    public function getDiskFiles(): int
    {
        return $this->diskFiles;
    }

    /**
     * Set maximum number of opened files.
     * @param int $files number of files
     * @return $this
     */
    public function setDiskFiles(int $files)
    {
        $this->diskFiles = $files;
        return $this;
    }

    /**
     * Get array of environmental variables which will be set in sandbox.
     * @return array
     */
    public function getEnvironVariables(): array
    {
        return $this->environVariables;
    }

    /**
     * Set array of environmental variables.
     * @param array $variables array of strings
     * @return $this
     */
    public function setEnvironVariables(array $variables)
    {
        $this->environVariables = $variables;
        return $this;
    }

    /**
     * Gets array of BoundDirectory structures representing bound directories
     * inside sandbox.
     * @return array
     */
    public function getBoundDirectories(): array
    {
        return $this->boundDirectories;
    }

    /**
     * Add bound directory to internal array of bounded directories.
     * @param BoundDirectoryConfig $boundDir bound directory
     * @return $this
     */
    public function addBoundDirectory(BoundDirectoryConfig $boundDir)
    {
        $this->boundDirectories[] = $boundDir;
        return $this;
    }

    /**
     * Get additional data.
     * Needed for forward compatibility.
     * @return array
     */
    public function getAdditionalData(): array
    {
        return $this->data;
    }

    /**
     * Set additional data, which cannot be parsed into structure.
     * Needed for forward compatibility.
     * @param array $data
     * @return $this
     */
    public function setAdditionalData(array $data)
    {
        $this->data = $data;
        return $this;
    }

    /**
     * Creates and returns properly structured array representing this object.
     * @return array
     */
    public function toArray(): array
    {
        $data = $this->data;
        $data[self::HW_GROUP_ID_KEY] = $this->id;
        if ($this->time > 0) {
            $data[self::TIME_KEY] = $this->time;
        }
        if ($this->wallTime > 0) {
            $data[self::WALL_TIME_KEY] = $this->wallTime;
        }
        if ($this->extraTime > 0) {
            $data[self::EXTRA_TIME_KEY] = $this->extraTime;
        }
        if ($this->stackSize > 0) {
            $data[self::STACK_SIZE_KEY] = $this->stackSize;
        }
        if ($this->memory) {
            $data[self::MEMORY_KEY] = $this->memory;
        }
        if ($this->parallel > 0) {
            $data[self::PARALLEL_KEY] = $this->parallel;
        }
        if ($this->diskSize > 0) {
            $data[self::DISK_SIZE_KEY] = $this->diskSize;
        }
        if ($this->diskFiles > 0) {
            $data[self::DISK_FILES_KEY] = $this->diskFiles;
        }
        if (!empty($this->environVariables)) {
            $data[self::ENVIRON_KEY] = $this->environVariables;
        }

        $data[self::BOUND_DIRECTORIES_KEY] = [];
        foreach ($this->boundDirectories as $dir) {
            $data[self::BOUND_DIRECTORIES_KEY][] = $dir->toArray();
        }

        return $data;
    }

    /**
     * Serialize the config.
     * @return string
     */
    public function __toString(): string
    {
        return Yaml::dump($this->toArray());
    }

    /**
     * Enable automatic serialization to JSON
     * @return array
     */
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }
}
