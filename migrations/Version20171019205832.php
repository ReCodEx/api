<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171019205832 extends AbstractMigration
{
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

        $this->addSql('ALTER TABLE submission_failure DROP FOREIGN KEY FK_D7A9817FA3CA3B7');
        $this->addSql('DROP INDEX IDX_D7A9817FA3CA3B7 ON submission_failure');
        $this->addSql(
            'ALTER TABLE submission_failure CHANGE reference_solution_id reference_solution_evaluation_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\''
        );
        $this->addSql(
            'ALTER TABLE submission_failure ADD CONSTRAINT FK_D7A981711DCC77F FOREIGN KEY (reference_solution_evaluation_id) REFERENCES reference_solution_evaluation (id)'
        );
        $this->addSql('CREATE INDEX IDX_D7A981711DCC77F ON submission_failure (reference_solution_evaluation_id)');
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

        $this->addSql('ALTER TABLE submission_failure DROP FOREIGN KEY FK_D7A981711DCC77F');
        $this->addSql('DROP INDEX IDX_D7A981711DCC77F ON submission_failure');
        $this->addSql(
            'ALTER TABLE submission_failure CHANGE reference_solution_evaluation_id reference_solution_id CHAR(36) DEFAULT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:guid)\''
        );
        $this->addSql(
            'ALTER TABLE submission_failure ADD CONSTRAINT FK_D7A9817FA3CA3B7 FOREIGN KEY (reference_solution_id) REFERENCES reference_exercise_solution (id)'
        );
        $this->addSql('CREATE INDEX IDX_D7A9817FA3CA3B7 ON submission_failure (reference_solution_id)');
    }
}
