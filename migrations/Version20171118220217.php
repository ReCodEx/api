<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use App\Helpers\Yaml;

/**
 * No ORM changes, migrates only pipelines and exercise config data within
 * database.
 */
class Version20171118220217 extends AbstractMigration
{

  const STDIN_FILE_PIPE = "stdin-file";
  const INPUT_FILE_VAR = "input-file";
  const STDIN_FILE_VAR = "stdin-file";
  const INPUT_FILES_VAR = "input-files";
  const STDIN_STDOUT_PIPE = "stdin-stdout";
  const FILES_FILE_PIPE = "files-file";
  const FILES_STDOUT_PIPE = "files-stdout";
  const ACTUAL_INPUTS_VAR = "actual-inputs";
  const ACTUAL_INPUTS_REF = "\$actual-inputs";

  /**
   * Change name of input-file variable in pipeline_config to stdin-file.
   * @param $pipelineConfigId
   */
  private function changeInputFileName($pipelineConfigId) {
    // prep
    $pipelineConfig = $this->connection->executeQuery("SELECT * FROM pipeline_config WHERE id = '{$pipelineConfigId}'")->fetch();
    $config = Yaml::parse($pipelineConfig["pipeline_config"]);

    // boxes walk through
    foreach ($config["boxes"] as &$box) {
      foreach ($box["portsIn"] as $portName => &$portIn) {
        if ($portIn["value"] === self::INPUT_FILE_VAR) {
          $portIn["value"] = self::STDIN_FILE_VAR;
        }

        // to input-files port there has to be input-files variable attached
        if ($portName === self::INPUT_FILES_VAR) {
          $portIn["value"] = self::INPUT_FILES_VAR;
        }
      }

      foreach ($box["portsOut"] as $portName => &$portOut) {
        if ($portOut["value"] === self::INPUT_FILE_VAR) {
          $portOut["value"] = self::STDIN_FILE_VAR;
        }
      }
    }

    // add input-files data-in box
    $config["boxes"][] = [
      "name" => self::INPUT_FILES_VAR,
      "type" => "files-in",
      "portsIn" => [],
      "portsOut" => [
        "input" => [
          "type" => "file[]",
          "value" => self::INPUT_FILES_VAR
        ]
      ]
    ];

    // variables walk through
    foreach ($config["variables"] as &$variable) {
      if ($variable["name"] === self::INPUT_FILE_VAR) {
        $variable["name"] = self::STDIN_FILE_VAR;
      }
    }

    // add input-files variable
    $config["variables"][] = [
      "name" => self::INPUT_FILES_VAR,
      "type" => "file[]",
      "value" => self::ACTUAL_INPUTS_REF
    ];

    // save
    $dumped = Yaml::dump($config);
    $this->connection->executeQuery("UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id",
      [ "config" => $dumped, "id" => $pipelineConfigId ]);
  }

  /**
   * Change name of input-file variable in pipeline_config to stdin-file.
   * @param $exerciseConfig
   */
  private function processConfigs($exerciseConfig) {
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
          $pipelineName = $pipeline["name"];
          $pipelineVars = &$pipeline["variables"];

          // stdin pipeline found
          if (array_search($pipelineName, $this->stdinPipelines) !== false) {
            // go through variables in pipeline which is supposed to be changed
            foreach ($pipelineVars as &$variable) {
              if ($variable["name"] !== self::INPUT_FILE_VAR) {
                continue;
              }

              // we found it! then change it
              $variable["name"] = self::STDIN_FILE_VAR;
            }

            // add input-files variable
            $pipelineVars[] = [
              "name" => self::INPUT_FILES_VAR,
              "type" => "remote-file[]",
              "value" => []
            ];

            // add actual-inputs variable
            $pipelineVars[] = [
              "name" => self::ACTUAL_INPUTS_VAR,
              "type" => "file[]",
              "value" => []
            ];
          }

          // pipeline which should be replaced found
          if (array_key_exists($pipelineName, $this->replacedPipelines)) {
            $pipeline["name"] = $this->replacedPipelines[$pipelineName];

            // add stdin-file variable
            $pipelineVars[] = [
              "name" => self::STDIN_FILE_VAR,
              "type" => "remote-file",
              "value" => ""
            ];
          }
        }
      }
    }

    // save
    $dumped = Yaml::dump($config);
    $this->connection->executeQuery("UPDATE exercise_config SET config = :config WHERE id = :id",
      [ "config" => $dumped, "id" => $exerciseConfig["id"] ]);
  }


  private $stdinPipelines = [];
  private $replacedPipelines = [];

  /**
   * Process stdin pipeline, add variable and change name.
   * @param $pipelineType
   * @param $replacedType
   */
  private function processPipeline($pipelineType, $replacedType, $namePostfix) {
    $stdinFileResult = $this->connection->executeQuery("SELECT * FROM pipeline WHERE name LIKE '%{$pipelineType}%'");
    foreach ($stdinFileResult as $stdinFile) {
      // change input-file variable name to stdin-file
      $this->changeInputFileName($stdinFile["pipeline_config_id"]);
      // add current pipeline to array of ids for which variable name should be changed
      $this->stdinPipelines[] = $stdinFile["id"];

      // construct files-file pipeline name
      $stdinName = $stdinFile["name"];
      $filesName = str_replace($pipelineType, $replacedType, $stdinName);

      // find files-file pipelines which correspond to stdin-file pipeline
      $filesFileResult = $this->connection->executeQuery("SELECT * FROM pipeline WHERE name = '$filesName'");
      foreach ($filesFileResult as $filesFile) {
        // add id to pipelines which should be replaced by the stdin-file one
        $this->replacedPipelines[$filesFile["id"]] = $stdinFile["id"];
      }

      // change name of the pipeline
      $newName = str_replace($pipelineType . " ", "", $stdinName) . " " . $namePostfix;
      $this->connection->executeQuery("UPDATE pipeline SET name = '{$newName}' WHERE id = '{$stdinFile["id"]}'");
    }
  }

  /**
   * @param Schema $schema
   */
  public function up(Schema $schema): void
  {
    $this->connection->beginTransaction();

    // find stdin-file pipelines which will be replacement for files-file pipelines
    $this->processPipeline(self::STDIN_FILE_PIPE, self::FILES_FILE_PIPE, "[outfile]");
    // find stdin-stdout pipelines which will be replacement for files-stdout pipelines
    $this->processPipeline(self::STDIN_STDOUT_PIPE, self::FILES_STDOUT_PIPE, "[stdout]");

    // go through all exercise configs and search for the ones which should be changed
    $configResult = $this->connection->executeQuery("SELECT * FROM exercise_config");
    foreach ($configResult as $configRow) {
      $this->processConfigs($configRow);
    }

    // all replaced pipelines should be deleted
    foreach ($this->replacedPipelines as $id => $ignored) {
      $this->connection->executeQuery("DELETE FROM pipeline WHERE id = '{$id}'");
    }

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
