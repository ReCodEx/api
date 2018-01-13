<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180113115130 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE exercise_config DROP FOREIGN KEY FK_A1C5BA173EA4CB4D');
        $this->addSql('ALTER TABLE exercise_config ADD CONSTRAINT FK_A1C5BA173EA4CB4D FOREIGN KEY (created_from_id) REFERENCES exercise_config (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE exercise_environment_config DROP FOREIGN KEY FK_89540FF73EA4CB4D');
        $this->addSql('ALTER TABLE exercise_environment_config ADD CONSTRAINT FK_89540FF73EA4CB4D FOREIGN KEY (created_from_id) REFERENCES exercise_environment_config (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE exercise_limits DROP FOREIGN KEY FK_238CADD03EA4CB4D');
        $this->addSql('ALTER TABLE exercise_limits ADD CONSTRAINT FK_238CADD03EA4CB4D FOREIGN KEY (created_from_id) REFERENCES exercise_limits (id) ON DELETE SET NULL');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE exercise_config DROP FOREIGN KEY FK_A1C5BA173EA4CB4D');
        $this->addSql('ALTER TABLE exercise_config ADD CONSTRAINT FK_A1C5BA173EA4CB4D FOREIGN KEY (created_from_id) REFERENCES exercise_config (id)');
        $this->addSql('ALTER TABLE exercise_environment_config DROP FOREIGN KEY FK_89540FF73EA4CB4D');
        $this->addSql('ALTER TABLE exercise_environment_config ADD CONSTRAINT FK_89540FF73EA4CB4D FOREIGN KEY (created_from_id) REFERENCES exercise_environment_config (id)');
        $this->addSql('ALTER TABLE exercise_limits DROP FOREIGN KEY FK_238CADD03EA4CB4D');
        $this->addSql('ALTER TABLE exercise_limits ADD CONSTRAINT FK_238CADD03EA4CB4D FOREIGN KEY (created_from_id) REFERENCES exercise_limits (id)');
    }
}
