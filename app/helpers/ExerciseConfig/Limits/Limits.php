<?php

namespace App\Helpers\ExerciseConfig;

use App\Helpers\JobConfig\Limits as JobLimits;
use App\Helpers\Yaml;
use JsonSerializable;

/**
 * High-level configuration limits holder.
 */
class Limits implements JsonSerializable
{

    /** Wall time limit key */
    const WALL_TIME_KEY = "wall-time";
    /** Cpu time limit key */
    const CPU_TIME_KEY = "cpu-time";
    /** Memory limit key */
    const MEMORY_KEY = "memory";
    /** Parallel executions key */
    const PARALLEL_KEY = "parallel";


    /** @var float Wall time limit */
    protected $wallTime = 0;
    /** @var float Cpu time limit */
    protected $cpuTime = 0;
    /** @var int Memory limit */
    protected $memory = 0;
    /** @var int Parallel processes/threads count limit */
    protected $parallel = 0;

    public static function create(float $wallTime, float $cpuTime, int $memory, int $parallel): Limits
    {
        $result = new Limits();
        $result->setWallTime($wallTime);
        $result->setCpuTime($cpuTime);
        $result->setMemoryLimit($memory);
        $result->setParallel($parallel);
        return $result;
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
    public function setWallTime(float $time): Limits
    {
        $this->wallTime = $time;
        return $this;
    }

    /**
     * Returns cpu time limit.
     * @return float Number of seconds
     */
    public function getCpuTime(): float
    {
        return $this->cpuTime;
    }

    /**
     * Set cpu time limit in seconds.
     * @param float $time cpu time limit
     * @return $this
     */
    public function setCpuTime(float $time): Limits
    {
        $this->cpuTime = $time;
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
    public function setMemoryLimit(int $memory): Limits
    {
        $this->memory = $memory;
        return $this;
    }

    /**
     * Gets number of processes/threads which can be created in sandboxed program.
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
    public function setParallel(int $parallel): Limits
    {
        $this->parallel = $parallel;
        return $this;
    }


    /**
     * Compile exercise limits into job limits.
     * @param string $hwGroupId
     * @return JobLimits
     */
    public function compile(string $hwGroupId): JobLimits
    {
        return (new JobLimits())->setId($hwGroupId)
            ->setWallTime($this->wallTime)
            ->setTimeLimit($this->cpuTime)
            ->setMemoryLimit($this->memory)
            ->setParallel($this->parallel);
    }


    /**
     * Creates and returns properly structured array representing this object.
     * @return array
     */
    public function toArray(): array
    {
        $data = [];
        if ($this->wallTime > 0) {
            $data[self::WALL_TIME_KEY] = $this->wallTime;
        }
        if ($this->cpuTime > 0) {
            $data[self::CPU_TIME_KEY] = $this->cpuTime;
        }
        if ($this->memory) {
            $data[self::MEMORY_KEY] = $this->memory;
        }
        if ($this->parallel > 0) {
            $data[self::PARALLEL_KEY] = $this->parallel;
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
    public function jsonSerialize()
    {
        return $this->toArray();
    }
}
