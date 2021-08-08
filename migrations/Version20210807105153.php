<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20210807105153 extends AbstractMigration
{
    private $createdAts = null;

    public function getDescription(): string
    {
        return '';
    }

    public function preUp(Schema $schema): void
    {
        // there should be no inactive memberships, but let's be sure...
        $this->connection->executeStatement("DELETE FROM group_membership WHERE `status` != 'active'");

        // remove duplicate memberships (student & supervisor)
        $toDeletes = $this->connection->executeQuery(
            "SELECT gu.id FROM group_membership AS gu WHERE `type` = 'student' AND EXISTS
                (SELECT * FROM group_membership AS gu2 WHERE gu2.`type` != 'student'
                AND gu.user_id = gu2.user_id AND gu.group_id = gu2.group_id)"
        );

        foreach ($toDeletes as $toDelete) {
            $this->connection->executeStatement(
                "DELETE FROM group_membership WHERE id = :id",
                [ 'id' => $toDelete['id'] ]
            );
        }

        // load datetimes that will be used as created_at values
        $this->createdAts = $this->connection->executeQuery(
            "SELECT COALESCE(GREATEST(joined_at, COALESCE(student_since, supervisor_since)), joined_at) AS created_at,
                user_id, group_id FROM group_membership"
        );

        // make sure all admins are created or replace existing records
        // (each admin was also a supervisor, these data needs to be merged)
        $this->connection->executeStatement(
            "UPDATE group_membership AS gm SET `type` = 'admin' WHERE EXISTS
            (SELECT * FROM group_user AS gu WHERE gu.user_id = gm.user_id AND gu.group_id = gm.group_id)"
        );

        $this->connection->executeStatement(
            "INSERT INTO group_membership (`id`, `user_id`, `group_id`, `type`, `status`, `joined_at`)
            SELECT UUID() as `id`, `user_id`, `group_id`, 'admin' AS `type`, 'active' AS `status`, SYSDATE() AS `joined_at`
            FROM group_user AS gu WHERE NOT EXISTS
            (SELECT * FROM group_membership AS gm WHERE gu.user_id = gm.user_id AND gu.group_id = gm.group_id)"
        );
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE group_user');
        $this->addSql('ALTER TABLE group_membership ADD inherited_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', ADD created_at DATETIME NOT NULL, DROP status, DROP requested_at, DROP joined_at, DROP rejected_at, DROP student_since, DROP supervisor_since');
        $this->addSql('ALTER TABLE group_membership ADD CONSTRAINT FK_5132B33739FB77C6 FOREIGN KEY (inherited_from_id) REFERENCES `group` (id)');
        $this->addSql('CREATE INDEX IDX_5132B33739FB77C6 ON group_membership (inherited_from_id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5132B337A76ED395FE54D94739FB77C6 ON group_membership (user_id, group_id, inherited_from_id)');
    }

    public function postUp(Schema $schema): void
    {
        // set current date time as initial value for existing memberships
        $this->connection->executeStatement(
            "UPDATE group_membership SET created_at = SYSDATE()"
        );

        // save times loaded before up into new created_at field
        foreach ($this->createdAts as $data) {
            $this->connection->executeStatement(
                "UPDATE group_membership SET created_at = :cat WHERE user_id = :user_id AND group_id = :group_id",
                [ 'cat' => $data['created_at'], 'user_id' => $data['user_id'], 'group_id' => $data['group_id'] ]
            );
        }
    }

    public function preDown(Schema $schema): void
    {
        // delete inherited rules
        $this->connection->executeStatement(
            "DELETE FROM group_membership WHERE inherited_from_id IS NOT NULL"
        );

        $this->createdAts = $this->connection->executeQuery(
            "SELECT user_id, group_id, `type`, created_at FROM group_membership"
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE group_user (group_id CHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:guid)\', user_id CHAR(36) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci` COMMENT \'(DC2Type:guid)\', INDEX IDX_A4C98D39FE54D947 (group_id), INDEX IDX_A4C98D39A76ED395 (user_id), PRIMARY KEY(group_id, user_id)) DEFAULT CHARACTER SET utf8 COLLATE `utf8_unicode_ci` ENGINE = InnoDB COMMENT = \'\' ');
        $this->addSql('ALTER TABLE group_user ADD CONSTRAINT FK_A4C98D39A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_user ADD CONSTRAINT FK_A4C98D39FE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_membership DROP FOREIGN KEY FK_5132B33739FB77C6');
        $this->addSql('DROP INDEX IDX_5132B33739FB77C6 ON group_membership');
        $this->addSql('DROP INDEX UNIQ_5132B337A76ED395FE54D94739FB77C6 ON group_membership');
        $this->addSql('ALTER TABLE group_membership ADD status VARCHAR(255) CHARACTER SET utf8mb4 NOT NULL COLLATE `utf8mb4_unicode_ci`, ADD requested_at DATETIME DEFAULT NULL, ADD joined_at DATETIME DEFAULT NULL, ADD rejected_at DATETIME DEFAULT NULL, ADD student_since DATETIME DEFAULT NULL, ADD supervisor_since DATETIME DEFAULT NULL, DROP inherited_from_id, DROP created_at');
    }

    public function postDown(Schema $schema): void
    {
        // fill the restored group_user table with admins
        $this->connection->executeStatement(
            "INSERT group_user (user_id, group_id)
            SELECT user_id, group_id FROM group_membership WHERE `type` = 'admin'"
        );

        // convert admin memberships back to supervisors (yes, admins are supervisors as well)
        $this->connection->executeStatement(
            "UPDATE group_membership SET `type` = 'supervisor' WHERE `type` = 'admin'"
        );

        // restore datetime columns of memberships table
        foreach ($this->createdAts as $data) {
            $this->connection->executeStatement(
                "UPDATE group_membership SET joined_at = :joined_at,
                    student_since = :student_since, supervisor_since = :supervisor_since
                    WHERE user_id = :user_id AND group_id = :group_id",
                [
                    'user_id' => $data['user_id'],
                    'group_id' => $data['group_id'],
                    'joined_at' => $data['created_at'],
                    'student_since' => $data['type'] === 'student' ?  $data['created_at'] : null,
                    'supervisor_since' => $data['type'] !== 'student' ?  $data['created_at'] : null,
                ]
            );
        }
    }
}
