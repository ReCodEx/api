<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171108205919 extends AbstractMigration
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

        $this->addSql('ALTER TABLE solution DROP FOREIGN KEY FK_9F3329DBA76ED395');
        $this->addSql('DROP INDEX IDX_9F3329DBA76ED395 ON solution');
        $this->addSql(
            'ALTER TABLE solution ADD created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', CHANGE user_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\''
        );
        $this->addSql(
            'ALTER TABLE solution ADD CONSTRAINT FK_9F3329DBF675F31B FOREIGN KEY (author_id) REFERENCES user (id)'
        );
        $this->addSql('CREATE INDEX IDX_9F3329DBF675F31B ON solution (author_id)');
        $this->addSql('ALTER TABLE submission DROP FOREIGN KEY FK_DB055AF3A76ED395');
        $this->addSql('DROP INDEX IDX_DB055AF3A76ED395 ON submission');
        $this->addSql('ALTER TABLE submission ADD bonus_points INT NOT NULL, DROP user_id');
    }

    public function postUp(Schema $schema): void
    {
        $this->connection->beginTransaction();

        // createAt datetime moved from submission to solution
        $result = $this->connection->executeQuery(
            "SELECT submitted_at, solution_id FROM submission WHERE original_submission_id IS NULL"
        );
        foreach ($result as $row) {
            $this->connection->executeQuery(
                "UPDATE solution SET created_at = '{$row['submitted_at']}' WHERE id = '{$row['solution_id']}'"
            );
        }

        // createAt datetime moved from reference solution to solution
        $result = $this->connection->executeQuery("SELECT uploaded_at, solution_id FROM reference_exercise_solution");
        foreach ($result as $row) {
            $this->connection->executeQuery(
                "UPDATE solution SET created_at = '{$row['uploaded_at']}' WHERE id = '{$row['solution_id']}'"
            );
        }

        // move bonus points to submission
        $result = $this->connection->executeQuery(
            "SELECT submission.id AS id, solution_evaluation.bonus_points AS bonus_points FROM " .
            "solution_evaluation INNER JOIN submission ON submission.evaluation_id = solution_evaluation.id"
        );
        foreach ($result as $row) {
            $this->connection->executeQuery(
                "UPDATE submission SET bonus_points = '{$row['bonus_points']}' WHERE id = '{$row['id']}'"
            );
        }

        $this->connection->commit();
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
