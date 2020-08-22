<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200214160239 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    /**
     * @param Schema $schema
     */
    public function preUp(Schema $schema): void
    {
        $this->connection->executeQuery(
            "UPDATE assignment_solution SET note = SUBSTRING(note, 1, 1024) WHERE LENGTH(note) > 1024"
        );
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE assignment_solution CHANGE note note VARCHAR(1024) NOT NULL');
        $this->addSql('ALTER TABLE hardware_group CHANGE description description VARCHAR(1024) NOT NULL');
        $this->addSql('ALTER TABLE localized_exercise CHANGE external_assignment_link external_assignment_link VARCHAR(1024) DEFAULT NULL');
        $this->addSql('ALTER TABLE localized_shadow_assignment CHANGE external_assignment_link external_assignment_link VARCHAR(1024) DEFAULT NULL');
        $this->addSql('ALTER TABLE runtime_environment CHANGE description description VARCHAR(1024) NOT NULL');
        $this->addSql('ALTER TABLE shadow_assignment_points CHANGE note note VARCHAR(1024) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE assignment_solution CHANGE note note LONGTEXT NOT NULL COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE hardware_group CHANGE description description LONGTEXT NOT NULL COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE localized_exercise CHANGE external_assignment_link external_assignment_link LONGTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE localized_shadow_assignment CHANGE external_assignment_link external_assignment_link LONGTEXT DEFAULT NULL COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE runtime_environment CHANGE description description LONGTEXT NOT NULL COLLATE utf8mb4_unicode_ci');
        $this->addSql('ALTER TABLE shadow_assignment_points CHANGE note note LONGTEXT NOT NULL COLLATE utf8mb4_unicode_ci');
    }
}
