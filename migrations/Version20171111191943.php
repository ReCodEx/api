<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171111191943 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql(
            'CREATE TABLE localized_group (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', locale VARCHAR(255) NOT NULL, INDEX IDX_A9E6EF153EA4CB4D (created_from_id), INDEX IDX_A9E6EF15FE54D947 (group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );

        $this->addSql(
            "INSERT INTO `localized_group` (id, group_id, locale, `name`, `description`, created_at) SELECT UUID(), id, 'cs', `name`, `description`, NOW() FROM `group`"
        );

        $this->addSql(
            'ALTER TABLE localized_group ADD CONSTRAINT FK_A9E6EF153EA4CB4D FOREIGN KEY (created_from_id) REFERENCES localized_group (id) ON DELETE SET NULL'
        );
        $this->addSql(
            'ALTER TABLE localized_group ADD CONSTRAINT FK_A9E6EF15FE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id)'
        );
        $this->addSql('ALTER TABLE instance DROP name, DROP description');
        $this->addSql('ALTER TABLE `group` DROP name, DROP description');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql(
            'ALTER TABLE `group` ADD name VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, ADD description LONGTEXT NOT NULL COLLATE utf8_unicode_ci'
        );
        $this->addSql(
            'ALTER TABLE instance ADD name VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, ADD description LONGTEXT DEFAULT NULL COLLATE utf8_unicode_ci'
        );

        $this->addSql("UPDATE `group` g SET `name` = (SELECT `name` FROM localized_group WHERE group_id = g.id)");
        $this->addSql(
            "UPDATE `group` g SET `description` = (SELECT `description` FROM localized_group WHERE group_id = g.id)"
        );

        $this->addSql('ALTER TABLE localized_group DROP FOREIGN KEY FK_A9E6EF153EA4CB4D');
        $this->addSql('DROP TABLE localized_group');
    }
}
