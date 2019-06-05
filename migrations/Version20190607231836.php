<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190607231836 extends AbstractMigration
{
    public function getDescription() : string
    {
        return '';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE user_ui_data (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', data LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE user ADD ui_data_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE user ADD CONSTRAINT FK_8D93D64925B58E7D FOREIGN KEY (ui_data_id) REFERENCES user_ui_data (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_8D93D64925B58E7D ON user (ui_data_id)');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D64925B58E7D');
        $this->addSql('DROP INDEX UNIQ_8D93D64925B58E7D ON user');
        $this->addSql('ALTER TABLE user DROP ui_data_id');
        $this->addSql('DROP TABLE user_ui_data');
    }
}
