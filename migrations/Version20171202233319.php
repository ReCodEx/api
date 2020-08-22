<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171202233319 extends AbstractMigration
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
            'CREATE TABLE pipeline_runtime_environment (pipeline_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', runtime_environment_id VARCHAR(255) NOT NULL, INDEX IDX_9068DE17E80B93 (pipeline_id), INDEX IDX_9068DE17C9F479A7 (runtime_environment_id), PRIMARY KEY(pipeline_id, runtime_environment_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'ALTER TABLE pipeline_runtime_environment ADD CONSTRAINT FK_9068DE17E80B93 FOREIGN KEY (pipeline_id) REFERENCES pipeline (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE pipeline_runtime_environment ADD CONSTRAINT FK_9068DE17C9F479A7 FOREIGN KEY (runtime_environment_id) REFERENCES runtime_environment (id) ON DELETE CASCADE'
        );
        $this->addSql('ALTER TABLE pipeline DROP FOREIGN KEY FK_7DFCD9D9C9F479A7');

        $this->addSql(
            'INSERT INTO pipeline_runtime_environment (pipeline_id, runtime_environment_id) SELECT id, runtime_environment_id FROM pipeline WHERE runtime_environment_id IS NOT NULL'
        );

        $this->addSql('DROP INDEX IDX_7DFCD9D9C9F479A7 ON pipeline');
        $this->addSql('ALTER TABLE pipeline DROP runtime_environment_id');
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

        $this->addSql('DROP TABLE pipeline_runtime_environment');
        $this->addSql(
            'ALTER TABLE pipeline ADD runtime_environment_id VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci'
        );
        $this->addSql(
            'ALTER TABLE pipeline ADD CONSTRAINT FK_7DFCD9D9C9F479A7 FOREIGN KEY (runtime_environment_id) REFERENCES runtime_environment (id)'
        );
        $this->addSql('CREATE INDEX IDX_7DFCD9D9C9F479A7 ON pipeline (runtime_environment_id)');
    }
}
