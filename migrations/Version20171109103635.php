<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171109103635 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE assignment_solution DROP FOREIGN KEY FK_DB055AF31C0BE183');
        $this->addSql('ALTER TABLE assignment_solution DROP FOREIGN KEY FK_DB055AF32E9C906C');
        $this->addSql('ALTER TABLE assignment_solution DROP FOREIGN KEY FK_DB055AF3456C5646');
        $this->addSql('ALTER TABLE assignment_solution DROP FOREIGN KEY FK_DB055AF379F7D87D');
        $this->addSql('ALTER TABLE assignment_solution DROP FOREIGN KEY FK_DB055AF3D19302F8');
        $this->addSql('DROP INDEX idx_db055af3d19302f8 ON assignment_solution');
        $this->addSql('CREATE INDEX IDX_5B315D2ED19302F8 ON assignment_solution (assignment_id)');
        $this->addSql('DROP INDEX idx_db055af32e9c906c ON assignment_solution');
        $this->addSql('CREATE INDEX IDX_5B315D2E2E9C906C ON assignment_solution (original_submission_id)');
        $this->addSql('DROP INDEX idx_db055af379f7d87d ON assignment_solution');
        $this->addSql('CREATE INDEX IDX_5B315D2E79F7D87D ON assignment_solution (submitted_by_id)');
        $this->addSql('DROP INDEX idx_db055af31c0be183 ON assignment_solution');
        $this->addSql('CREATE INDEX IDX_5B315D2E1C0BE183 ON assignment_solution (solution_id)');
        $this->addSql('DROP INDEX uniq_db055af3456c5646 ON assignment_solution');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_5B315D2E456C5646 ON assignment_solution (evaluation_id)');
        $this->addSql('ALTER TABLE assignment_solution ADD CONSTRAINT FK_DB055AF31C0BE183 FOREIGN KEY (solution_id) REFERENCES solution (id)');
        $this->addSql('ALTER TABLE assignment_solution ADD CONSTRAINT FK_DB055AF32E9C906C FOREIGN KEY (original_submission_id) REFERENCES assignment_solution (id)');
        $this->addSql('ALTER TABLE assignment_solution ADD CONSTRAINT FK_DB055AF3456C5646 FOREIGN KEY (evaluation_id) REFERENCES solution_evaluation (id)');
        $this->addSql('ALTER TABLE assignment_solution ADD CONSTRAINT FK_DB055AF379F7D87D FOREIGN KEY (submitted_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE assignment_solution ADD CONSTRAINT FK_DB055AF3D19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id)');
        $this->addSql('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_62BA741F456C5646');
        $this->addSql('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_62BA741F79F7D87D');
        $this->addSql('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_62BA741FA398D0F9');
        $this->addSql('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_62BA741FFA3CA3B7');
        $this->addSql('DROP INDEX idx_62ba741f79f7d87d ON reference_solution_submission');
        $this->addSql('CREATE INDEX IDX_AA9C8B9979F7D87D ON reference_solution_submission (submitted_by_id)');
        $this->addSql('DROP INDEX idx_62ba741ffa3ca3b7 ON reference_solution_submission');
        $this->addSql('CREATE INDEX IDX_AA9C8B99FA3CA3B7 ON reference_solution_submission (reference_solution_id)');
        $this->addSql('DROP INDEX idx_62ba741fa398d0f9 ON reference_solution_submission');
        $this->addSql('CREATE INDEX IDX_AA9C8B99A398D0F9 ON reference_solution_submission (hw_group_id)');
        $this->addSql('DROP INDEX uniq_62ba741f456c5646 ON reference_solution_submission');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_AA9C8B99456C5646 ON reference_solution_submission (evaluation_id)');
        $this->addSql('ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_62BA741F456C5646 FOREIGN KEY (evaluation_id) REFERENCES solution_evaluation (id)');
        $this->addSql('ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_62BA741F79F7D87D FOREIGN KEY (submitted_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_62BA741FA398D0F9 FOREIGN KEY (hw_group_id) REFERENCES hardware_group (id)');
        $this->addSql('ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_62BA741FFA3CA3B7 FOREIGN KEY (reference_solution_id) REFERENCES reference_exercise_solution (id)');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
