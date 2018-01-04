<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180104224609 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE task_result ADD used_cpu_time DOUBLE PRECISION NOT NULL, CHANGE used_time used_wall_time DOUBLE PRECISION NOT NULL');
        $this->addSql('ALTER TABLE test_result CHANGE used_time_ratio used_wall_time_ratio DOUBLE PRECISION NOT NULL, CHANGE used_time used_wall_time DOUBLE PRECISION NOT NULL, CHANGE time_exceeded wall_time_exceeded TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', ADD cpu_time_exceeded TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', ADD used_cpu_time_ratio DOUBLE PRECISION NOT NULL, ADD used_cpu_time DOUBLE PRECISION NOT NULL');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE task_result CHANGE used_wall_time used_time DOUBLE PRECISION NOT NULL, DROP used_cpu_time');
        $this->addSql('ALTER TABLE test_result CHANGE wall_time_exceeded time_exceeded TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', CHANGE used_wall_time_ratio used_time_ratio DOUBLE PRECISION NOT NULL, CHANGE used_wall_time used_time DOUBLE PRECISION NOT NULL, DROP cpu_time_exceeded, DROP used_cpu_time_ratio, DROP used_cpu_time');
    }
}
