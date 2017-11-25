<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Yaml\Yaml;

/**
 * Changes in exercise and pipeline configuration which reflects changes in
 * judge boxes interface.
 */
class Version20171122181453 extends AbstractMigration
{
  const JUDGE_TYPE = "judge";
  const JUDGE_ARGS_PORT = "args";
  const JUDGE_ARGS_VAR = "judge-args";
  const JUDGE_ARGS_REF = "\$judge-args";
  const CUSTOM_JUDGE_VAR = "custom-judge";
  const JUDGE_TYPE_VAR = "judge-type";

  /**
   * Add newly defined ports in judge box to corresponding structures in
   * database.
   * @param $pipelineConfig
   */
  private function processPipeline($pipelineConfig) {
    // prep
    $config = Yaml::parse($pipelineConfig["pipeline_config"]);

    // boxes walk through
    $found = false;
    foreach ($config["boxes"] as &$box) {
      if ($box["type"] == self::JUDGE_TYPE) {
        $found = true;
      } else {
        continue;
      }

      $box["portsIn"][self::JUDGE_ARGS_PORT] = [
        "type" => "string[]",
        "value" => self::JUDGE_ARGS_VAR
      ];
      $box["portsIn"][self::CUSTOM_JUDGE_VAR] = [
        "type" => "file",
        "value" => self::CUSTOM_JUDGE_VAR
      ];
    }

    if ($found === true) {
      // add box which will download custom judge if any
      $config["boxes"][] = [
        "name" => self::CUSTOM_JUDGE_VAR,
        "type" => "file-in",
        "portsIn" => [],
        "portsOut" => [
          "input" => [
            "type" => "file",
            "value" => self::CUSTOM_JUDGE_VAR
          ]
        ]
      ];

      // add custom judge variable
      $config["variables"][] = [
        "name" => self::CUSTOM_JUDGE_VAR,
        "type" => "file",
        "value" => ""
      ];

      // add judge args variable
      $config["variables"][] = [
        "name" => self::JUDGE_ARGS_VAR,
        "type" => "string[]",
        "value" => self::JUDGE_ARGS_REF
      ];

      // save
      $this->connection->executeQuery("UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id",
        ["config" => Yaml::dump($config), "id" => $pipelineConfig["id"]]);
    }
  }

  /**
   * Add judge arguments and custom judge file variable to configurations using
   * pipelines which contains judge box.
   * @param $exerciseConfig
   */
  private function processConfig($exerciseConfig) {
    // tests walk through
    $config = Yaml::parse($exerciseConfig["config"]);
    foreach ($config["tests"] as &$test) {
      // environments
      foreach ($test["environments"] as &$env) {

        $pipelines = &$env["pipelines"];
        if (empty($pipelines)) {
          continue;
        }

        // go through pipelines
        foreach ($pipelines as &$pipeline) {
          $pipelineVars = &$pipeline["variables"];

          // go through variables and try to find judge-type variable
          $found = false;
          foreach ($pipelineVars as &$variable) {
            if ($variable["name"] == self::JUDGE_TYPE_VAR) {
              $found = true;
            }
          }

          if ($found === true) {
            // add judge arguments variable
            $pipelineVars[] = [
              "name" => self::JUDGE_ARGS_VAR,
              "type" => "string[]",
              "value" => []
            ];

            $pipelineVars[] = [
              "name" => self::CUSTOM_JUDGE_VAR,
              "type" => "remote-file",
              "value" => ""
            ];
          }
        }
      }
    }

    // save
    $this->connection->executeQuery("UPDATE exercise_config SET config = :config WHERE id = :id",
      [ "config" => Yaml::dump($config), "id" => $exerciseConfig["id"] ]);
  }

  /**
   * @param Schema $schema
   */
  public function up(Schema $schema)
  {
    $this->connection->beginTransaction();

    // in pipelines which uses judges add ports for custom judges and arguments
    $pipelineResult = $this->connection->executeQuery("SELECT * FROM pipeline_config");
    foreach ($pipelineResult as $pipelineRow) {
      $this->processPipeline($pipelineRow);
    }

    // go through all exercise configs and search for the ones using judges
    $configResult = $this->connection->executeQuery("SELECT * FROM exercise_config");
    foreach ($configResult as $configRow) {
      $this->processConfig($configRow);
    }

    $this->connection->commit();
  }

  /**
   * @param Schema $schema
   */
  public function down(Schema $schema)
  {
    $this->throwIrreversibleMigrationException();
  }
}
