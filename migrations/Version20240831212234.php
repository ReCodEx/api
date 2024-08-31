<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240831212234 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE group_external_attribute (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', service VARCHAR(32) NOT NULL, `key` VARCHAR(32) NOT NULL, value VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_F1907847FE54D947 (group_id), INDEX keys_idx (service, `key`), UNIQUE INDEX UNIQ_F1907847FE54D947E19D9AD28A90ABA91D775834 (group_id, service, `key`, value), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE group_external_attribute ADD CONSTRAINT FK_F1907847FE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE group_external_attribute DROP FOREIGN KEY FK_F1907847FE54D947');
        $this->addSql('DROP TABLE group_external_attribute');
    }
}
