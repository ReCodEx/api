<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190503212616 extends AbstractMigration
{
    private $failures = [];

    public function getDescription(): string
    {
        return '';
    }

    /**
     * @param Schema $schema
     */
    public function preUp(Schema $schema): void
    {
        $this->failures = $this->connection->executeQuery(
            "SELECT id, reference_solution_submission_id, assignment_solution_submission_id FROM submission_failure"
        )->fetchAll();
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
            'ALTER TABLE assignment_solution_submission ADD failure_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\''
        );
        $this->addSql(
            'ALTER TABLE assignment_solution_submission ADD CONSTRAINT FK_114838A3BADC2069 FOREIGN KEY (failure_id) REFERENCES submission_failure (id)'
        );
        $this->addSql('CREATE UNIQUE INDEX UNIQ_114838A3BADC2069 ON assignment_solution_submission (failure_id)');
        $this->addSql(
            'ALTER TABLE reference_solution_submission ADD failure_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\''
        );
        $this->addSql(
            'ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_AA9C8B99BADC2069 FOREIGN KEY (failure_id) REFERENCES submission_failure (id)'
        );
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AA9C8B99BADC2069 ON reference_solution_submission (failure_id)');

        $this->addSql('ALTER TABLE submission_failure DROP FOREIGN KEY FK_D7A9817B54DD80A');
        $this->addSql('ALTER TABLE submission_failure DROP FOREIGN KEY FK_D7A9817D2E75341');
        $this->addSql('DROP INDEX IDX_D7A9817D2E75341 ON submission_failure');
        $this->addSql('DROP INDEX IDX_D7A9817B54DD80A ON submission_failure');
        $this->addSql(
            'ALTER TABLE submission_failure DROP reference_solution_submission_id, DROP assignment_solution_submission_id'
        );
    }

    /**
     * @param Schema $schema
     */
    public function postUp(Schema $schema): void
    {
        foreach ($this->failures as $failure) {
            if ($failure['assignment_solution_submission_id']) {
                $this->connection->executeQuery(
                    'UPDATE assignment_solution_submission SET failure_id = :failureId WHERE id = :submissionId',
                    ['submissionId' => $failure['assignment_solution_submission_id'], 'failureId' => $failure['id']]
                );
            }
            if ($failure['reference_solution_submission_id']) {
                $this->connection->executeQuery(
                    'UPDATE reference_solution_submission SET failure_id = :failureId WHERE id = :submissionId',
                    ['submissionId' => $failure['reference_solution_submission_id'], 'failureId' => $failure['id']]
                );
            }
        }
    }


    private $assignmentSubmissions = [];
    private $referenceSubmissions = [];

    /**
     * @param Schema $schema
     */
    public function preDown(Schema $schema): void
    {
        $this->assignmentSubmissions = $this->connection->executeQuery(
            "SELECT id, failure_id FROM assignment_solution_submission WHERE failure_id IS NOT NULL"
        )->fetchAll();
        $this->referenceSubmissions = $this->connection->executeQuery(
            "SELECT id, failure_id FROM reference_solution_submission WHERE failure_id IS NOT NULL"
        )->fetchAll();
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

        $this->addSql('ALTER TABLE assignment_solution_submission DROP FOREIGN KEY FK_114838A3BADC2069');
        $this->addSql('DROP INDEX UNIQ_114838A3BADC2069 ON assignment_solution_submission');
        $this->addSql('ALTER TABLE assignment_solution_submission DROP failure_id');
        $this->addSql('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_AA9C8B99BADC2069');
        $this->addSql('DROP INDEX UNIQ_AA9C8B99BADC2069 ON reference_solution_submission');
        $this->addSql('ALTER TABLE reference_solution_submission DROP failure_id');
        $this->addSql(
            'ALTER TABLE submission_failure ADD reference_solution_submission_id CHAR(36) DEFAULT NULL COLLATE utf8mb4_unicode_ci COMMENT \'(DC2Type:guid)\', ADD assignment_solution_submission_id CHAR(36) DEFAULT NULL COLLATE utf8mb4_unicode_ci COMMENT \'(DC2Type:guid)\''
        );
        $this->addSql(
            'ALTER TABLE submission_failure ADD CONSTRAINT FK_D7A9817B54DD80A FOREIGN KEY (reference_solution_submission_id) REFERENCES reference_solution_submission (id)'
        );
        $this->addSql(
            'ALTER TABLE submission_failure ADD CONSTRAINT FK_D7A9817D2E75341 FOREIGN KEY (assignment_solution_submission_id) REFERENCES assignment_solution_submission (id)'
        );
        $this->addSql('CREATE INDEX IDX_D7A9817D2E75341 ON submission_failure (assignment_solution_submission_id)');
        $this->addSql('CREATE INDEX IDX_D7A9817B54DD80A ON submission_failure (reference_solution_submission_id)');
    }

    /**
     * @param Schema $schema
     */
    public function postDown(Schema $schema): void
    {
        foreach ($this->assignmentSubmissions as $submission) {
            $this->connection->executeQuery(
                'UPDATE submission_failure SET assignment_solution_submission_id = :submissionId WHERE id = :failureId',
                ['submissionId' => $submission['id'], 'failureId' => $failsubmissionure['failure_id']]
            );
        }
        foreach ($this->referenceSubmissions as $submission) {
            $this->connection->executeQuery(
                'UPDATE submission_failure SET reference_solution_submission_id = :submissionId WHERE id = :failureId',
                ['submissionId' => $submission['id'], 'failureId' => $failsubmissionure['failure_id']]
            );
        }
    }
}
