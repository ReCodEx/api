<?php

namespace App\Helpers\ExerciseConfig;

use App\Helpers\Yaml;
use JsonSerializable;
use App\Exceptions\InternalServerException;

/**
 * High-level configuration exercise config holder.
 */
class ExerciseConfig implements JsonSerializable
{

    /** Key for the environments list item */
    const ENVIRONMENTS_KEY = "environments";
    /** Key for the tests item */
    const TESTS_KEY = "tests";

    /** @var array tests indexed by test name */
    protected $tests = array();
    /** @var array list of which can be present in tests environments */
    protected $environments = array();


    /**
     * Get environments list.
     * @return string[]
     */
    public function getEnvironments(): array
    {
        return $this->environments;
    }

    /**
     * Add environment into this holder.
     * @param string $id
     * @return $this
     */
    public function addEnvironment(string $id): ExerciseConfig
    {
        $this->environments[] = $id;
        return $this;
    }

    /**
     * Remove environment according to given name identification.
     * @param string $id
     * @return $this
     */
    public function removeEnvironment(string $id): ExerciseConfig
    {
        if (($key = array_search($id, $this->environments)) !== false) {
            unset($this->environments[$key]);
        }
        return $this;
    }

    /**
     * Get associative array of tests.
     * @return Test[]
     */
    public function getTests(): array
    {
        return $this->tests;
    }

    /**
     * Get test for the given test name.
     * @param string $id
     * @return Test|null
     */
    public function getTest(string $id): ?Test
    {
        if (!array_key_exists($id, $this->tests)) {
            return null;
        }

        return $this->tests[$id];
    }

    /**
     * Add test into this holder.
     * @param string $id
     * @param Test $test
     * @return $this
     */
    public function addTest(string $id, Test $test): ExerciseConfig
    {
        $this->tests[$id] = $test;
        return $this;
    }

    /**
     * Remove test according to given test identification.
     * @param string $id
     * @return $this
     */
    public function removeTest(string $id): ExerciseConfig
    {
        unset($this->tests[$id]);
        return $this;
    }

    /**
     * Remove test according to given test identification.
     * @param string $id
     * @return $this
     */
    public function changeTestId(string $oldId, string $newId): ExerciseConfig
    {
        $test = $this->getTest($oldId);
        if ($test) {
            if ($this->getTest($newId)) {
                throw new InternalServerException(
                    "Serious internal error. Newly created test ID is already present in exercise config!"
                );
            }
            $this->removeTest($oldId)->addTest($newId, $test);
        }
        return $this;
    }


    /**
     * Creates and returns properly structured array representing this object.
     * @return array
     */
    public function toArray(): array
    {
        $data = [];

        $data[self::ENVIRONMENTS_KEY] = array();
        foreach ($this->environments as $envId) {
            $data[self::ENVIRONMENTS_KEY][] = $envId;
        }

        $data[self::TESTS_KEY] = array();
        foreach ($this->tests as $testId => $test) {
            $data[self::TESTS_KEY][$testId] = $test->toArray();
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
