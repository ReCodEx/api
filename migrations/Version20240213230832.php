<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240213230832 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE security_event (id INT AUTO_INCREMENT NOT NULL, user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', type VARCHAR(255) NOT NULL, remote_addr VARCHAR(255) NOT NULL, data TEXT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_D712E90DA76ED395 (user_id), INDEX event_created_at_idx (created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE security_event ADD CONSTRAINT FK_D712E90DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE assignment ADD exam TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE `group` ADD exam_lock_strict TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE group_exam ADD lock_strict TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE group_exam_lock CHANGE ip remote_addr VARCHAR(255) NOT NULL');
        $this->addSql('CREATE INDEX lock_created_at_idx ON group_exam_lock (created_at)');
        $this->addSql('ALTER TABLE user ADD group_lock_strict TINYINT(1) NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE security_event DROP FOREIGN KEY FK_D712E90DA76ED395');
        $this->addSql('DROP TABLE security_event');
        $this->addSql('ALTER TABLE assignment DROP exam');
        $this->addSql('ALTER TABLE `group` DROP exam_lock_strict');
        $this->addSql('ALTER TABLE group_exam DROP lock_strict');
        $this->addSql('DROP INDEX lock_created_at_idx ON group_exam_lock');
        $this->addSql('ALTER TABLE group_exam_lock CHANGE remote_addr ip VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE user DROP group_lock_strict');
    }
}
