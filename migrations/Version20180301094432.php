<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180301094432 extends AbstractMigration
{

  /**
   * @param Schema $schema
   */
  public function up(Schema $schema)
  {
    // this up() migration is auto-generated, please modify it to your needs
    $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    $this->addSql('ALTER TABLE uploaded_file DROP FOREIGN KEY FK_B40DF75D1C0BE183');
    $this->addSql('ALTER TABLE uploaded_file ADD CONSTRAINT FK_B40DF75D1C0BE183 FOREIGN KEY (solution_id) REFERENCES solution (id) ON DELETE SET NULL');
  }

  /**
   * @param Schema $schema
   */
  public function postUp(Schema $schema) {
    // setup database cascades
    $this->connection->executeQuery('ALTER TABLE reference_exercise_solution DROP FOREIGN KEY FK_E414ABAB1C0BE183');
    $this->connection->executeQuery('ALTER TABLE reference_exercise_solution ADD CONSTRAINT FK_E414ABAB1C0BE183 FOREIGN KEY (solution_id) REFERENCES solution (id) ON DELETE CASCADE');
    $this->connection->executeQuery('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_62BA741F456C5646');
    $this->connection->executeQuery('ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_AA9C8B99456C5646 FOREIGN KEY (evaluation_id) REFERENCES solution_evaluation (id) ON DELETE CASCADE');
    $this->connection->executeQuery('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_62BA741FFA3CA3B7');
    $this->connection->executeQuery('ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_62BA741FFA3CA3B7 FOREIGN KEY (reference_solution_id) REFERENCES reference_exercise_solution (id) ON DELETE CASCADE');

    // without any shame, lets delete all soft-deleted reference solutions
    $this->connection->executeQuery("DELETE FROM reference_exercise_solution WHERE deleted_at IS NOT NULL");

    // delete database cascades
    $this->connection->executeQuery('ALTER TABLE reference_exercise_solution DROP FOREIGN KEY FK_E414ABAB1C0BE183');
    $this->connection->executeQuery('ALTER TABLE reference_exercise_solution ADD CONSTRAINT FK_E414ABAB1C0BE183 FOREIGN KEY (solution_id) REFERENCES solution (id)');
    $this->connection->executeQuery('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_AA9C8B99456C5646');
    $this->connection->executeQuery('ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_62BA741F456C5646 FOREIGN KEY (evaluation_id) REFERENCES solution_evaluation (id)');
    $this->connection->executeQuery('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_62BA741FFA3CA3B7');
    $this->connection->executeQuery('ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_62BA741FFA3CA3B7 FOREIGN KEY (reference_solution_id) REFERENCES reference_exercise_solution (id)');
  }

  /**
   * @param Schema $schema
   */
  public function down(Schema $schema)
  {
    // this down() migration is auto-generated, please modify it to your needs
    $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    $this->addSql('ALTER TABLE `uploaded_file` DROP FOREIGN KEY FK_B40DF75D1C0BE183');
    $this->addSql('ALTER TABLE `uploaded_file` ADD CONSTRAINT FK_B40DF75D1C0BE183 FOREIGN KEY (solution_id) REFERENCES solution (id)');
  }
}
