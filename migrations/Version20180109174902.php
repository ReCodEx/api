<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Yaml\Yaml;

/**
 * Added python compilation, refactor whole pipeline and also python execution.
 */
class Version20180109174902 extends AbstractMigration
{
  public static $FILE_TYPE = "file";
  public static $FILES_TYPE = "file[]";
  public static $REMOTE_FILES_TYPE = "remote-file[]";
  public static $FILE_IN_TYPE = "file-in";
  public static $FILES_IN_TYPE = "files-in";
  public static $FILE_OUT_TYPE = "file-out";
  public static $SOURCES_BOX_NAME = "sources";
  public static $SOURCE_FILE_PORT = "source-file";
  public static $EXTRAS_BOX_NAME = "extras";
  public static $EXTRA_FILES_PORT = "extra-files";
  public static $EXTRA_FILE_NAMES_PORT = "extra-file-names";
  public static $EXTRA_FILE_NAMES_REF = "\$extra-file-names";
  public static $PYC_FILE_PORT = "pyc-file";
  public static $PYTHON_COMPILATION_BOX_TYPE = "python3c";

  private static $PYTHON_RUN_PIPELINES = ["Python execution & evaluation [stdout]", "Python execution & evaluation [outfile]"];
  private static $PYTHON_COMPILATION_PIPELINE = "Python pass-through compilation";
  private static $NEW_PYTHON_COMPILATION_PIPELINE = "Python compilation";

  /**
   * Add whole new python compilation pipeline.
   * @throws \Doctrine\DBAL\DBALException
   * @return string[]
   */
  private function updatePythonCompilationPipeline(): array {
    $pipeline = $this->connection->executeQuery("SELECT * FROM pipeline WHERE name = :name", ["name" => self::$PYTHON_COMPILATION_PIPELINE])->fetch();

    $config = [
      "boxes" => [
        [
          "name" => self::$SOURCES_BOX_NAME,
          "type" => self::$FILE_IN_TYPE,
          "portsIn" => [],
          "portsOut" => [
            "input" => [
              "type" => self::$FILE_TYPE,
              "value" => self::$SOURCE_FILE_PORT
            ]
          ]
        ],
        [
          "name" => self::$EXTRAS_BOX_NAME,
          "type" => self::$FILES_IN_TYPE,
          "portsIn" => [],
          "portsOut" => [
            "input" => [
              "type" => self::$FILES_TYPE,
              "value" => self::$EXTRA_FILES_PORT
            ]
          ]
        ],
        [
          "name" => self::$PYTHON_COMPILATION_BOX_TYPE,
          "type" => self::$PYTHON_COMPILATION_BOX_TYPE,
          "portsIn" => [
            self::$SOURCE_FILE_PORT => [
              "type" => self::$FILE_TYPE,
              "value" => self::$SOURCE_FILE_PORT
            ],
            self::$EXTRA_FILES_PORT => [
              "type" => self::$FILES_TYPE,
              "value" => self::$EXTRA_FILES_PORT
            ]
          ],
          "portsOut" => [
            self::$PYC_FILE_PORT => [
              "type" => self::$FILE_TYPE,
              "value" => self::$PYC_FILE_PORT
            ]
          ]
        ],
        [
          "name" => self::$PYC_FILE_PORT,
          "type" => self::$FILE_OUT_TYPE,
          "portsIn" => [
            "output" => [
              "type" => self::$FILE_TYPE,
              "value" => self::$PYC_FILE_PORT
            ]
          ],
          "portsOut" => []
        ]
      ],
      "variables" => [
        [
          "name" => self::$SOURCE_FILE_PORT,
          "type" => self::$FILE_TYPE,
          "value" => ""
        ],
        [
          "name" => self::$EXTRA_FILES_PORT,
          "type" => self::$FILES_TYPE,
          "value" => self::$EXTRA_FILE_NAMES_REF
        ],
        [
          "name" => self::$PYC_FILE_PORT,
          "type" => self::$FILE_TYPE,
          "value" => ""
        ]
      ]
    ];

    $this->connection->executeQuery("UPDATE pipeline SET name = :name WHERE id = :id",
      ["id" => $pipeline["id"], "name" => self::$NEW_PYTHON_COMPILATION_PIPELINE]);
    $this->connection->executeQuery("UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id",
      ["id" => $pipeline["pipeline_config_id"], "config" => Yaml::dump($config)]);

    return [$pipeline["id"]];
  }

  /**
   * Update python run pipelines.
   * @throws \Doctrine\DBAL\DBALException
   * @return string[]
   */
  private function updatePythonRunPipelines(): array {
    $pipelines = [];
    foreach (self::$PYTHON_RUN_PIPELINES as $name) {
      $pipeline = $this->connection->executeQuery("SELECT * FROM pipeline WHERE name = :name", ["name" => $name])->fetch();
      $pipelines[] = $pipeline["id"];

      $pipelineConfig = $this->connection->executeQuery("SELECT * FROM pipeline_config WHERE id = '{$pipeline["pipeline_config_id"]}'")->fetch();
      $config = Yaml::parse($pipelineConfig["pipeline_config"]);

      foreach ($config["boxes"] as &$box) {
        foreach ($box["portsIn"] as $portName => $portIn) {
          if ($portIn["value"] == self::$SOURCE_FILE_PORT) {
            unset($box["portsIn"][$portName]);
            $box["portsIn"][self::$PYC_FILE_PORT] = [
              "type" => self::$FILE_TYPE,
              "value" => self::$PYC_FILE_PORT
            ];
          }
        }

        foreach ($box["portsOut"] as $portName => &$portOut) {
          if ($portOut["value"] == self::$SOURCE_FILE_PORT) {
            $portOut["value"] = self::$PYC_FILE_PORT;
          }
        }
      }

      foreach ($config["variables"] as &$variable) {
        if ($variable["name"] == self::$SOURCE_FILE_PORT) {
          $variable["name"] = self::$PYC_FILE_PORT;
          $variable["value"] = "";
        }
      }

      $this->connection->executeQuery("UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id",
        ["id" => $pipelineConfig["id"], "config" => Yaml::dump($config)]);
    }

    return $pipelines;
  }

  /**
   * Name of the input file was changes. Propagate this change into exercise configs.
   * @param string[] $compilationPipelines
   * @param string[] $runPipelines
   * @throws \Doctrine\DBAL\DBALException
   */
  private function updatePythonRunConfigs(array $compilationPipelines, array $runPipelines) {
    $configsResult = $this->connection->executeQuery("SELECT * FROM exercise_config");
    foreach ($configsResult as $exerciseConfig) {
      $config = Yaml::parse($exerciseConfig["config"]);

      $pipelineFound = false;
      foreach ($config["tests"] as &$test) {
        foreach ($test["environments"] as &$env) {
          foreach ($env["pipelines"] as &$pipeline) {
            if (in_array($pipeline["name"], $compilationPipelines)) {
              $pipelineFound = true;
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

      if ($pipelineFound) {
        $this->connection->executeQuery("UPDATE exercise_config SET config = :config WHERE id = :id",
          ["id" => $exerciseConfig["id"], "config" => Yaml::dump($config)]);
      }
    }
  }

  /**
   * @param Schema $schema
   * @throws \Doctrine\DBAL\ConnectionException
   * @throws \Doctrine\DBAL\DBALException
   */
  public function up(Schema $schema) {
    $this->connection->beginTransaction();
    $compilationUpdated = $this->updatePythonCompilationPipeline();
    $runUpdated = $this->updatePythonRunPipelines();
    $this->updatePythonRunConfigs($compilationUpdated, $runUpdated);
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
