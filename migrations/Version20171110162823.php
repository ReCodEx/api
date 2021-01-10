<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171110162823 extends AbstractMigration
{
    private $assignmentSolutionsToBeDeleted = [];

    public function preUp(Schema $schema): void
    {
        $result = $this->connection->executeQuery(
            'SELECT id, original_submission_id FROM assignment_solution WHERE original_submission_id IS NOT NULL'
        );
        foreach ($result as $row) {
            $originalId = null;
            $parentId = $row["original_submission_id"];
            while (true) {
                $parent = $this->connection->executeQuery(
                    "SELECT * FROM assignment_solution WHERE id = '{$parentId}'"
                )->fetchAssociative();
                $originalId = $parent["id"];
                $parentId = $parent["original_submission_id"];
                if (empty($parentId)) {
                    break;
                }
            }

            $this->connection->executeQuery(
                "UPDATE `assignment_solution_submission` SET `assignment_solution_id`='$originalId' WHERE `assignment_solution_id`='{$row["id"]}'"
            );
            $this->assignmentSolutionsToBeDeleted[] = $row["id"];
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

        $this->addSql('ALTER TABLE assignment_solution DROP FOREIGN KEY FK_DB055AF32E9C906C');
        $this->addSql('ALTER TABLE assignment_solution DROP FOREIGN KEY FK_DB055AF3456C5646');
        $this->addSql('ALTER TABLE assignment_solution DROP FOREIGN KEY FK_DB055AF379F7D87D');
        $this->addSql('DROP INDEX UNIQ_5B315D2E456C5646 ON assignment_solution');
        $this->addSql('DROP INDEX IDX_5B315D2E2E9C906C ON assignment_solution');
        $this->addSql('DROP INDEX IDX_5B315D2E79F7D87D ON assignment_solution');
        $this->addSql(
            'ALTER TABLE assignment_solution DROP original_submission_id, DROP submitted_by_id, DROP evaluation_id, DROP submitted_at, DROP results_url, DROP job_config_path'
        );
        $this->addSql('ALTER TABLE submission_failure DROP FOREIGN KEY FK_D7A9817A598DA2');
        $this->addSql('DROP INDEX IDX_D7A9817A598DA2 ON submission_failure');
        $this->addSql('ALTER TABLE submission_failure DROP assignment_solution_id');
    }

    public function postUp(Schema $schema): void
    {
        $this->connection->beginTransaction();

        // delete all resubmits which are now represented as AssignmentSolutionSubmission entity
        foreach ($this->assignmentSolutionsToBeDeleted as $solutionId) {
            $this->connection->executeQuery("DELETE FROM assignment_solution WHERE id = '{$solutionId}'");
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
