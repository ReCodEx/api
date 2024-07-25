<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240725113248 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assignment_solution ADD review_request TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE user_settings ADD solution_accepted_emails TINYINT(1) NOT NULL, ADD solution_review_requested_emails TINYINT(1) NOT NULL');
    }

    public function postUp(Schema $schema): void
    {
        $this->connection->executeQuery("UPDATE user_settings SET solution_review_requested_emails = 1");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assignment_solution DROP review_request');
        $this->addSql('ALTER TABLE user_settings DROP solution_accepted_emails, DROP solution_review_requested_emails');
    }
}
