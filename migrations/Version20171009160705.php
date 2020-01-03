<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171009160705 extends AbstractMigration
{
    /**
     * @var array
     */
    private $exerciseGroup = [];

    public function preUp(Schema $schema): void
    {
        $result = $this->connection->executeQuery("SELECT id, group_id FROM exercise WHERE group_id IS NOT NULL");
        foreach ($result as $row) {
            $exerciseId = $row["id"];
            $groupId = $row["group_id"];
            $this->exerciseGroup[] = "('$exerciseId', '$groupId')";
        }
    }

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
            'CREATE TABLE exercise_group (exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', group_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_48F6B307E934951A (exercise_id), INDEX IDX_48F6B307FE54D947 (group_id), PRIMARY KEY(exercise_id, group_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'ALTER TABLE exercise_group ADD CONSTRAINT FK_48F6B307E934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE exercise_group ADD CONSTRAINT FK_48F6B307FE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id) ON DELETE CASCADE'
        );
        $this->addSql('ALTER TABLE exercise DROP FOREIGN KEY FK_AEDAD51CFE54D947');
        $this->addSql('DROP INDEX IDX_AEDAD51CFE54D947 ON exercise');
        $this->addSql('ALTER TABLE exercise DROP group_id');
    }

    public function postUp(Schema $schema): void
    {
        if (empty($this->exerciseGroup)) {
            return;
        }

        $this->connection->executeQuery(
            "INSERT INTO exercise_group (exercise_id, group_id) VALUES " . implode(', ', $this->exerciseGroup)
        );
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

        $this->addSql('DROP TABLE exercise_group');
        $this->addSql(
            'ALTER TABLE exercise ADD group_id CHAR(36) DEFAULT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:guid)\''
        );
        $this->addSql(
            'ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51CFE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id)'
        );
        $this->addSql('CREATE INDEX IDX_AEDAD51CFE54D947 ON exercise (group_id)');
    }
}
