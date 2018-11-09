<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20181109123450 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE notification (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', visible_from DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', visible_to DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', role VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, INDEX IDX_BF5476CAF675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notification_localized_exercise (notification_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', localized_exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_6B635453EF1A9D84 (notification_id), INDEX IDX_6B635453EF02E9CC (localized_exercise_id), PRIMARY KEY(notification_id, localized_exercise_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notification_group (notification_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', group_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_AB74A13CEF1A9D84 (notification_id), INDEX IDX_AB74A13CFE54D947 (group_id), PRIMARY KEY(notification_id, group_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notification ADD CONSTRAINT FK_BF5476CAF675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE notification_localized_exercise ADD CONSTRAINT FK_6B635453EF1A9D84 FOREIGN KEY (notification_id) REFERENCES notification (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification_localized_exercise ADD CONSTRAINT FK_6B635453EF02E9CC FOREIGN KEY (localized_exercise_id) REFERENCES localized_exercise (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification_group ADD CONSTRAINT FK_AB74A13CEF1A9D84 FOREIGN KEY (notification_id) REFERENCES notification (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification_group ADD CONSTRAINT FK_AB74A13CFE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id) ON DELETE CASCADE');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE notification_localized_exercise DROP FOREIGN KEY FK_6B635453EF1A9D84');
        $this->addSql('ALTER TABLE notification_group DROP FOREIGN KEY FK_AB74A13CEF1A9D84');
        $this->addSql('DROP TABLE notification');
        $this->addSql('DROP TABLE notification_localized_exercise');
        $this->addSql('DROP TABLE notification_group');
    }
}
