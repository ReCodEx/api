<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbortMigrationException;
use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Migrations\IrreversibleMigrationException;
use Doctrine\DBAL\Schema\Schema;

/**
 * User can belong to multiple instances.
 */
class Version20180420180256 extends AbstractMigration
{
  /**
   * @var string[]
   */
  private $userInstance = [];

  public function preUp(Schema $schema) {
    $result = $this->connection->executeQuery("SELECT id, instance_id FROM user");
    foreach ($result as $row) {
      $this->userInstance[] = "('{$row["id"]}', '{$row["instance_id"]}')";
    }
  }

  /**
   * @param Schema $schema
   * @throws AbortMigrationException
   */
  public function up(Schema $schema) {
    // this up() migration is auto-generated, please modify it to your needs
    $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    $this->addSql('CREATE TABLE user_instance (user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', instance_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_A2BD55DEA76ED395 (user_id), INDEX IDX_A2BD55DE3A51721D (instance_id), PRIMARY KEY(user_id, instance_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
    $this->addSql('ALTER TABLE user_instance ADD CONSTRAINT FK_A2BD55DEA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
    $this->addSql('ALTER TABLE user_instance ADD CONSTRAINT FK_A2BD55DE3A51721D FOREIGN KEY (instance_id) REFERENCES instance (id) ON DELETE CASCADE');
    $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D6493A51721D');
    $this->addSql('DROP INDEX IDX_8D93D6493A51721D ON user');
    $this->addSql('ALTER TABLE user DROP instance_id');
  }

  public function postUp(Schema $schema) {
    if (empty($this->userInstance)) {
      return;
    }

    $this->connection->executeQuery("INSERT INTO user_instance (user_id, instance_id) VALUES " . implode(', ', $this->userInstance));
  }

  /**
   * @param Schema $schema
   * @throws IrreversibleMigrationException
   */
  public function down(Schema $schema) {
    $this->throwIrreversibleMigrationException();
  }
}
