<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171109102227 extends AbstractMigration
{
  /**
   * @param Schema $schema
   */
  public function up(Schema $schema): void
  {
    // this up() migration is auto-generated, please modify it to your needs
    $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    $this->addSql('RENAME TABLE reference_solution_evaluation TO reference_solution_submission');
    $this->addSql('RENAME TABLE submission TO assignment_solution');

    $this->addSql('ALTER TABLE submission_failure DROP FOREIGN KEY FK_D7A981711DCC77F');
    $this->addSql('ALTER TABLE submission_failure DROP FOREIGN KEY FK_D7A9817E1FD4933');
    $this->addSql('DROP INDEX IDX_D7A9817E1FD4933 ON submission_failure');
    $this->addSql('DROP INDEX IDX_D7A981711DCC77F ON submission_failure');
    $this->addSql('ALTER TABLE submission_failure CHANGE submission_id assignment_solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE reference_solution_evaluation_id reference_solution_submission_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
    $this->addSql('ALTER TABLE submission_failure ADD CONSTRAINT FK_D7A9817A598DA2 FOREIGN KEY (assignment_solution_id) REFERENCES assignment_solution (id)');
    $this->addSql('ALTER TABLE submission_failure ADD CONSTRAINT FK_D7A9817B54DD80A FOREIGN KEY (reference_solution_submission_id) REFERENCES reference_solution_submission (id)');
    $this->addSql('CREATE INDEX IDX_D7A9817A598DA2 ON submission_failure (assignment_solution_id)');
    $this->addSql('CREATE INDEX IDX_D7A9817B54DD80A ON submission_failure (reference_solution_submission_id)');
  }

  /**
   * @param Schema $schema
   */
  public function down(Schema $schema): void
  {
    $this->throwIrreversibleMigrationException();
  }
}
