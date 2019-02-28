<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190228170751 extends AbstractMigration
{
    public function getDescription() : string
    {
        return 'Adds defaultPage user settings.';
    }

    public function up(Schema $schema) : void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE user_settings ADD default_page VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, CHANGE default_language default_language VARCHAR(32) NOT NULL COLLATE utf8mb4_unicode_ci');
    }

    public function down(Schema $schema) : void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE user_settings DROP default_page, CHANGE default_language default_language VARCHAR(255) NOT NULL COLLATE utf8mb4_unicode_ci');
    }
}
