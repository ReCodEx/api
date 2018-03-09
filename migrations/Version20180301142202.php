<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180301142202 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE assignment_disabled_runtime_environments (assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', runtime_environment_id VARCHAR(255) NOT NULL, INDEX IDX_63E4FB5DD19302F8 (assignment_id), INDEX IDX_63E4FB5DC9F479A7 (runtime_environment_id), PRIMARY KEY(assignment_id, runtime_environment_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE assignment_disabled_runtime_environments ADD CONSTRAINT FK_63E4FB5DD19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE assignment_disabled_runtime_environments ADD CONSTRAINT FK_63E4FB5DC9F479A7 FOREIGN KEY (runtime_environment_id) REFERENCES runtime_environment (id) ON DELETE CASCADE');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('DROP TABLE assignment_disabled_runtime_environments');
    }
}
