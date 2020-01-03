<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190504111926 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
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
            'ALTER TABLE assignment_solution ADD last_submission_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\''
        );
        $this->addSql(
            'ALTER TABLE assignment_solution ADD CONSTRAINT FK_5B315D2E8DF22AA4 FOREIGN KEY (last_submission_id) REFERENCES assignment_solution_submission (id)'
        );
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5B315D2E8DF22AA4 ON assignment_solution (last_submission_id)');

        // And update data in the newly created column...
        $this->addSql(
            'UPDATE assignment_solution AS u LEFT JOIN (
          SELECT sol.id AS solutionId, (
              SELECT sub.id FROM assignment_solution_submission AS sub
              WHERE sub.assignment_solution_id = sol.id AND sub.submitted_at = (
                  SELECT MAX(sub2.submitted_at) FROM assignment_solution_submission AS sub2
                  WHERE sub2.assignment_solution_id = sub.assignment_solution_id
              )
          ) AS lastSubmissionId
          FROM assignment_solution AS sol
      ) AS t ON u.id = t.solutionId
      SET u.last_submission_id = t.lastSubmissionId'
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

        $this->addSql('ALTER TABLE assignment_solution DROP FOREIGN KEY FK_5B315D2E8DF22AA4');
        $this->addSql('DROP INDEX UNIQ_5B315D2E8DF22AA4 ON assignment_solution');
        $this->addSql('ALTER TABLE assignment_solution DROP last_submission_id');
    }
}
