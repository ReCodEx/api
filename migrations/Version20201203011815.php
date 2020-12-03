<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201203011815 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE assignment ADD merge_judge_logs TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\'');
        $this->addSql('ALTER TABLE exercise ADD merge_judge_logs TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\'');
    }

    /**
     * @param Schema $schema
     * Setting all mergeJudgeLogs flags to true to ensure BC.
     */
    public function postUp(Schema $schema): void
    {
        $this->connection->executeQuery("UPDATE exercise SET merge_judge_logs = 1");
        $this->connection->executeQuery("UPDATE assignment SET merge_judge_logs = 1");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE assignment DROP merge_judge_logs');
        $this->addSql('ALTER TABLE exercise DROP merge_judge_logs');
    }
}
