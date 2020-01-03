<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171116150410 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql(
            'CREATE TABLE pipeline_parameter (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', pipeline_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(255) NOT NULL, discriminator VARCHAR(255) NOT NULL, boolean_value TINYINT(1) DEFAULT NULL COMMENT \'(DC2Type:boolean)\', string_value VARCHAR(255) DEFAULT NULL, INDEX IDX_59D0D302E80B93 (pipeline_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'ALTER TABLE pipeline_parameter ADD CONSTRAINT FK_59D0D302E80B93 FOREIGN KEY (pipeline_id) REFERENCES pipeline (id)'
        );
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('DROP TABLE pipeline_parameter');
    }
}
