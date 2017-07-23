<?php

namespace App\Helpers\ExerciseConfig;
use Symfony\Component\Yaml\Yaml;
use JsonSerializable;


/**
 * High-level configuration exercise config holder.
 */
class ExerciseConfig implements JsonSerializable {

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
  public function getEnvironments(): array {
    return $this->environments;
  }

  /**
   * Add environment into this holder.
   * @param string $name
   * @return $this
   */
  public function addEnvironment(string $name): ExerciseConfig {
    $this->environments[] = $name;
    return $this;
  }

  /**
   * Remove environment according to given name identification.
   * @param string $name
   * @return $this
   */
  public function removeEnvironment(string $name): ExerciseConfig {
    if(($key = array_search($name, $this->environments)) !== false) {
      unset($this->environments[$key]);
    }
    return $this;
  }

  /**
   * Get associative array of tests.
   * @return Test[]
   */
  public function getTests(): array {
    return $this->tests;
  }

  /**
   * Get test for the given test name.
   * @param string $name
   * @return Test|null
   */
  public function getTest(string $name): ?Test {
    if (!array_key_exists($name, $this->tests)) {
      return null;
    }

    return $this->tests[$name];
  }

  /**
   * Add test into this holder.
   * @param string $name
   * @param Test $test
   * @return $this
   */
  public function addTest(string $name, Test $test): ExerciseConfig {
    $this->tests[$name] = $test;
    return $this;
  }

  /**
   * Remove test according to given test identification.
   * @param string $name
   * @return $this
   */
  public function removeTest(string $name): ExerciseConfig {
    unset($this->tests[$name]);
    return $this;
  }


  /**
   * Creates and returns properly structured array representing this object.
   * @return array
   */
  public function toArray(): array {
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
  public function __toString(): string {
    return Yaml::dump($this->toArray());
  }

  /**
   * Enable automatic serialization to JSON
   * @return array
   */
  public function jsonSerialize() {
    return $this->toArray();
  }
}
