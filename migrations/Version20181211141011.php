<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20181211141011 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE task_result');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE task_result (id CHAR(36) NOT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:guid)\', test_result_id INT DEFAULT NULL, task_name VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, used_wall_time DOUBLE PRECISION NOT NULL, used_memory INT NOT NULL, output LONGTEXT NOT NULL COLLATE utf8_unicode_ci, used_cpu_time DOUBLE PRECISION NOT NULL, INDEX IDX_28C345C0853A2189 (test_result_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE task_result ADD CONSTRAINT FK_28C345C0853A2189 FOREIGN KEY (test_result_id) REFERENCES test_result (id)');
    }
}
