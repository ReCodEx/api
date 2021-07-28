<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210728221516 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE `group` ADD directly_archived TINYINT(1) NOT NULL, CHANGE archivation_date archived_at DATETIME DEFAULT NULL');
    }

    /**
     * @param Schema $schema
     * Updating the archiving states to new logic.
     */
    public function postUp(Schema $schema): void
    {
        // make sure we remember all directly archived groups properly
        $this->connection->executeStatement(
            "UPDATE `group` SET directly_archived = 1 WHERE archived_at IS NOT NULL"
        );

        // now we mark all transitively archived groups
        do {
            // the updates need to be iterated to reach the lowest depths of the group hierarchy
            $updateGroups = $this->connection->executeQuery(
                "SELECT g.id, (SELECT pg.archived_at FROM `group` AS pg WHERE pg.id = g.parent_group_id) AS newArchiveDate
                FROM `group` AS g WHERE g.archived_at IS NULL HAVING newArchiveDate IS NOT NULL"
            );

            $updated = 0;
            foreach ($updateGroups as $group) {
                $updated += $this->connection->executeStatement(
                    "UPDATE `group` SET archived_at = :archivedAt WHERE id = :id LIMIT 1",
                    [
                        'id' => $group['id'],
                        'archivedAt' => $group['newArchiveDate'],
                    ]
                );
            }
        } while ($updated > 0);
    }

    /**
     * @param Schema $schema
     * Reverting archiving states to olf logic.
     */
    public function preDown(Schema $schema): void
    {
        $this->connection->executeStatement(
            "UPDATE `group` SET archived_at = NULL WHERE directly_archived = 0"
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');
        $this->addSql('ALTER TABLE `group` DROP directly_archived, CHANGE archived_at archivation_date DATETIME DEFAULT NULL');
    }
}
