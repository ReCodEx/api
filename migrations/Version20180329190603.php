<?php

namespace Migrations;

use Doctrine\DBAL\DBALException;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Yaml\Yaml;

/**
 * Extra files in Node and PHP environments have to be plugged in to execution
 * boxes, otherwise it will not be downloaded and copied over to test folder.
 */
class Version20180329190603 extends AbstractMigration
{
  private static $EXTRA_FILES_BOXES = ["php", "node"];
  private static $EXTRA_FILES_PORT = "extra-files";
  private static $FILES_TYPE = "file[]";
  private static $EXTRAS_BOX_NAME = "extras";
  private static $FILES_IN_TYPE = "files-in";

  /**
   * @return string[]
   * @throws DBALException
   */
  private function getExtraFilesRelevantPipelineConfigsIds(): array {
    $pipelinesNames = [
      "PHP execution & evaluation [stdout]",
      "PHP execution & evaluation [outfile]",
      "Node.js execution & evaluation [stdout]",
      "Node.js execution & evaluation [outfile]",
    ];

    $pipelines = [];
    foreach ($pipelinesNames as $name) {
      $pipelines[] = $this->connection->executeQuery("SELECT * FROM pipeline WHERE name = :name", ["name" => $name])->fetch()["pipeline_config_id"];
    }

    return $pipelines;
  }

  /**
   * @param Schema $schema
   * @throws DBALException
   */
  public function up(Schema $schema): void {
    foreach ($this->getExtraFilesRelevantPipelineConfigsIds() as $configId) {
      $pipelineConfig = $this->connection->executeQuery("SELECT * FROM pipeline_config WHERE id = :id", ["id" => $configId])->fetch();
      $config = Yaml::parse($pipelineConfig["pipeline_config"]);

      foreach ($config["boxes"] as &$box) {
        if (!in_array($box["type"], self::$EXTRA_FILES_BOXES)) {
          continue;
        }

        $box["portsIn"][self::$EXTRA_FILES_PORT] = [
          "type" => self::$FILES_TYPE,
          "value" => self::$EXTRA_FILES_PORT
        ];
      }

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

      $config["variables"][] = [
        "name" => self::$EXTRA_FILES_PORT,
        "type" => self::$FILES_TYPE,
        "value" => []
      ];

      $this->connection->executeQuery("UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id",
        ["id" => $pipelineConfig["id"], "config" => Yaml::dump($config)]);
    }
  }

  /**
   * @param Schema $schema
   */
  public function down(Schema $schema): void {
    $this->throwIrreversibleMigrationException();
  }
}
