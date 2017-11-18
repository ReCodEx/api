<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * No ORM changes, migrates only pipelines and exercise config data within
 * database.
 */
class Version20171118220217 extends AbstractMigration
{
  /**
   * @param Schema $schema
   */
  public function up(Schema $schema)
  {
    $this->connection->beginTransaction();
    $stdinPipelines = [];
    $replacedPipelines = [];

    // find stdin-file pipelines which will be replacement for files-file pipelines
    $stdinFileResult = $this->connection->executeQuery("SELECT * FROM pipelines WHERE name LIKE '%stdin-file%'");
    foreach ($stdinFileResult as $stdinFile) {
      // change input-file variable name to stdin-file
      // TODO
      // add current pipeline to array of ids for which variable name should be changed
      $stdinPipelines[] = $stdinFile["id"];

      // construct files-file pipeline name
      $stdinName = $stdinFile["name"];
      $filesName = str_replace("stdin-file", "files-file", $stdinName);

      // find files-file pipelines which correspond to stdin-file pipeline
      $filesFileResult = $this->connection->executeQuery("SELECT * FROM pipelines WHERE name = '$filesName'");
      foreach ($filesFileResult as $filesFile) {
        // add id to pipelines which should be replaced by the stdin-file one
        // TODO
      }
    }

    // find stdin-stdout pipelines which will be replacement for files-stdout pipelines
    $stdinStdoutResult = $this->connection->executeQuery("SELECT * FROM pipelines WHERE name LIKE '%stdin-stdout%'");
    foreach ($stdinStdoutResult as $stdinStdout) {
      // change input-file variable name to stdin-file
      // TODO
      // add current pipeline to array of ids for which variable name should be changed
      $stdinPipelines[] = $stdinStdout["id"];

      // construct files-stdout pipeline name
      $stdinName = $stdinStdout["name"];
      $filesName = str_replace("stdin-stdout", "files-stdout", $stdinName);

      // find files-stdout pipelines which correspond to stdin-stdout pipeline
      $filesStdoutResult = $this->connection->executeQuery("SELECT * FROM pipelines WHERE name = '$filesName'");
      foreach ($filesStdoutResult as $filesStdout) {
        // add id to pipelines which should be replaced by the stdin-stdout one
        // TODO
      }
    }

    // go through all exercise configs and search for the ones which should be changed
    $configResult = $this->connection->executeQuery("SELECT * FROM exercise_config");
    foreach ($configResult as $configRow) {
      // process stdin pipelines and change input variable name and add input files variable
      // TODO

      // process files pipelines which has to be replaced and stdin variable added
      // TODO
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
