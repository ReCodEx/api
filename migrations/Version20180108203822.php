<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Yaml\Yaml;

/**
 * Added compilation extra files to chosen compilation boxes. Make appropriate
 * changes in db (pipeline configs and exercise configs).
 */
class Version20180108203822 extends AbstractMigration
{
  public static $WILL_BE_CHANGED_BOXES = ["fpc", "g++", "gcc", "javac", "mcs"];
  public static $REMOTE_FILES_TYPE = "remote-file[]";
  public static $FILES_TYPE = "file[]";
  public static $FILES_IN_TYPE = "files-in";
  public static $EXTRAS_BOX_NAME = "extras";
  public static $EXTRA_FILES_PORT = "extra-files";
  public static $EXTRA_FILE_NAMES_PORT = "extra-file-names";
  public static $EXTRA_FILE_NAMES_REF = "\$extra-file-names";


  /**
   * Add empty array of extra-files variable.
   * @param string[] $changedPipelines
   * @throws \Doctrine\DBAL\DBALException
   */
  private function updateConfigs(array $changedPipelines) {
    $configResult = $this->connection->executeQuery("SELECT * FROM exercise_config");
    foreach ($configResult as $exerciseConfig) {
      $config = Yaml::parse($exerciseConfig["config"]);

      foreach ($config["tests"] as &$test) {
        foreach ($test["environments"] as &$env) {
          foreach ($env["pipelines"] as &$pipeline) {
            if (in_array($pipeline["name"], $changedPipelines)) {
              $pipeline["variables"][] = [
                "name" => self::$EXTRA_FILES_PORT,
                "type" => self::$REMOTE_FILES_TYPE,
                "value" => []
              ];
              $pipeline["variables"][] = [
                "name" => self::$EXTRA_FILE_NAMES_PORT,
                "type" => self::$FILES_TYPE,
                "value" => []
              ];
            }
          }
        }
      }

      $this->connection->executeQuery("UPDATE exercise_config SET config = :config WHERE id = :id",
        ["id" => $exerciseConfig["id"], "config" => Yaml::dump($config)]);
    }
  }

  /**
   * Add files-in box and input port to chosen compilation boxes.
   * @return string[] pipelines which was changed
   * @throws \Doctrine\DBAL\DBALException
   */
  private function updatePipelines(): array {
    $changedPipelines = [];
    $pipelinesResult = $this->connection->executeQuery("SELECT * FROM pipeline");
    foreach ($pipelinesResult as $pipeline) {
      $pipelineConfig = $this->connection->executeQuery("SELECT * FROM pipeline_config WHERE id = '{$pipeline["pipeline_config_id"]}'")->fetch();
      $config = Yaml::parse($pipelineConfig["pipeline_config"]);

      $compilationBoxFound = false;
      foreach ($config["boxes"] as &$box) {
        if (in_array($box["type"], self::$WILL_BE_CHANGED_BOXES)) {
          $compilationBoxFound = true;
          $box["portsIn"][self::$EXTRA_FILES_PORT] = [
            "type" => self::$FILES_TYPE,
            "value" => self::$EXTRA_FILES_PORT
          ];
        }
      }

      if ($compilationBoxFound) {
        $changedPipelines[] = $pipeline["id"];

        // add extra files variable
        $config["variables"][] = [
          "name" => self::$EXTRA_FILES_PORT,
          "type" => self::$FILES_TYPE,
          "value" => self::$EXTRA_FILE_NAMES_REF
        ];

        // add files-in box to download extra files
        $config["boxes"][] = [
          "name" => self::$EXTRAS_BOX_NAME,
          "type" => self::$FILES_IN_TYPE,
          "portsIn" => [],
          "portsOut" => [
            "input" => [
              "type" => self::$FILES_TYPE,
              "value" => self::$EXTRA_FILES_PORT
            ]
          ]
        ];
      }

      $this->connection->executeQuery("UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id",
        ["id" => $pipelineConfig["id"], "config" => Yaml::dump($config)]);
    }
    return $changedPipelines;
  }

  /**
   * @param Schema $schema
   * @throws \Doctrine\DBAL\ConnectionException
   * @throws \Doctrine\DBAL\DBALException
   */
  public function up(Schema $schema) {
    $this->connection->beginTransaction();
    $changedPipelines = $this->updatePipelines();
    $this->updateConfigs($changedPipelines);
    $this->connection->commit();
  }

  /**
   * @param Schema $schema
   * @throws \Doctrine\DBAL\Migrations\IrreversibleMigrationException
   */
  public function down(Schema $schema) {
    $this->throwIrreversibleMigrationException();
  }

}
