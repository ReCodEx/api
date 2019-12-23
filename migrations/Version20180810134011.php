<?php

namespace Migrations;

use Doctrine\DBAL\DBALException;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use App\Helpers\Yaml;

/**
 * Remove nasty hack which adds /java-jars folder on classpath of all java executions.
 */
class Version20180810134011 extends AbstractMigration
{
  private static $JAVAC_BOX = "javac";
  private static $JAVA_RUNNER_BOX = "java-runner";
  private static $JAR_FILES_PORT = "jar-files";
  private static $FILES_TYPE = "file[]";
  private static $JARS_BOX_NAME = "jars";
  private static $PASSED_JARS_BOX_NAME = "jars-passed";
  private static $FILES_IN_TYPE = "files-in";
  private static $FILES_OUT_TYPE = "files-out";
  private static $REMOTE_FILES_TYPE = "remote-file[]";

  /**
   * @return array java compilation changed pipelines
   * @throws DBALException
   */
  private function updatePipelines(): array {
    $changedCompilationPipelines = [];
    $pipelinesResult = $this->connection->executeQuery("SELECT * FROM pipeline");
    foreach ($pipelinesResult as $pipeline) {
      $pipelineConfig = $this->connection->executeQuery("SELECT * FROM pipeline_config WHERE id = '{$pipeline["pipeline_config_id"]}'")->fetch();
      $config = Yaml::parse($pipelineConfig["pipeline_config"]);
      $changed = false;

      foreach ($config["boxes"] as &$box) {
        if ($box["type"] === self::$JAVAC_BOX) {
          $changed = true;
          $changedCompilationPipelines[] = $pipeline["id"];

          $box["portsIn"][self::$JAR_FILES_PORT] = [
            "type" => self::$FILES_TYPE,
            "value" => self::$JAR_FILES_PORT
          ];
          // input jar files in compilation
          $config["boxes"][] = [
            "name" => self::$JARS_BOX_NAME,
            "type" => self::$FILES_IN_TYPE,
            "portsIn" => [],
            "portsOut" => [
              "input" => [
                "type" => self::$FILES_TYPE,
                "value" => self::$JAR_FILES_PORT
              ]
            ]
          ];
          // output jar files from compilation
          $config["boxes"][] = [
            "name" => self::$PASSED_JARS_BOX_NAME,
            "type" => self::$FILES_OUT_TYPE,
            "portsIn" => [
              "output" => [
                "type" => self::$FILES_TYPE,
                "value" => self::$JAR_FILES_PORT
              ]
            ],
            "portsOut" => []
          ];
          $config["variables"][] = [
            "name" => self::$JAR_FILES_PORT,
            "type" => self::$FILES_TYPE,
            "value" => []
          ];
        }

        if ($box["type"] === self::$JAVA_RUNNER_BOX) {
          $changed = true;

          $box["portsIn"][self::$JAR_FILES_PORT] = [
            "type" => self::$FILES_TYPE,
            "value" => self::$JAR_FILES_PORT
          ];
          // input jar files in execution
          $config["boxes"][] = [
            "name" => self::$JARS_BOX_NAME,
            "type" => self::$FILES_IN_TYPE,
            "portsIn" => [],
            "portsOut" => [
              "input" => [
                "type" => self::$FILES_TYPE,
                "value" => self::$JAR_FILES_PORT
              ]
            ]
          ];
          $config["variables"][] = [
            "name" => self::$JAR_FILES_PORT,
            "type" => self::$FILES_TYPE,
            "value" => []
          ];
        }
      }

      if ($changed) {
        $this->connection->executeQuery("UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id",
          ["id" => $pipelineConfig["id"], "config" => Yaml::dump($config)]);
      }
    }
    return $changedCompilationPipelines;
  }

  /**
   * @param array $changedPipelines
   * @throws DBALException
   */
  private function updateExercises(array $changedPipelines) {
    $configResult = $this->connection->executeQuery("SELECT * FROM exercise_config");
    foreach ($configResult as $exerciseConfig) {
      $config = Yaml::parse($exerciseConfig["config"]);

      $pipelineFound = false;
      foreach ($config["tests"] as &$test) {
        foreach ($test["environments"] as &$env) {
          foreach ($env["pipelines"] as &$pipeline) {
            if (in_array($pipeline["name"], $changedPipelines)) {
              $pipelineFound = true;
              $pipeline["variables"][] = [
                "name" => self::$JAR_FILES_PORT,
                "type" => self::$REMOTE_FILES_TYPE,
                "value" => []
              ];
            }
          }
        }
      }

      if ($pipelineFound) {
        $this->connection->executeQuery("UPDATE exercise_config SET config = :config WHERE id = :id",
          ["id" => $exerciseConfig["id"], "config" => Yaml::dump($config)]);
      }
    }
  }

  /**
   * @param Schema $schema
   * @throws DBALException
   */
  public function up(Schema $schema): void {
    $this->connection->beginTransaction();
    $changedPipelines = $this->updatePipelines();
    $this->updateExercises($changedPipelines);
    $this->connection->commit();
  }

  /**
   * @param Schema $schema
   */
  public function down(Schema $schema): void {
    $this->throwIrreversibleMigrationException();
  }
}
