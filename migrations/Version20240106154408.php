<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240106154408 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `group` ADD exam_begin DATETIME DEFAULT NULL, ADD exam_end DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD group_lock_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', ADD ip_lock VARCHAR(255) DEFAULT NULL, ADD ip_lock_expiration DATETIME DEFAULT NULL, ADD group_lock_expiration DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D64956D71D1 FOREIGN KEY (group_lock_id) REFERENCES `group` (id)');
        $this->addSql('CREATE INDEX IDX_8D93D64956D71D1 ON user (group_lock_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `group` DROP exam_begin, DROP exam_end');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D64956D71D1');
        $this->addSql('DROP INDEX IDX_8D93D64956D71D1 ON user');
        $this->addSql('ALTER TABLE user DROP group_lock_id, DROP ip_lock, DROP ip_lock_expiration, DROP group_lock_expiration');
    }
}
