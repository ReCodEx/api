<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Yaml\Yaml;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171130144517 extends AbstractMigration
{
  const REMOTE_FILE = "remote-file";
  const REMOTE_FILES = "remote-file[]";

  /**
   * Create array indexed by hashes of files and containing file names.
   * @param $files
   * @return array
   */
  private function createFilesArray($files): array {
    $result = [];
    foreach ($files as $file) {
      $result[$file["hash_name"]] = $file["name"];
    }
    return $result;
  }

  /**
   * Update given variables table's files.
   * @param $varTable
   * @param array $files
   */
  private function updateVariables(&$varTable, array $files) {
    // go through variables and try to find remote files types
    foreach ($varTable as &$variable) {
      if ($variable["type"] != self::REMOTE_FILE &&
        $variable["type"] != self::REMOTE_FILES) {
        continue;
      }

      if (is_scalar($variable["value"])) {
        // scalar variable type
        if (array_key_exists($variable["value"], $files)) {
          $variable["value"] = $files[$variable["value"]];
        }
      } else {
        // array variable type
        foreach ($variable["value"] as &$varValue) {
          if (array_key_exists($varValue, $files)) {
            $varValue = $files[$varValue];
          }
        }
      }
    }
  }

  /**
   * Update given configuration files from hashes to names.
   * @param $config
   * @param array $files
   */
  private function updateExerciseConfig(&$config, array $files) {
    foreach ($config["tests"] as &$test) {
      foreach ($test["environments"] as &$env) {

        $pipelines = &$env["pipelines"];
        if (empty($pipelines)) {
          continue;
        }

        // go through pipelines
        foreach ($pipelines as &$pipeline) {
          $this->updateVariables($pipeline["variables"], $files);
        }
      }
    }
  }

  /**
   * Update given pipeline configuration, files should be identified by names and not hashes.
   * @param $config
   * @param array $files
   */
  private function updatePipelineConfig(&$config, array $files) {
    if (empty($config["variables"])) {
      return;
    }

    $this->updateVariables($config["variables"], $files);
  }

  private function updateExercises($exerciseType) {
    $exercises = $this->connection->executeQuery("SELECT * FROM $exerciseType");
    foreach ($exercises as $exercise) {
      $id = $exercise["exercise_config_id"];
      $exerciseConfig = $this->connection->executeQuery("SELECT * FROM exercise_config WHERE id = '{$id}'")->fetch();
      $config = Yaml::parse($exerciseConfig["config"]);

      // load files and make them associative array suitable for searching
      $filesResult = $this->connection->executeQuery("SELECT * FROM uploaded_file " .
        "INNER JOIN {$exerciseType}_supplementary_exercise_file AS esef ON esef.supplementary_exercise_file_id = id " .
        "WHERE {$exerciseType}_id = '{$exercise["id"]}'");
      $files = $this->createFilesArray($filesResult);

      // update config
      $this->updateExerciseConfig($config, $files);

      $this->connection->executeQuery("UPDATE exercise_config SET config = :config WHERE id = :id",
        ["id" => $id, "config" => Yaml::dump($config)]);
    }
  }

  private function updatePipelines() {
    $pipelines = $this->connection->executeQuery("SELECT * FROM pipeline");
    foreach ($pipelines as $pipeline) {
      $id = $pipeline["pipeline_config_id"];
      $pipelineConfig = $this->connection->executeQuery("SELECT * FROM pipeline_config WHERE id = '{$id}'")->fetch();
      $config = Yaml::parse($pipelineConfig["pipeline_config"]);

      // load files and make them associative array suitable for searching
      $filesResult = $this->connection->executeQuery("SELECT * FROM uploaded_file " .
        "INNER JOIN pipeline_supplementary_exercise_file AS psef ON psef.supplementary_exercise_file_id = id " .
        "WHERE pipeline_id = '{$pipeline["id"]}'");
      $files = $this->createFilesArray($filesResult);
      
      // update config
      $this->updatePipelineConfig($config, $files);

      $this->connection->executeQuery("UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id",
        ["id" => $id, "config" => Yaml::dump($config)]);
    }
  }

  /**
   * @param Schema $schema
   */
  public function up(Schema $schema): void
  {
    $this->connection->beginTransaction();
    $this->updateExercises("exercise");
    $this->updateExercises("assignment");
    $this->updatePipelines();
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
