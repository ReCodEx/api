<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220721143607 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE assignment_solver (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', assignment_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', solver_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', last_attempt_index INT NOT NULL, evaluations_count INT NOT NULL, INDEX IDX_983ACDE6D19302F8 (assignment_id), INDEX IDX_983ACDE6BE651DEC (solver_id), UNIQUE INDEX UNIQ_983ACDE6D19302F8BE651DEC (assignment_id, solver_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE assignment_solver ADD CONSTRAINT FK_983ACDE6D19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id)');
        $this->addSql('ALTER TABLE assignment_solver ADD CONSTRAINT FK_983ACDE6BE651DEC FOREIGN KEY (solver_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE assignment_solution ADD attempt_index INT NOT NULL');
    }

    public function postUp(Schema $schema): void
    {
        // fills in the assignment_solution attempt index by ranking sorted solutions of each user-assignment pair
        $this->connection->executeStatement(
            "UPDATE assignment_solution, (
                SELECT asol.id AS id,
                RANK() OVER (PARTITION BY asol.assignment_id, sol.author_id ORDER BY sol.created_at) AS attempt
                FROM assignment_solution AS asol JOIN solution AS sol ON sol.id = asol.solution_id
            ) AS att
            SET assignment_solution.attempt_index = att.attempt
            WHERE assignment_solution.id = att.id"
        );

        // fill in the assignment_solver by selecting all solutions, grouping them,
        // and calculating their max rank and sum of evaluations
        $this->connection->executeStatement(
            "INSERT INTO assignment_solver (id, assignment_id, solver_id, last_attempt_index, evaluations_count)
            SELECT UUID() AS id, prep.assignment_id AS assignment_id, prep.author_id AS solver_id,
            MAX(prep.attIdx) AS last_attempt_index, SUM(evaluations_count) AS evaluations_count
            FROM (SELECT asol.assignment_id AS assignment_id, sol.author_id AS author_id, asol.attempt_index AS attIdx,
                (SELECT COUNT(*) FROM assignment_solution_submission AS ass
                    WHERE ass.assignment_solution_id = asol.id) AS evaluations_count
                FROM assignment_solution AS asol JOIN solution AS sol ON sol.id = asol.solution_id
            ) AS prep
            GROUP BY prep.assignment_id, prep.author_id"
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE assignment_solver');
        $this->addSql('ALTER TABLE assignment_solution DROP attempt_index');
    }
}
