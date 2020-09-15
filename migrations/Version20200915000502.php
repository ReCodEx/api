<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200915000502 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE solution ADD subdir VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE assignment_solution_submission ADD subdir VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE reference_solution_submission ADD subdir VARCHAR(255) NOT NULL');
    }

    /**
     * @param Schema $schema
     */
    public function postUp(Schema $schema): void
    {
        $this->connection->executeQuery(
            "UPDATE solution SET subdir = DATE_FORMAT(created_at, '%Y-%m')"
        );
        $this->connection->executeQuery(
            "UPDATE assignment_solution_submission SET subdir = DATE_FORMAT(submitted_at, '%Y-%m')"
        );
        $this->connection->executeQuery(
            "UPDATE reference_solution_submission SET subdir = DATE_FORMAT(submitted_at, '%Y-%m')"
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE assignment_solution_submission DROP subdir');
        $this->addSql('ALTER TABLE reference_solution_submission DROP subdir');
        $this->addSql('ALTER TABLE solution DROP subdir');
    }
}
