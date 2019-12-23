<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use App\Helpers\Yaml;

/**
 * Pascal compilation can accept multiple files. Execute appropriate migrations.
 */
class Version20171230103913 extends AbstractMigration
{
  const SOURCE_FILE = "source-file";
  const SOURCE_FILES = "source-files";
  const FILES_TYPE = "file[]";
  const FPC_TYPE = "fpc";
  const FILE_IN = "file-in";
  const FILES_IN = "files-in";
  const PASCAL_SOURCE_FILES = "*.{pas,lpr}";

  /**
   * @throws \Doctrine\DBAL\DBALException
   */
  private function updateEnvironment() {
    $defVars = "- name: \"source-files\"\n  type: \"file[]\"\n  value: \"*.{pas,lpr}\"";
    $this->connection->executeQuery("UPDATE runtime_environment SET default_variables = :config WHERE id = :id",
      ["config" => $defVars, "id" => "freepascal-linux"]);
  }

  /**
   * @throws \Doctrine\DBAL\DBALException
   */
  private function updatePipeline() {
    $pipeline = $this->connection->executeQuery("SELECT * FROM pipeline WHERE name = 'FreePascal Compilation'")->fetch();
    $pipelineConfig = $this->connection->executeQuery("SELECT * FROM pipeline_config WHERE id = '{$pipeline["pipeline_config_id"]}'")->fetch();
    $config = Yaml::parse($pipelineConfig["pipeline_config"]);

    foreach ($config["variables"] as &$variable) {
      if ($variable["name"] == self::SOURCE_FILE) {
        $variable["name"] = self::SOURCE_FILES;
        $variable["type"] = self::FILES_TYPE;
      }
    }

    foreach ($config["boxes"] as &$box) {
      if ($box["type"] == self::FPC_TYPE) {
        unset($box["portsIn"][self::SOURCE_FILE]);
        $box["portsIn"][self::SOURCE_FILES] = [
          "type" => self::FILES_TYPE,
          "value" => self::SOURCE_FILES
        ];
      } else if ($box["type"] == self::FILE_IN) {
        $box["name"] = "sources";
        $box["type"] = self::FILES_IN;
        $box["portsOut"]["input"] = [
          "type" => self::FILES_TYPE,
          "value" => self::SOURCE_FILES
        ];
      }
    }

    $this->connection->executeQuery("UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id",
      ["config" => Yaml::dump($config), "id" => $pipelineConfig["id"]]);
  }

  /**
   * @throws \Doctrine\DBAL\DBALException
   */
  private function updateExerciseEnvironmentConfigs() {
    $exerciseEnvironmentsResult = $this->connection->executeQuery("SELECT * FROM exercise_environment_config");
    foreach ($exerciseEnvironmentsResult as $exerciseEnvironment) {
      $envConfig = Yaml::parse($exerciseEnvironment["variables_table"]);

      foreach ($envConfig as &$variable) {
        if ($variable["value"] == self::PASCAL_SOURCE_FILES) {
          $variable["name"] = self::SOURCE_FILES;
          $variable["type"] = self::FILES_TYPE;
        }
      }

      $this->connection->executeQuery("UPDATE exercise_environment_config SET variables_table = :config WHERE id = :id",
        ["config" => Yaml::dump($envConfig), "id" => $exerciseEnvironment["id"]]);
    }
  }

  /**
   * @param Schema $schema
   * @throws \Doctrine\DBAL\ConnectionException
   * @throws \Doctrine\DBAL\DBALException
   */
    public function up(Schema $schema): void {
        $this->connection->beginTransaction();

        $this->updateEnvironment();
        $this->updatePipeline();
        $this->updateExerciseEnvironmentConfigs();

        $this->connection->commit();
    }

  /**
   * @param Schema $schema
   */
    public function down(Schema $schema): void {
        $this->throwIrreversibleMigrationException();
    }
}
