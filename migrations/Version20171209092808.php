<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Faker\Provider\Uuid;
use Symfony\Component\Yaml\Yaml;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171209092808 extends AbstractMigration
{
  const REMOTE_FILE = "remote-file";
  const REMOTE_FILES = "remote-file[]";

  /**
   * Create array indexed by names of tests and containing test ids.
   * @param $tests
   * @return array
   */
  private function createTestsArray($tests): array {
    $result = [];
    foreach ($tests as $test) {
      $result[$test["name"]] = $test["id"];
    }
    return $result;
  }

  /**
   * Update given configuration tests from names to ids.
   * @param $configId
   * @param array $tests
   * @return string
   * @throws \Doctrine\DBAL\DBALException
   */
  private function updateExerciseConfig(string $configId, array $tests): string {
    $exerciseConfig = $this->connection->executeQuery("SELECT * FROM exercise_config WHERE id = '{$configId}'")->fetch();
    $config = Yaml::parse($exerciseConfig["config"]);

    foreach ($config["tests"] as $testName => $test) {
      if (!array_key_exists($testName, $tests)) {
        continue;
      }

      unset($config["tests"][$testName]);
      $config["tests"][$tests[$testName]] = $test;
    }

    $uuid = Uuid::uuid();
    $this->connection->executeQuery("INSERT INTO exercise_config (id, created_from_id, author_id, config, created_at) " .
        "VALUES (:id, :from, :author, :config, :at)",
      ["id" => $uuid, "config" => Yaml::dump($config), "from" => $configId, "author" => $exerciseConfig["author_id"], "at" => $exerciseConfig["created_at"]]);
    return $uuid;
  }

  /**
   * Update given limits tests from names to ids.
   * @param array $limits
   * @param array $tests
   * @return string[]
   * @throws \Doctrine\DBAL\DBALException
   */
  private function updateExerciseLimits($limits, array $tests): array {
    $ids = [];
    foreach ($limits as $lim) {
      $limitConfig = $this->connection->executeQuery("SELECT * FROM exercise_limits WHERE id = '{$lim["id"]}'")->fetch();
      $config = Yaml::parse($limitConfig["limits"]);

      foreach ($config as $testName => $testLimits) {
        if (!array_key_exists($testName, $tests)) {
          continue;
        }

        unset($config[$testName]);
        $config[$tests[$testName]] = $testLimits;
      }

      $this->connection->executeQuery("UPDATE exercise_limits SET limits = :config WHERE id = :id",
        ["id" => $lim["id"], "config" => Yaml::dump($config)]);

      $uuid = Uuid::uuid();
      $this->connection->executeQuery("INSERT INTO exercise_limits (id, created_from_id, author_id, runtime_environment_id, hardware_group_id, limits, created_at) " .
        "VALUES (:id, :from, :author, :runtime, :hw, :config, :at)",
        [
          "id" => $uuid, "config" => Yaml::dump($config), "from" => $lim["id"], "author" => $limitConfig["author_id"],
          "runtime" => $limitConfig["runtime_environment_id"], "hw" => $limitConfig["hardware_group_id"], "at" => $limitConfig["created_at"]
        ]);
      $ids[] = $uuid;
    }
    return $ids;
  }

  /**
   * @param $exerciseType
   * @throws \Doctrine\DBAL\DBALException
   */
  private function updateExercises($exerciseType) {
    $exercises = $this->connection->executeQuery("SELECT * FROM $exerciseType");
    foreach ($exercises as $exercise) {
      // load files and make them associative array suitable for searching
      $testsResult = $this->connection->executeQuery("SELECT * FROM exercise_test " .
        "INNER JOIN {$exerciseType}_exercise_test AS eet ON eet.exercise_test_id = id " .
        "WHERE {$exerciseType}_id = '{$exercise["id"]}'");
      $tests = $this->createTestsArray($testsResult);

      // update config
      $configId = $this->updateExerciseConfig($exercise["exercise_config_id"], $tests);
      $this->connection->executeQuery("UPDATE $exerciseType SET exercise_config_id = :configId WHERE id = :id",
        ["id" => $exercise["id"], "configId" => $configId]);

      // update limits
      $limits = $this->connection->executeQuery("SELECT * FROM exercise_limits " .
        "INNER JOIN {$exerciseType}_exercise_limits AS eel ON eel.exercise_limits_id = id " .
        "WHERE {$exerciseType}_id = '{$exercise["id"]}'");
      $limitIds = $this->updateExerciseLimits($limits, $tests);

      // delete relations
      $this->connection->executeQuery("DELETE FROM {$exerciseType}_exercise_limits WHERE {$exerciseType}_id = '{$exercise["id"]}'");
      // add new relations
      foreach ($limitIds as $limitId) {
        $this->connection->executeQuery("INSERT INTO {$exerciseType}_exercise_limits ({$exerciseType}_id, exercise_limits_id) VALUES (:exercise, :limit)",
          ["exercise" => $exercise["id"], "limit" => $limitId]);
      }
    }
  }


  /**
   * @param Schema $schema
   * @throws \Doctrine\DBAL\ConnectionException
   * @throws \Doctrine\DBAL\DBALException
   */
  public function up(Schema $schema): void
  {
    $this->connection->beginTransaction();
    $this->updateExercises("exercise");
    $this->updateExercises("assignment");
    $this->connection->commit();
  }

  /**
   * @param Schema $schema
   */
  public function down(Schema $schema): void
  {
    $this->throwIrreversibleMigrationException();
  }
}
