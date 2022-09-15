<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220914220522 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE group_invitation (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', host_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', expire_at DATETIME DEFAULT NULL, note VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_26D00010FE54D947 (group_id), INDEX IDX_26D000101FB8D185 (host_id), INDEX grouped_created_at_idx (group_id, created_at), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE group_invitation ADD CONSTRAINT FK_26D00010FE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id)');
        $this->addSql('ALTER TABLE group_invitation ADD CONSTRAINT FK_26D000101FB8D185 FOREIGN KEY (host_id) REFERENCES user (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE group_invitation');
    }
}
