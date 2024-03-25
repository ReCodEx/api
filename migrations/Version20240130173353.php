<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240130173353 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE group_exam (id INT AUTO_INCREMENT NOT NULL, group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', begin DATETIME NOT NULL, end DATETIME NOT NULL, INDEX IDX_11E1FDB6FE54D947 (group_id), INDEX group_begin_idx (group_id, begin), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE group_exam_lock (id INT AUTO_INCREMENT NOT NULL, group_exam_id INT DEFAULT NULL, student_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ip VARCHAR(255) NOT NULL, unlocked_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_CAD8415CD18D0A9D (group_exam_id), INDEX IDX_CAD8415CCB944F1A (student_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE group_exam ADD CONSTRAINT FK_11E1FDB6FE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id)');
        $this->addSql('ALTER TABLE group_exam_lock ADD CONSTRAINT FK_CAD8415CD18D0A9D FOREIGN KEY (group_exam_id) REFERENCES group_exam (id)');
        $this->addSql('ALTER TABLE group_exam_lock ADD CONSTRAINT FK_CAD8415CCB944F1A FOREIGN KEY (student_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE `group` ADD is_exam TINYINT(1) DEFAULT 0 NOT NULL');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE group_exam DROP FOREIGN KEY FK_11E1FDB6FE54D947');
        $this->addSql('ALTER TABLE group_exam_lock DROP FOREIGN KEY FK_CAD8415CD18D0A9D');
        $this->addSql('ALTER TABLE group_exam_lock DROP FOREIGN KEY FK_CAD8415CCB944F1A');
        $this->addSql('DROP TABLE group_exam');
        $this->addSql('DROP TABLE group_exam_lock');
        $this->addSql('ALTER TABLE `group` DROP is_exam');
    }
}
