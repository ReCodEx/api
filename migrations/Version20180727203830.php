<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Changing pipelines-exercises relation from many-to-one to many-to-many.
 */
class Version20180727203830 extends AbstractMigration
{
  private $pipelines = [];

  /**
   * @param Schema $schema
   */
  public function preUp(Schema $schema)
  {
    $this->pipelines = $this->connection->executeQuery("SELECT id, exercise_id FROM pipeline WHERE exercise_id IS NOT NULL")->fetchAll();
  }

  /**
   * @param Schema $schema
   */
  public function up(Schema $schema)
  {
    // this up() migration is auto-generated, please modify it to your needs
    $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    $this->addSql('CREATE TABLE exercise_pipeline (exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', pipeline_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\',
      INDEX IDX_7B907931E934951A (exercise_id), INDEX IDX_7B907931E80B93 (pipeline_id),
      PRIMARY KEY(exercise_id, pipeline_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
    $this->addSql('ALTER TABLE exercise_pipeline ADD CONSTRAINT FK_7B907931E934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE');
    $this->addSql('ALTER TABLE exercise_pipeline ADD CONSTRAINT FK_7B907931E80B93 FOREIGN KEY (pipeline_id) REFERENCES pipeline (id) ON DELETE CASCADE');

    $this->addSql('ALTER TABLE pipeline DROP FOREIGN KEY FK_7DFCD9D9E934951A');
    $this->addSql('DROP INDEX IDX_7DFCD9D9E934951A ON pipeline');
    $this->addSql('ALTER TABLE pipeline DROP exercise_id');
  }

  /**
   * @param Schema $schema
   */
  public function postUp(Schema $schema)
  {
    foreach ($this->pipelines as $pipeline) {
      $this->connection->executeQuery("INSERT INTO exercise_pipeline (pipeline_id, exercise_id) VALUES (:pid, :eid)",
        [ "pid" => $pipeline["id"], "eid" => $pipeline['exercise_id'] ]);
    }
  }

  /**
   * @param Schema $schema
   */
  public function preDown(Schema $schema)
  {
    $this->pipelines = $this->connection->executeQuery("SELECT p.id AS pid, ep.exercise_id AS eid FROM pipeline AS p
      JOIN exercise_pipeline AS ep ON p.id = ep.pipeline_id")
      ->fetchAll();
  }
  /**
   * @param Schema $schema
   */
  public function down(Schema $schema)
  {
    // this down() migration is auto-generated, please modify it to your needs
    $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
    $maxExercises = $this->connection->executeQuery("SELECT MAX(tmp.exercises_count) FROM
      (SELECT pipeline_id, COUNT(exercise_id) AS exercises_count FROM exercise_pipeline GROUP BY pipeline_id) AS tmp")->fetchColumn();
    $this->abortIf($maxExercises > 1, 'Migraction can only be executed safely when every pipeline is attached to at most one exercise (so many-to-many relation may be reduced to one-to-many).');

    $this->addSql('DROP TABLE exercise_pipeline');
    $this->addSql('ALTER TABLE pipeline ADD exercise_id CHAR(36) DEFAULT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:guid)\'');
    $this->addSql('ALTER TABLE pipeline ADD CONSTRAINT FK_7DFCD9D9E934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id)');
    $this->addSql('CREATE INDEX IDX_7DFCD9D9E934951A ON pipeline (exercise_id)');
  }

  /**
   * @param Schema $schema
   */
  public function postDown(Schema $schema)
  {
    foreach ($this->pipelines as $pipeline) {
      $this->connection->executeQuery("UPDATE pipeline SET exercise_id = :eid WHERE id = :pid",
        [ "pid" => $pipeline["pid"], "eid" => $pipeline['eid'] ]);
    }
  }

}
