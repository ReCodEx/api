<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200423174547 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE solution_evaluation ADD score_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE solution_evaluation ADD CONSTRAINT FK_E4248A27AF2FC52 FOREIGN KEY (score_config_id) REFERENCES exercise_score_config (id)');
        $this->addSql('CREATE INDEX IDX_E4248A27AF2FC52 ON solution_evaluation (score_config_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE solution_evaluation DROP FOREIGN KEY FK_E4248A27AF2FC52');
        $this->addSql('DROP INDEX IDX_E4248A27AF2FC52 ON solution_evaluation');
        $this->addSql('ALTER TABLE solution_evaluation DROP score_config_id');
    }
}
