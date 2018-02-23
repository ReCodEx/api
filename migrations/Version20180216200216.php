<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180216200216 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE hardware_group ADD name VARCHAR(255) NOT NULL');
        $this->addSql('DROP TABLE hardware_group_availability_log');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE hardware_group DROP name');
        $this->addSql('CREATE TABLE hardware_group_availability_log (id CHAR(36) NOT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:guid)\', hardware_group_id VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, is_available TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', logged_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', description LONGTEXT NOT NULL COLLATE utf8_unicode_ci, INDEX IDX_C6835B1523F56800 (hardware_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE hardware_group_availability_log ADD CONSTRAINT FK_C6835B1523F56800 FOREIGN KEY (hardware_group_id) REFERENCES hardware_group (id)');
    }
}
