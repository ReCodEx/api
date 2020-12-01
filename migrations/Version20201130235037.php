<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20201130235037 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE assignment ADD can_view_judge_stderr TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', CHANGE can_view_judge_outputs can_view_judge_stdout TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\'');
        $this->addSql('ALTER TABLE test_result ADD exit_signal INT DEFAULT NULL, ADD judge_stderr TEXT DEFAULT NULL, CHANGE judge_output judge_stdout TEXT DEFAULT NULL');
    }

    /**
     * @param Schema $schema
     * Cutting score (first line) from judge logs.
     */
    public function postUp(Schema $schema): void
    {
        $this->connection->executeQuery(
            "UPDATE test_result SET judge_stdout = REGEXP_REPLACE(judge_stdout, '^[0-9]+([.][0-9]+)?[ \n]+', '')"
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE assignment CHANGE can_view_judge_stdout can_view_judge_outputs TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', DROP can_view_judge_stderr');
        $this->addSql('ALTER TABLE test_result CHANGE judge_stdout judge_output TEXT DEFAULT NULL, DROP exit_signal, DROP judge_stderr');
    }

    /**
     * @param Schema $schema
     */
    public function postDown(Schema $schema): void
    {
        $this->connection->executeQuery(
            "UPDATE test_result SET judge_output = CONCAT(score, '\n', judge_output)"
        );
    }
}
