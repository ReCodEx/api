<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20181110115737 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE localized_notification (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', text LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', locale VARCHAR(255) NOT NULL, INDEX IDX_D0E237573EA4CB4D (created_from_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE notification_localized_notification (notification_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', localized_notification_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_42DD6CD8EF1A9D84 (notification_id), INDEX IDX_42DD6CD8D1B1DEB5 (localized_notification_id), PRIMARY KEY(notification_id, localized_notification_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE localized_notification ADD CONSTRAINT FK_D0E237573EA4CB4D FOREIGN KEY (created_from_id) REFERENCES localized_notification (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE notification_localized_notification ADD CONSTRAINT FK_42DD6CD8EF1A9D84 FOREIGN KEY (notification_id) REFERENCES notification (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification_localized_notification ADD CONSTRAINT FK_42DD6CD8D1B1DEB5 FOREIGN KEY (localized_notification_id) REFERENCES localized_notification (id) ON DELETE CASCADE');
        $this->addSql('DROP TABLE notification_localized_exercise');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE localized_notification DROP FOREIGN KEY FK_D0E237573EA4CB4D');
        $this->addSql('ALTER TABLE notification_localized_notification DROP FOREIGN KEY FK_42DD6CD8D1B1DEB5');
        $this->addSql('CREATE TABLE notification_localized_exercise (notification_id CHAR(36) NOT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:guid)\', localized_exercise_id CHAR(36) NOT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:guid)\', INDEX IDX_6B635453EF1A9D84 (notification_id), INDEX IDX_6B635453EF02E9CC (localized_exercise_id), PRIMARY KEY(notification_id, localized_exercise_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE notification_localized_exercise ADD CONSTRAINT FK_6B635453EF02E9CC FOREIGN KEY (localized_exercise_id) REFERENCES localized_exercise (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE notification_localized_exercise ADD CONSTRAINT FK_6B635453EF1A9D84 FOREIGN KEY (notification_id) REFERENCES notification (id) ON DELETE CASCADE');
        $this->addSql('DROP TABLE localized_notification');
        $this->addSql('DROP TABLE notification_localized_notification');
    }
}
