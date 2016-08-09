<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20160809113037 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE comment DROP INDEX user_id, ADD UNIQUE INDEX UNIQ_9474526CA76ED395 (user_id)');
        $this->addSql('ALTER TABLE comment CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE comment_thread_id comment_thread_id VARCHAR(255) DEFAULT NULL, CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE text text VARCHAR(255) NOT NULL, ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CBEEA14F FOREIGN KEY (comment_thread_id) REFERENCES comment_thread (id)');
        $this->addSql('ALTER TABLE comment ADD CONSTRAINT FK_9474526CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE comment RENAME INDEX comment_thread_id TO IDX_9474526CBEEA14F');
        $this->addSql('ALTER TABLE comment_thread CHANGE id id VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE exercise DROP INDEX exercise_id, ADD UNIQUE INDEX UNIQ_AEDAD51CE934951A (exercise_id)');
        $this->addSql('ALTER TABLE exercise DROP FOREIGN KEY exercise_ibfk_1');
        $this->addSql('ALTER TABLE exercise DROP FOREIGN KEY exercise_ibfk_2');
        $this->addSql('ALTER TABLE exercise CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE exercise_id exercise_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE name name VARCHAR(255) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE difficulty difficulty VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51CE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id)');
        $this->addSql('ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE exercise RENAME INDEX user_id TO IDX_AEDAD51CA76ED395');
        $this->addSql('ALTER TABLE exercise_assignment CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE group_id group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE exercise_id exercise_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE name name VARCHAR(255) NOT NULL, CHANGE job_config_file_path job_config_file_path VARCHAR(255) NOT NULL, CHANGE description description LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE exercise_assignment ADD CONSTRAINT FK_70B498DAE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id)');
        $this->addSql('CREATE INDEX IDX_70B498DAE934951A ON exercise_assignment (exercise_id)');
        $this->addSql('ALTER TABLE exercise_assignment RENAME INDEX group_id TO IDX_70B498DAFE54D947');
        $this->addSql('ALTER TABLE `group` DROP FOREIGN KEY group_ibfk_1');
        $this->addSql('ALTER TABLE `group` ADD admin_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE parent_group_id parent_group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE instance_id instance_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE name name VARCHAR(255) NOT NULL, CHANGE description description VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE `group` ADD CONSTRAINT FK_6DC044C561997596 FOREIGN KEY (parent_group_id) REFERENCES `group` (id)');
        $this->addSql('ALTER TABLE `group` ADD CONSTRAINT FK_6DC044C5642B8210 FOREIGN KEY (admin_id) REFERENCES user (id)');
        $this->addSql('CREATE UNIQUE INDEX UNIQ_6DC044C5642B8210 ON `group` (admin_id)');
        $this->addSql('ALTER TABLE `group` RENAME INDEX parent_group_id TO IDX_6DC044C561997596');
        $this->addSql('ALTER TABLE `group` RENAME INDEX instance_id TO IDX_6DC044C53A51721D');
        $this->addSql('ALTER TABLE instance DROP INDEX user_id, ADD UNIQUE INDEX UNIQ_4230B1DE642B8210 (admin_id)');
        $this->addSql('ALTER TABLE instance CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE admin_id admin_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE name name VARCHAR(255) NOT NULL, CHANGE description description VARCHAR(255) NOT NULL, CHANGE created_at created_at DATETIME NOT NULL, CHANGE updated_at updated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE licence DROP FOREIGN KEY licence_ibfk_1');
        $this->addSql('ALTER TABLE licence CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE instance_id instance_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE name name VARCHAR(255) NOT NULL, CHANGE valid_until valid_until DATETIME NOT NULL, CHANGE note note VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE licence ADD CONSTRAINT FK_1DAAE6483A51721D FOREIGN KEY (instance_id) REFERENCES instance (id)');
        $this->addSql('ALTER TABLE licence RENAME INDEX instance_id TO IDX_1DAAE6483A51721D');
        $this->addSql('ALTER TABLE login DROP INDEX user_id, ADD UNIQUE INDEX UNIQ_AA08CB10A76ED395 (user_id)');
        $this->addSql('ALTER TABLE login DROP FOREIGN KEY login_ibfk_1');
        $this->addSql('ALTER TABLE login CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE username username VARCHAR(255) NOT NULL, CHANGE password_hash password_hash VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE login ADD CONSTRAINT FK_AA08CB10A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE permission CHANGE role_id role_id VARCHAR(255) DEFAULT NULL, CHANGE resource_id resource_id VARCHAR(255) DEFAULT NULL, CHANGE action action VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE permission ADD CONSTRAINT FK_E04992AAD60322AC FOREIGN KEY (role_id) REFERENCES role (id)');
        $this->addSql('ALTER TABLE permission ADD CONSTRAINT FK_E04992AA89329D25 FOREIGN KEY (resource_id) REFERENCES resource (id)');
        $this->addSql('ALTER TABLE permission RENAME INDEX role_id TO IDX_E04992AAD60322AC');
        $this->addSql('ALTER TABLE permission RENAME INDEX resource_id TO IDX_E04992AA89329D25');
        $this->addSql('ALTER TABLE resource CHANGE id id VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE role DROP FOREIGN KEY role_ibfk_1');
        $this->addSql('ALTER TABLE role CHANGE id id VARCHAR(255) NOT NULL, CHANGE parent_role_id parent_role_id VARCHAR(255) DEFAULT NULL');
        $this->addSql('ALTER TABLE role ADD CONSTRAINT FK_57698A6AA44B56EA FOREIGN KEY (parent_role_id) REFERENCES role (id)');
        $this->addSql('ALTER TABLE role RENAME INDEX parent_role_id TO IDX_57698A6AA44B56EA');
        $this->addSql('ALTER TABLE submission DROP INDEX submission_evaluation_id, ADD UNIQUE INDEX UNIQ_DB055AF3784926AC (submission_evaluation_id)');
        $this->addSql('ALTER TABLE submission DROP FOREIGN KEY submission_ibfk_2');
        $this->addSql('ALTER TABLE submission DROP FOREIGN KEY submission_ibfk_3');
        $this->addSql('ALTER TABLE submission CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE exercise_assignment_id exercise_assignment_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE submission_evaluation_id submission_evaluation_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE note note LONGTEXT NOT NULL, CHANGE hardware_group hardware_group VARCHAR(255) NOT NULL, CHANGE results_url results_url VARCHAR(255) NOT NULL, CHANGE submitted_at submitted_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE submission ADD CONSTRAINT FK_DB055AF3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE submission ADD CONSTRAINT FK_DB055AF3784926AC FOREIGN KEY (submission_evaluation_id) REFERENCES submission_evaluation (id)');
        $this->addSql('ALTER TABLE submission RENAME INDEX exercise_assignment_id TO IDX_DB055AF393CB0221');
        $this->addSql('ALTER TABLE submission RENAME INDEX user_id TO IDX_DB055AF3A76ED395');
        $this->addSql('ALTER TABLE submission_evaluation ADD init_failed TINYINT(1) NOT NULL, CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE points points SMALLINT NOT NULL, CHANGE evaluated_at evaluated_at DATETIME NOT NULL');
        $this->addSql('ALTER TABLE test_result DROP FOREIGN KEY test_result_ibfk_1');
        $this->addSql('ALTER TABLE test_result CHANGE submission_evaluation_id submission_evaluation_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE test_name test_name VARCHAR(255) NOT NULL, CHANGE status status VARCHAR(255) NOT NULL, CHANGE exit_code exit_code TINYINT(1) NOT NULL, CHANGE message message VARCHAR(255) NOT NULL, CHANGE stats stats VARCHAR(255) NOT NULL, CHANGE judge_output judge_output VARCHAR(255) NOT NULL');
        $this->addSql('ALTER TABLE test_result ADD CONSTRAINT FK_84B3C63D784926AC FOREIGN KEY (submission_evaluation_id) REFERENCES submission_evaluation (id)');
        $this->addSql('ALTER TABLE test_result RENAME INDEX submission_evaluation_id TO IDX_84B3C63D784926AC');
        $this->addSql('ALTER TABLE uploaded_file DROP FOREIGN KEY uploaded_file_ibfk_3');
        $this->addSql('ALTER TABLE uploaded_file DROP FOREIGN KEY uploaded_file_ibfk_4');
        $this->addSql('ALTER TABLE uploaded_file CHANGE submission_id submission_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE name name VARCHAR(255) NOT NULL, CHANGE file_path file_path VARCHAR(255) NOT NULL, ADD PRIMARY KEY (id)');
        $this->addSql('ALTER TABLE uploaded_file ADD CONSTRAINT FK_B40DF75DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE uploaded_file ADD CONSTRAINT FK_B40DF75DE1FD4933 FOREIGN KEY (submission_id) REFERENCES submission (id)');
        $this->addSql('ALTER TABLE uploaded_file RENAME INDEX user_id TO IDX_B40DF75DA76ED395');
        $this->addSql('ALTER TABLE uploaded_file RENAME INDEX submission_id TO IDX_B40DF75DE1FD4933');
        $this->addSql('DROP INDEX email ON user');
        $this->addSql('ALTER TABLE user CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE role_id role_id VARCHAR(255) DEFAULT NULL, CHANGE degrees_before_name degrees_before_name VARCHAR(255) NOT NULL, CHANGE first_name first_name VARCHAR(255) NOT NULL, CHANGE last_name last_name VARCHAR(255) NOT NULL, CHANGE degrees_after_name degrees_after_name VARCHAR(255) NOT NULL, CHANGE email email VARCHAR(255) NOT NULL, CHANGE avatar_url avatar_url VARCHAR(255) NOT NULL, CHANGE is_verified is_verified TINYINT(1) NOT NULL');
        $this->addSql('ALTER TABLE user RENAME INDEX role_id TO IDX_8D93D649D60322AC');
        $this->addSql('ALTER TABLE group_student DROP FOREIGN KEY group_student_ibfk_1');
        $this->addSql('ALTER TABLE group_student CHANGE group_id group_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', ADD PRIMARY KEY (user_id, group_id)');
        $this->addSql('ALTER TABLE group_student ADD CONSTRAINT FK_3123FB3FFE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_student RENAME INDEX user_id TO IDX_3123FB3FA76ED395');
        $this->addSql('ALTER TABLE group_student RENAME INDEX group_id TO IDX_3123FB3FFE54D947');
        $this->addSql('ALTER TABLE group_supervisor DROP FOREIGN KEY group_supervisor_ibfk_1');
        $this->addSql('ALTER TABLE group_supervisor CHANGE group_id group_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', ADD PRIMARY KEY (user_id, group_id)');
        $this->addSql('ALTER TABLE group_supervisor ADD CONSTRAINT FK_9A5CF34AFE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE group_supervisor RENAME INDEX user_id TO IDX_9A5CF34AA76ED395');
        $this->addSql('ALTER TABLE group_supervisor RENAME INDEX group_id TO IDX_9A5CF34AFE54D947');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE comment DROP INDEX UNIQ_9474526CA76ED395, ADD INDEX user_id (user_id)');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CBEEA14F');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CA76ED395');
        $this->addSql('ALTER TABLE comment DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE comment CHANGE id id VARCHAR(60) NOT NULL COLLATE utf8_general_ci, CHANGE comment_thread_id comment_thread_id VARCHAR(60) NOT NULL COLLATE utf8_general_ci, CHANGE user_id user_id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE text text TEXT NOT NULL COLLATE utf8_general_ci');
        $this->addSql('ALTER TABLE comment RENAME INDEX idx_9474526cbeea14f TO comment_thread_id');
        $this->addSql('ALTER TABLE comment_thread CHANGE id id VARCHAR(60) NOT NULL COLLATE utf8_general_ci');
        $this->addSql('ALTER TABLE exercise DROP INDEX UNIQ_AEDAD51CE934951A, ADD INDEX exercise_id (exercise_id)');
        $this->addSql('ALTER TABLE exercise DROP FOREIGN KEY FK_AEDAD51CE934951A');
        $this->addSql('ALTER TABLE exercise DROP FOREIGN KEY FK_AEDAD51CA76ED395');
        $this->addSql('ALTER TABLE exercise CHANGE id id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE exercise_id exercise_id VARCHAR(36) DEFAULT NULL COLLATE utf8_general_ci, CHANGE user_id user_id VARCHAR(36) DEFAULT NULL COLLATE utf8_general_ci, CHANGE name name VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE difficulty difficulty VARCHAR(20) NOT NULL COLLATE utf8_general_ci');
        $this->addSql('ALTER TABLE exercise ADD CONSTRAINT exercise_ibfk_1 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE exercise ADD CONSTRAINT exercise_ibfk_2 FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE exercise RENAME INDEX idx_aedad51ca76ed395 TO user_id');
        $this->addSql('ALTER TABLE exercise_assignment DROP FOREIGN KEY FK_70B498DAE934951A');
        $this->addSql('DROP INDEX IDX_70B498DAE934951A ON exercise_assignment');
        $this->addSql('ALTER TABLE exercise_assignment CHANGE id id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE exercise_id exercise_id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE group_id group_id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE name name VARCHAR(100) NOT NULL COLLATE utf8_general_ci, CHANGE job_config_file_path job_config_file_path VARCHAR(100) NOT NULL COLLATE utf8_general_ci, CHANGE description description TEXT DEFAULT NULL COLLATE utf8_general_ci');
        $this->addSql('ALTER TABLE exercise_assignment RENAME INDEX idx_70b498dafe54d947 TO group_id');
        $this->addSql('ALTER TABLE `group` DROP FOREIGN KEY FK_6DC044C561997596');
        $this->addSql('ALTER TABLE `group` DROP FOREIGN KEY FK_6DC044C5642B8210');
        $this->addSql('DROP INDEX UNIQ_6DC044C5642B8210 ON `group`');
        $this->addSql('ALTER TABLE `group` DROP admin_id, CHANGE id id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE parent_group_id parent_group_id VARCHAR(36) DEFAULT NULL COLLATE utf8_general_ci, CHANGE instance_id instance_id VARCHAR(36) DEFAULT NULL COLLATE utf8_general_ci, CHANGE name name VARCHAR(50) NOT NULL COLLATE utf8_general_ci, CHANGE description description TEXT NOT NULL COLLATE utf8_general_ci');
        $this->addSql('ALTER TABLE `group` ADD CONSTRAINT group_ibfk_1 FOREIGN KEY (parent_group_id) REFERENCES `group` (id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `group` RENAME INDEX idx_6dc044c561997596 TO parent_group_id');
        $this->addSql('ALTER TABLE `group` RENAME INDEX idx_6dc044c53a51721d TO instance_id');
        $this->addSql('ALTER TABLE group_student DROP FOREIGN KEY FK_3123FB3FFE54D947');
        $this->addSql('ALTER TABLE group_student DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE group_student CHANGE user_id user_id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE group_id group_id VARCHAR(36) NOT NULL COLLATE utf8_general_ci');
        $this->addSql('ALTER TABLE group_student ADD CONSTRAINT group_student_ibfk_1 FOREIGN KEY (group_id) REFERENCES `group` (id)');
        $this->addSql('ALTER TABLE group_student RENAME INDEX idx_3123fb3ffe54d947 TO group_id');
        $this->addSql('ALTER TABLE group_student RENAME INDEX idx_3123fb3fa76ed395 TO user_id');
        $this->addSql('ALTER TABLE group_supervisor DROP FOREIGN KEY FK_9A5CF34AFE54D947');
        $this->addSql('ALTER TABLE group_supervisor DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE group_supervisor CHANGE user_id user_id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE group_id group_id VARCHAR(36) NOT NULL COLLATE utf8_general_ci');
        $this->addSql('ALTER TABLE group_supervisor ADD CONSTRAINT group_supervisor_ibfk_1 FOREIGN KEY (group_id) REFERENCES `group` (id)');
        $this->addSql('ALTER TABLE group_supervisor RENAME INDEX idx_9a5cf34afe54d947 TO group_id');
        $this->addSql('ALTER TABLE group_supervisor RENAME INDEX idx_9a5cf34aa76ed395 TO user_id');
        $this->addSql('ALTER TABLE instance DROP INDEX UNIQ_4230B1DE642B8210, ADD INDEX user_id (admin_id)');
        $this->addSql('ALTER TABLE instance CHANGE id id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE admin_id admin_id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE name name VARCHAR(100) NOT NULL COLLATE utf8_general_ci, CHANGE description description TEXT NOT NULL COLLATE utf8_general_ci, CHANGE created_at created_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE updated_at updated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL');
        $this->addSql('ALTER TABLE licence DROP FOREIGN KEY FK_1DAAE6483A51721D');
        $this->addSql('ALTER TABLE licence CHANGE id id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE instance_id instance_id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE name name VARCHAR(50) NOT NULL COLLATE utf8_general_ci, CHANGE valid_until valid_until DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE note note VARCHAR(300) NOT NULL COLLATE utf8_general_ci');
        $this->addSql('ALTER TABLE licence ADD CONSTRAINT licence_ibfk_1 FOREIGN KEY (instance_id) REFERENCES instance (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE licence RENAME INDEX idx_1daae6483a51721d TO instance_id');
        $this->addSql('ALTER TABLE login DROP INDEX UNIQ_AA08CB10A76ED395, ADD INDEX user_id (user_id)');
        $this->addSql('ALTER TABLE login DROP FOREIGN KEY FK_AA08CB10A76ED395');
        $this->addSql('ALTER TABLE login CHANGE id id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE user_id user_id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE username username VARCHAR(100) NOT NULL COLLATE utf8_general_ci, CHANGE password_hash password_hash VARCHAR(100) NOT NULL COLLATE utf8_general_ci');
        $this->addSql('ALTER TABLE login ADD CONSTRAINT login_ibfk_1 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE permission DROP FOREIGN KEY FK_E04992AAD60322AC');
        $this->addSql('ALTER TABLE permission DROP FOREIGN KEY FK_E04992AA89329D25');
        $this->addSql('ALTER TABLE permission CHANGE role_id role_id VARCHAR(50) NOT NULL COLLATE utf8_general_ci, CHANGE resource_id resource_id VARCHAR(50) NOT NULL COLLATE utf8_general_ci, CHANGE action action VARCHAR(50) NOT NULL COLLATE utf8_general_ci');
        $this->addSql('ALTER TABLE permission RENAME INDEX idx_e04992aad60322ac TO role_id');
        $this->addSql('ALTER TABLE permission RENAME INDEX idx_e04992aa89329d25 TO resource_id');
        $this->addSql('ALTER TABLE resource CHANGE id id VARCHAR(50) NOT NULL COLLATE utf8_general_ci');
        $this->addSql('ALTER TABLE role DROP FOREIGN KEY FK_57698A6AA44B56EA');
        $this->addSql('ALTER TABLE role CHANGE id id VARCHAR(50) NOT NULL COLLATE utf8_general_ci, CHANGE parent_role_id parent_role_id VARCHAR(50) DEFAULT NULL COLLATE utf8_general_ci');
        $this->addSql('ALTER TABLE role ADD CONSTRAINT role_ibfk_1 FOREIGN KEY (parent_role_id) REFERENCES role (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE role RENAME INDEX idx_57698a6aa44b56ea TO parent_role_id');
        $this->addSql('ALTER TABLE submission DROP INDEX UNIQ_DB055AF3784926AC, ADD INDEX submission_evaluation_id (submission_evaluation_id)');
        $this->addSql('ALTER TABLE submission DROP FOREIGN KEY FK_DB055AF3A76ED395');
        $this->addSql('ALTER TABLE submission DROP FOREIGN KEY FK_DB055AF3784926AC');
        $this->addSql('ALTER TABLE submission CHANGE id id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE exercise_assignment_id exercise_assignment_id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE user_id user_id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE submission_evaluation_id submission_evaluation_id VARCHAR(36) DEFAULT NULL COLLATE utf8_general_ci, CHANGE submitted_at submitted_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE note note VARCHAR(300) NOT NULL COLLATE utf8_general_ci, CHANGE results_url results_url VARCHAR(300) DEFAULT NULL COLLATE utf8_general_ci, CHANGE hardware_group hardware_group VARCHAR(30) NOT NULL COLLATE utf8_general_ci');
        $this->addSql('ALTER TABLE submission ADD CONSTRAINT submission_ibfk_2 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE submission ADD CONSTRAINT submission_ibfk_3 FOREIGN KEY (submission_evaluation_id) REFERENCES submission_evaluation (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE submission RENAME INDEX idx_db055af393cb0221 TO exercise_assignment_id');
        $this->addSql('ALTER TABLE submission RENAME INDEX idx_db055af3a76ed395 TO user_id');
        $this->addSql('ALTER TABLE submission_evaluation DROP init_failed, CHANGE id id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE evaluated_at evaluated_at DATETIME DEFAULT CURRENT_TIMESTAMP NOT NULL, CHANGE points points INT NOT NULL');
        $this->addSql('ALTER TABLE test_result DROP FOREIGN KEY FK_84B3C63D784926AC');
        $this->addSql('ALTER TABLE test_result CHANGE submission_evaluation_id submission_evaluation_id VARCHAR(60) NOT NULL COLLATE utf8_general_ci, CHANGE test_name test_name VARCHAR(30) NOT NULL COLLATE utf8_general_ci, CHANGE status status VARCHAR(20) NOT NULL COLLATE utf8_general_ci, CHANGE exit_code exit_code INT NOT NULL, CHANGE message message TEXT NOT NULL COLLATE utf8_general_ci, CHANGE stats stats TEXT NOT NULL COLLATE utf8_general_ci, CHANGE judge_output judge_output TEXT NOT NULL COLLATE utf8_general_ci');
        $this->addSql('ALTER TABLE test_result ADD CONSTRAINT test_result_ibfk_1 FOREIGN KEY (submission_evaluation_id) REFERENCES submission_evaluation (id) ON UPDATE CASCADE ON DELETE CASCADE');
        $this->addSql('ALTER TABLE test_result RENAME INDEX idx_84b3c63d784926ac TO submission_evaluation_id');
        $this->addSql('ALTER TABLE `uploaded_file` DROP FOREIGN KEY FK_B40DF75DA76ED395');
        $this->addSql('ALTER TABLE `uploaded_file` DROP FOREIGN KEY FK_B40DF75DE1FD4933');
        $this->addSql('ALTER TABLE `uploaded_file` DROP PRIMARY KEY');
        $this->addSql('ALTER TABLE `uploaded_file` CHANGE id id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE user_id user_id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE submission_id submission_id VARCHAR(36) DEFAULT NULL COLLATE utf8_general_ci, CHANGE name name VARCHAR(100) NOT NULL COLLATE utf8_general_ci, CHANGE file_path file_path VARCHAR(200) NOT NULL COLLATE utf8_general_ci');
        $this->addSql('ALTER TABLE `uploaded_file` ADD CONSTRAINT uploaded_file_ibfk_3 FOREIGN KEY (submission_id) REFERENCES submission (id) ON UPDATE CASCADE ON DELETE SET NULL');
        $this->addSql('ALTER TABLE `uploaded_file` ADD CONSTRAINT uploaded_file_ibfk_4 FOREIGN KEY (user_id) REFERENCES user (id) ON UPDATE CASCADE');
        $this->addSql('ALTER TABLE `uploaded_file` RENAME INDEX idx_b40df75de1fd4933 TO submission_id');
        $this->addSql('ALTER TABLE `uploaded_file` RENAME INDEX idx_b40df75da76ed395 TO user_id');
        $this->addSql('ALTER TABLE user CHANGE id id VARCHAR(36) NOT NULL COLLATE utf8_general_ci, CHANGE role_id role_id VARCHAR(50) NOT NULL COLLATE utf8_general_ci, CHANGE degrees_before_name degrees_before_name VARCHAR(50) NOT NULL COLLATE utf8_general_ci, CHANGE first_name first_name VARCHAR(50) NOT NULL COLLATE utf8_general_ci, CHANGE last_name last_name VARCHAR(50) NOT NULL COLLATE utf8_general_ci, CHANGE degrees_after_name degrees_after_name VARCHAR(50) NOT NULL COLLATE utf8_general_ci, CHANGE email email VARCHAR(50) NOT NULL COLLATE utf8_general_ci, CHANGE avatar_url avatar_url VARCHAR(200) NOT NULL COLLATE utf8_general_ci, CHANGE is_verified is_verified TINYINT(1) DEFAULT \'0\' NOT NULL');
        $this->addSql('CREATE UNIQUE INDEX email ON user (email)');
        $this->addSql('ALTER TABLE user RENAME INDEX idx_8d93d649d60322ac TO role_id');
    }
}
