<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180822182100 extends AbstractMigration
{
  /**
   * @param Schema $schema
   */
  public function up(Schema $schema): void
  {
      // this up() migration is auto-generated, please modify it to your needs
      $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
      $this->addSql('UPDATE pipeline SET author_id = NULL');
  }

  /**
   * @param Schema $schema
   */
  public function down(Schema $schema): void
  {
    $this->throwIrreversibleMigrationException();
  }
}
