<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171108221323 extends AbstractMigration
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

        $this->addSql('ALTER TABLE reference_exercise_solution DROP uploaded_at');
        $this->addSql(
            'ALTER TABLE reference_solution_evaluation ADD submitted_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', ADD submitted_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\''
        );
        $this->addSql(
            'ALTER TABLE reference_solution_evaluation ADD CONSTRAINT FK_62BA741F79F7D87D FOREIGN KEY (submitted_by_id) REFERENCES user (id)'
        );
        $this->addSql('CREATE INDEX IDX_62BA741F79F7D87D ON reference_solution_evaluation (submitted_by_id)');
        $this->addSql('ALTER TABLE solution_evaluation DROP bonus_points');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
