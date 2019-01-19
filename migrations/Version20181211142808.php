<?php

namespace Migrations;

use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Migrations\AbortMigrationException;
use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20181211142808 extends AbstractMigration
{
  /**
   * @param Schema $schema
   * @throws AbortMigrationException
   */
  public function up(Schema $schema)
  {
    // this up() migration is auto-generated, please modify it to your needs
    $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    $this->addSql('ALTER TABLE test_result ADD used_memory_limit INT NOT NULL, ADD used_wall_time_limit DOUBLE PRECISION NOT NULL, ADD used_cpu_time_limit DOUBLE PRECISION NOT NULL');
  }

  /**
   * @param Schema $schema
   * @throws DBALException
   */
  public function postUp(Schema $schema)
  {
    // compute limits from used memory and time using stored ratios
    $this->connection->executeQuery("UPDATE test_result
      SET used_memory_limit = LEAST(used_memory / GREATEST(used_memory_ratio, 0.00001), 4000008),
          used_wall_time_limit = ROUND(used_wall_time / GREATEST(used_wall_time_ratio, 0.00001), 3),
          used_cpu_time_limit = ROUND(used_cpu_time / GREATEST(used_cpu_time_ratio, 0.00001), 3)");
  }

  /**
   * @param Schema $schema
   * @throws AbortMigrationException
   */
  public function down(Schema $schema)
  {
    // this down() migration is auto-generated, please modify it to your needs
    $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    $this->addSql('ALTER TABLE test_result DROP used_memory_limit, DROP used_wall_time_limit, DROP used_cpu_time_limit');
  }
}
