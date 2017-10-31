<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

class Version20171028111634 extends AbstractMigration {
  /**
   * @param Schema $schema
   */
  public function up(Schema $schema) {
    $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    $this->addSql("RENAME TABLE localized_text TO localized_exercise");
    $this->addSql("RENAME TABLE exercise_localized_text TO exercise_localized_exercise");
    $this->addSql("RENAME TABLE assignment_localized_text TO assignment_localized_exercise");
  }

  /**
   * @param Schema $schema
   */
  public function down(Schema $schema) {
    $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    $this->addSql("RENAME TABLE localized_exercise TO localized_text ");
    $this->addSql("RENAME TABLE exercise_localized_exercise TO exercise_localized_text");
    $this->addSql("RENAME TABLE assignment_localized_exercise TO assignment_localized_text");
  }
}
