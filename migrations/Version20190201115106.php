<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Migrate entire database to utf8mb4.
 */
class Version20190201115106 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $tables = $this->connection->executeQuery(
          "SELECT DISTINCT table_name FROM `information_schema`.`tables` WHERE `table_schema` = :db",
          ["db" => $this->connection->getDatabase()])->fetchAll(\PDO::FETCH_COLUMN);

        $this->addSql('SET FOREIGN_KEY_CHECKS=0');
        foreach ($tables as $table) {
          $this->addSql("ALTER TABLE `$table` CONVERT TO CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }
        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }

    /**
     * @param Schema $schema
     * @throws \Doctrine\DBAL\Migrations\IrreversibleMigrationException
     */
    public function down(Schema $schema)
    {
      $this->throwIrreversibleMigrationException();
    }
}
