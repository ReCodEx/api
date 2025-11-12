<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251112225318 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE assignment_supplementary_exercise_file RENAME TO assignment_exercise_file');
        $this->addSql('ALTER TABLE exercise_supplementary_exercise_file RENAME TO exercise_exercise_file');
        $this->addSql('ALTER TABLE pipeline_supplementary_exercise_file RENAME TO pipeline_exercise_file');

        $this->addSql('ALTER TABLE assignment_exercise_file DROP FOREIGN KEY FK_D6457EA62D777971');
        $this->addSql('DROP INDEX IDX_D6457EA62D777971 ON assignment_exercise_file');
        $this->addSql('DROP INDEX `primary` ON assignment_exercise_file');
        $this->addSql('ALTER TABLE assignment_exercise_file CHANGE supplementary_exercise_file_id exercise_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE assignment_exercise_file ADD CONSTRAINT FK_1D217A6049DE8E29 FOREIGN KEY (exercise_file_id) REFERENCES `uploaded_file` (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_1D217A6049DE8E29 ON assignment_exercise_file (exercise_file_id)');
        $this->addSql('ALTER TABLE assignment_exercise_file ADD PRIMARY KEY (assignment_id, exercise_file_id)');
        $this->addSql('ALTER TABLE assignment_exercise_file RENAME INDEX idx_d6457ea6d19302f8 TO IDX_1D217A60D19302F8');
        $this->addSql('ALTER TABLE exercise_exercise_file DROP FOREIGN KEY FK_42359992D777971');
        $this->addSql('DROP INDEX IDX_42359992D777971 ON exercise_exercise_file');
        $this->addSql('DROP INDEX `primary` ON exercise_exercise_file');
        $this->addSql('ALTER TABLE exercise_exercise_file CHANGE supplementary_exercise_file_id exercise_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE exercise_exercise_file ADD CONSTRAINT FK_97E2547B49DE8E29 FOREIGN KEY (exercise_file_id) REFERENCES `uploaded_file` (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_97E2547B49DE8E29 ON exercise_exercise_file (exercise_file_id)');
        $this->addSql('ALTER TABLE exercise_exercise_file ADD PRIMARY KEY (exercise_id, exercise_file_id)');
        $this->addSql('ALTER TABLE exercise_exercise_file RENAME INDEX idx_4235999e934951a TO IDX_97E2547BE934951A');
        $this->addSql('ALTER TABLE pipeline_exercise_file DROP FOREIGN KEY FK_DCF572882D777971');
        $this->addSql('DROP INDEX IDX_DCF572882D777971 ON pipeline_exercise_file');
        $this->addSql('DROP INDEX `primary` ON pipeline_exercise_file');
        $this->addSql('ALTER TABLE pipeline_exercise_file CHANGE supplementary_exercise_file_id exercise_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE pipeline_exercise_file ADD CONSTRAINT FK_2A806B5C49DE8E29 FOREIGN KEY (exercise_file_id) REFERENCES `uploaded_file` (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_2A806B5C49DE8E29 ON pipeline_exercise_file (exercise_file_id)');
        $this->addSql('ALTER TABLE pipeline_exercise_file ADD PRIMARY KEY (pipeline_id, exercise_file_id)');
        $this->addSql('ALTER TABLE pipeline_exercise_file RENAME INDEX idx_dcf57288e80b93 TO IDX_2A806B5CE80B93');

        $this->addSql('UPDATE uploaded_file SET discriminator = \'exercisefile\' WHERE discriminator = \'supplementaryexercisefile\'');
    }

    public function down(Schema $schema): void
    {
        $this->addSql('UPDATE uploaded_file SET discriminator = \'supplementaryexercisefile\' WHERE discriminator = \'exercisefile\'');

        $this->addSql('ALTER TABLE assignment_exercise_file DROP FOREIGN KEY FK_1D217A6049DE8E29');
        $this->addSql('DROP INDEX IDX_1D217A6049DE8E29 ON assignment_exercise_file');
        $this->addSql('DROP INDEX `PRIMARY` ON assignment_exercise_file');
        $this->addSql('ALTER TABLE assignment_exercise_file CHANGE exercise_file_id supplementary_exercise_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE assignment_exercise_file ADD CONSTRAINT FK_D6457EA62D777971 FOREIGN KEY (supplementary_exercise_file_id) REFERENCES uploaded_file (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_D6457EA62D777971 ON assignment_exercise_file (supplementary_exercise_file_id)');
        $this->addSql('ALTER TABLE assignment_exercise_file ADD PRIMARY KEY (assignment_id, supplementary_exercise_file_id)');
        $this->addSql('ALTER TABLE assignment_exercise_file RENAME INDEX idx_1d217a60d19302f8 TO IDX_D6457EA6D19302F8');
        $this->addSql('ALTER TABLE exercise_exercise_file DROP FOREIGN KEY FK_97E2547B49DE8E29');
        $this->addSql('DROP INDEX IDX_97E2547B49DE8E29 ON exercise_exercise_file');
        $this->addSql('DROP INDEX `PRIMARY` ON exercise_exercise_file');
        $this->addSql('ALTER TABLE exercise_exercise_file CHANGE exercise_file_id supplementary_exercise_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE exercise_exercise_file ADD CONSTRAINT FK_42359992D777971 FOREIGN KEY (supplementary_exercise_file_id) REFERENCES uploaded_file (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_42359992D777971 ON exercise_exercise_file (supplementary_exercise_file_id)');
        $this->addSql('ALTER TABLE exercise_exercise_file ADD PRIMARY KEY (exercise_id, supplementary_exercise_file_id)');
        $this->addSql('ALTER TABLE exercise_exercise_file RENAME INDEX idx_97e2547be934951a TO IDX_4235999E934951A');
        $this->addSql('ALTER TABLE pipeline_exercise_file DROP FOREIGN KEY FK_2A806B5C49DE8E29');
        $this->addSql('DROP INDEX IDX_2A806B5C49DE8E29 ON pipeline_exercise_file');
        $this->addSql('DROP INDEX `PRIMARY` ON pipeline_exercise_file');
        $this->addSql('ALTER TABLE pipeline_exercise_file CHANGE exercise_file_id supplementary_exercise_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE pipeline_exercise_file ADD CONSTRAINT FK_DCF572882D777971 FOREIGN KEY (supplementary_exercise_file_id) REFERENCES uploaded_file (id) ON DELETE CASCADE');
        $this->addSql('CREATE INDEX IDX_DCF572882D777971 ON pipeline_exercise_file (supplementary_exercise_file_id)');
        $this->addSql('ALTER TABLE pipeline_exercise_file ADD PRIMARY KEY (pipeline_id, supplementary_exercise_file_id)');
        $this->addSql('ALTER TABLE pipeline_exercise_file RENAME INDEX idx_2a806b5ce80b93 TO IDX_DCF57288E80B93');

        $this->addSql('ALTER TABLE assignment_exercise_file RENAME TO assignment_supplementary_exercise_file');
        $this->addSql('ALTER TABLE exercise_exercise_file RENAME TO exercise_supplementary_exercise_file');
        $this->addSql('ALTER TABLE pipeline_exercise_file RENAME TO pipeline_supplementary_exercise_file');
    }
}
