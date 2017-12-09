<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
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
   * @throws \Doctrine\DBAL\DBALException
   */
  private function updateExerciseConfig(string $configId, array $tests) {
    $exerciseConfig = $this->connection->executeQuery("SELECT * FROM exercise_config WHERE id = '{$configId}'")->fetch();
    $config = Yaml::parse($exerciseConfig["config"]);

    foreach ($config["tests"] as $testName => $test) {
      if (!array_key_exists($testName, $tests)) {
        continue;
      }

      unset($config["tests"][$testName]);
      $config["tests"][$tests[$testName]] = $test;
    }

    $this->connection->executeQuery("UPDATE exercise_config SET config = :config WHERE id = :id",
      ["id" => $configId, "config" => Yaml::dump($config)]);
  }

  /**
   * Update given limits tests from names to ids.
   * @param array $limits
   * @param array $tests
   * @throws \Doctrine\DBAL\DBALException
   */
  private function updateExerciseLimits($limits, array $tests) {
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
    }
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
      $this->updateExerciseConfig($exercise["exercise_config_id"], $tests);

      // update limits
      $limits = $this->connection->executeQuery("SELECT * FROM exercise_limits " .
        "INNER JOIN {$exerciseType}_exercise_limits AS eel ON eel.exercise_limits_id = id " .
        "WHERE {$exerciseType}_id = '{$exercise["id"]}'");
      $this->updateExerciseLimits($limits, $tests);
    }
  }


  /**
   * @param Schema $schema
   * @throws \Doctrine\DBAL\ConnectionException
   * @throws \Doctrine\DBAL\DBALException
   */
  public function up(Schema $schema)
  {
    $this->connection->beginTransaction();
    $this->updateExercises("exercise");
    $this->updateExercises("assignment");
    $this->connection->commit();
  }

  /**
   * @param Schema $schema
   * @throws \Doctrine\DBAL\Migrations\IrreversibleMigrationException
   */
  public function down(Schema $schema)
  {
    $this->throwIrreversibleMigrationException();
  }
}
