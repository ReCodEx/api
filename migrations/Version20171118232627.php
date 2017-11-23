<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171118232627 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE pipeline ADD runtime_environment_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE pipeline ADD CONSTRAINT FK_7DFCD9D9C9F479A7 FOREIGN KEY (runtime_environment_id) REFERENCES runtime_environment (id)');
        $this->addSql('CREATE INDEX IDX_7DFCD9D9C9F479A7 ON pipeline (runtime_environment_id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE pipeline DROP FOREIGN KEY FK_7DFCD9D9C9F479A7');
        $this->addSql('DROP INDEX IDX_7DFCD9D9C9F479A7 ON pipeline');
        $this->addSql('ALTER TABLE pipeline DROP runtime_environment_id');
    }
}
