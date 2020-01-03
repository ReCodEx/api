<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171021162329 extends AbstractMigration
{
    /**
     * @var array
     */
    private $groupAdmin = [];

    public function preUp(Schema $schema): void
    {
        $result = $this->connection->executeQuery("SELECT id, admin_id FROM `group` WHERE admin_id IS NOT NULL");
        foreach ($result as $row) {
            $groupId = $row["id"];
            $adminId = $row["admin_id"];
            $this->groupAdmin[] = "('$groupId', '$adminId')";
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
            'CREATE TABLE group_user (group_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_A4C98D39FE54D947 (group_id), INDEX IDX_A4C98D39A76ED395 (user_id), PRIMARY KEY(group_id, user_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'ALTER TABLE group_user ADD CONSTRAINT FK_A4C98D39FE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE group_user ADD CONSTRAINT FK_A4C98D39A76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE'
        );
        $this->addSql('ALTER TABLE `group` DROP FOREIGN KEY FK_6DC044C5642B8210');
        $this->addSql('DROP INDEX IDX_6DC044C5642B8210 ON `group`');
        $this->addSql('ALTER TABLE `group` DROP admin_id');
    }

    public function postUp(Schema $schema): void
    {
        if (empty($this->groupAdmin)) {
            return;
        }

        $this->connection->executeQuery(
            "INSERT INTO group_user (group_id, user_id) VALUES " . implode(', ', $this->groupAdmin)
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

        $this->addSql('DROP TABLE group_user');
        $this->addSql(
            'ALTER TABLE `group` ADD admin_id CHAR(36) DEFAULT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:guid)\''
        );
        $this->addSql(
            'ALTER TABLE `group` ADD CONSTRAINT FK_6DC044C5642B8210 FOREIGN KEY (admin_id) REFERENCES user (id)'
        );
        $this->addSql('CREATE INDEX IDX_6DC044C5642B8210 ON `group` (admin_id)');
    }
}
