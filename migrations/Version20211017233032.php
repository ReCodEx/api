<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211017233032 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE reference_exercise_solution ADD last_submission_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE reference_exercise_solution ADD CONSTRAINT FK_E414ABAB8DF22AA4 FOREIGN KEY (last_submission_id) REFERENCES reference_solution_submission (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_E414ABAB8DF22AA4 ON reference_exercise_solution (last_submission_id)');

        // And update data in the newly created column...
        $this->addSql('UPDATE reference_exercise_solution AS u LEFT JOIN (
            SELECT sol.id AS solutionId, (SELECT sub.id FROM reference_solution_submission AS sub
                WHERE sub.reference_solution_id = sol.id AND sub.submitted_at = (
                    SELECT MAX(sub2.submitted_at) FROM reference_solution_submission AS sub2
                    WHERE sub2.reference_solution_id = sub.reference_solution_id
                ) ORDER BY sub.id LIMIT 1
            ) AS lastSubmissionId FROM reference_exercise_solution AS sol) AS t ON u.id = t.solutionId
            SET u.last_submission_id = t.lastSubmissionId');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE reference_exercise_solution DROP FOREIGN KEY FK_E414ABAB8DF22AA4');
        $this->addSql('DROP INDEX UNIQ_E414ABAB8DF22AA4 ON reference_exercise_solution');
        $this->addSql('ALTER TABLE reference_exercise_solution DROP last_submission_id');
    }
}
