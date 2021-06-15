<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210423084318 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE async_job (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', created_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', associated_assignment_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', created_at DATETIME NOT NULL, scheduled_at DATETIME DEFAULT NULL, started_at DATETIME DEFAULT NULL, finished_at DATETIME DEFAULT NULL, retries INT NOT NULL, worker_id VARCHAR(255) DEFAULT NULL, command VARCHAR(255) NOT NULL, arguments VARCHAR(255) NOT NULL, error VARCHAR(255) DEFAULT NULL, INDEX IDX_8DA5C76BB03A8386 (created_by_id), INDEX IDX_8DA5C76B7951D628 (associated_assignment_id), INDEX created_at_idx (created_at), INDEX scheduled_at_idx (scheduled_at), INDEX started_at_idx (started_at), INDEX finished_at_idx (finished_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE async_job ADD CONSTRAINT FK_8DA5C76BB03A8386 FOREIGN KEY (created_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE async_job ADD CONSTRAINT FK_8DA5C76B7951D628 FOREIGN KEY (associated_assignment_id) REFERENCES assignment (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE async_job');
    }
}
