<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180113121818 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE pipeline_config DROP FOREIGN KEY FK_324206933EA4CB4D');
        $this->addSql('ALTER TABLE pipeline_config ADD CONSTRAINT FK_324206933EA4CB4D FOREIGN KEY (created_from_id) REFERENCES pipeline_config (id) ON DELETE SET NULL');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE pipeline_config DROP FOREIGN KEY FK_324206933EA4CB4D');
        $this->addSql('ALTER TABLE pipeline_config ADD CONSTRAINT FK_324206933EA4CB4D FOREIGN KEY (created_from_id) REFERENCES pipeline_config (id)');
    }
}
