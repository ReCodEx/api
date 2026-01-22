<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20260122225237 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE group_exam DROP INDEX group_begin_idx, ADD UNIQUE INDEX UNIQ_11E1FDB6FE54D9477A859515 (group_id, begin)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE group_exam DROP INDEX UNIQ_11E1FDB6FE54D9477A859515, ADD INDEX group_begin_idx (group_id, begin)');
    }
}
