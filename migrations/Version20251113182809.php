<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251113182809 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE uploaded_file ADD external VARCHAR(16) DEFAULT NULL');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_B40DF75D5852A1B8 ON uploaded_file (external)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP INDEX UNIQ_B40DF75D5852A1B8 ON `uploaded_file`');
        $this->addSql('ALTER TABLE `uploaded_file` DROP external');
    }
}
