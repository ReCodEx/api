<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171109163345 extends AbstractMigration
{
  /**
   * @param Schema $schema
   */
  public function up(Schema $schema)
  {
    // this up() migration is auto-generated, please modify it to your needs
    $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    $this->addSql('CREATE TABLE assignment_solution_submission (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', assignment_solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', evaluation_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', submitted_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', submitted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', results_url VARCHAR(255) DEFAULT NULL, job_config_path VARCHAR(255) NOT NULL, INDEX IDX_114838A3A598DA2 (assignment_solution_id), UNIQUE INDEX UNIQ_114838A3456C5646 (evaluation_id), INDEX IDX_114838A379F7D87D (submitted_by_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
    $this->addSql('ALTER TABLE assignment_solution_submission ADD CONSTRAINT FK_114838A3A598DA2 FOREIGN KEY (assignment_solution_id) REFERENCES assignment_solution (id)');
    $this->addSql('ALTER TABLE assignment_solution_submission ADD CONSTRAINT FK_114838A3456C5646 FOREIGN KEY (evaluation_id) REFERENCES solution_evaluation (id)');
    $this->addSql('ALTER TABLE assignment_solution_submission ADD CONSTRAINT FK_114838A379F7D87D FOREIGN KEY (submitted_by_id) REFERENCES user (id)');
    $this->addSql('ALTER TABLE submission_failure ADD assignment_solution_submission_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
    $this->addSql('ALTER TABLE submission_failure ADD CONSTRAINT FK_D7A9817D2E75341 FOREIGN KEY (assignment_solution_submission_id) REFERENCES assignment_solution_submission (id)');
    $this->addSql('CREATE INDEX IDX_D7A9817D2E75341 ON submission_failure (assignment_solution_submission_id)');
  }

  public function postUp(Schema $schema) {
    $this->connection->beginTransaction();

    // copy all data from assignment solution entities to assignment solution submission
    $result = $this->connection->executeQuery('SELECT * FROM assignment_solution');
    foreach ($result as $row) {
      $evaluation = $row["evaluation_id"] ? "'{$row["evaluation_id"]}'" : "NULL";

      $this->connection->executeQuery("INSERT INTO assignment_solution_submission " .
        "(id, evaluation_id, assignment_solution_id, submitted_at, submitted_by_id, results_url, job_config_path) " .
        "VALUES (UUID(), {$evaluation}, '{$row["id"]}', '{$row["submitted_at"]}', '{$row["submitted_by_id"]}', '{$row["results_url"]}', '{$row["job_config_path"]}')");
    }

    // move submission failures
    $result = $this->connection->executeQuery('SELECT * FROM submission_failure WHERE assignment_solution_id IS NOT NULL');
    foreach ($result as $row) {
      $submissionId = $this->connection->executeQuery("SELECT * FROM assignment_solution_submission WHERE assignment_solution_id = '{$row["assignment_solution_id"]}'")->fetchColumn(0);
      $this->connection->executeQuery("UPDATE submission_failure SET assignment_solution_submission_id = '{$submissionId}', assignment_solution_id = NULL WHERE assignment_solution_id = '{$row['assignment_solution_id']}'");
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
