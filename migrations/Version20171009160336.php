<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171009160336 extends AbstractMigration
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

        $this->addSql(
            'CREATE TABLE assignment (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', exercise_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', exercise_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(255) NOT NULL, version INT NOT NULL, is_public TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', is_bonus TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', points_percentual_threshold DOUBLE PRECISION NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, submissions_count_limit SMALLINT NOT NULL, score_calculator VARCHAR(255) DEFAULT NULL, score_config LONGTEXT DEFAULT NULL, first_deadline DATETIME NOT NULL, allow_second_deadline TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', second_deadline DATETIME NOT NULL, max_points_before_first_deadline SMALLINT NOT NULL, max_points_before_second_deadline SMALLINT NOT NULL, can_view_limit_ratios TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', INDEX IDX_30C544BA8A0C0069 (exercise_config_id), INDEX IDX_30C544BAE934951A (exercise_id), INDEX IDX_30C544BAFE54D947 (group_id), INDEX first_deadline_idx (first_deadline), INDEX second_deadline_idx (second_deadline), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE assignment_runtime_environment (assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', runtime_environment_id VARCHAR(255) NOT NULL, INDEX IDX_6AA32A86D19302F8 (assignment_id), INDEX IDX_6AA32A86C9F479A7 (runtime_environment_id), PRIMARY KEY(assignment_id, runtime_environment_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE assignment_hardware_group (assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', hardware_group_id VARCHAR(255) NOT NULL, INDEX IDX_DEC8DE33D19302F8 (assignment_id), INDEX IDX_DEC8DE3323F56800 (hardware_group_id), PRIMARY KEY(assignment_id, hardware_group_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE assignment_exercise_limits (assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', exercise_limits_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_696DD48D19302F8 (assignment_id), INDEX IDX_696DD48146FFD8C (exercise_limits_id), PRIMARY KEY(assignment_id, exercise_limits_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE assignment_exercise_environment_config (assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', exercise_environment_config_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_E54F7F2BD19302F8 (assignment_id), INDEX IDX_E54F7F2B3D5429CE (exercise_environment_config_id), PRIMARY KEY(assignment_id, exercise_environment_config_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE assignment_localized_text (assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', localized_text_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_9C8F78CD19302F8 (assignment_id), INDEX IDX_9C8F78CA9B14E11 (localized_text_id), PRIMARY KEY(assignment_id, localized_text_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE comment (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', comment_thread_id VARCHAR(255) DEFAULT NULL, user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', is_private TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', posted_at DATETIME NOT NULL, text VARCHAR(255) NOT NULL, INDEX IDX_9474526CBEEA14F (comment_thread_id), INDEX IDX_9474526CA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE comment_thread (id VARCHAR(255) NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE exercise (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', exercise_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', exercise_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(255) NOT NULL, version INT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, difficulty VARCHAR(255) NOT NULL, is_public TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', is_locked TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', description LONGTEXT NOT NULL, INDEX IDX_AEDAD51CE934951A (exercise_id), INDEX IDX_AEDAD51CF675F31B (author_id), INDEX IDX_AEDAD51CFE54D947 (group_id), INDEX IDX_AEDAD51C8A0C0069 (exercise_config_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE exercise_localized_text (exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', localized_text_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_8327FD4EE934951A (exercise_id), INDEX IDX_8327FD4EA9B14E11 (localized_text_id), PRIMARY KEY(exercise_id, localized_text_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE exercise_runtime_environment (exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', runtime_environment_id VARCHAR(255) NOT NULL, INDEX IDX_F5199605E934951A (exercise_id), INDEX IDX_F5199605C9F479A7 (runtime_environment_id), PRIMARY KEY(exercise_id, runtime_environment_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE exercise_hardware_group (exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', hardware_group_id VARCHAR(255) NOT NULL, INDEX IDX_5427D4F1E934951A (exercise_id), INDEX IDX_5427D4F123F56800 (hardware_group_id), PRIMARY KEY(exercise_id, hardware_group_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE exercise_supplementary_exercise_file (exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', supplementary_exercise_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_4235999E934951A (exercise_id), INDEX IDX_42359992D777971 (supplementary_exercise_file_id), PRIMARY KEY(exercise_id, supplementary_exercise_file_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE exercise_additional_exercise_file (exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', additional_exercise_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_C0EAC68CE934951A (exercise_id), INDEX IDX_C0EAC68CED6C0B59 (additional_exercise_file_id), PRIMARY KEY(exercise_id, additional_exercise_file_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE exercise_exercise_limits (exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', exercise_limits_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_737691DEE934951A (exercise_id), INDEX IDX_737691DE146FFD8C (exercise_limits_id), PRIMARY KEY(exercise_id, exercise_limits_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE exercise_exercise_environment_config (exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', exercise_environment_config_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_37295814E934951A (exercise_id), INDEX IDX_372958143D5429CE (exercise_environment_config_id), PRIMARY KEY(exercise_id, exercise_environment_config_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE exercise_config (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', config LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_A1C5BA173EA4CB4D (created_from_id), INDEX IDX_A1C5BA17F675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE exercise_environment_config (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', runtime_environment_id VARCHAR(255) DEFAULT NULL, created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', variables_table LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_89540FF7C9F479A7 (runtime_environment_id), INDEX IDX_89540FF73EA4CB4D (created_from_id), INDEX IDX_89540FF7F675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE exercise_limits (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', runtime_environment_id VARCHAR(255) DEFAULT NULL, hardware_group_id VARCHAR(255) DEFAULT NULL, limits LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_238CADD03EA4CB4D (created_from_id), INDEX IDX_238CADD0F675F31B (author_id), INDEX IDX_238CADD0C9F479A7 (runtime_environment_id), INDEX IDX_238CADD023F56800 (hardware_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE external_login (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', auth_service VARCHAR(255) NOT NULL, external_id VARCHAR(255) NOT NULL, INDEX IDX_9845B893A76ED395 (user_id), UNIQUE INDEX UNIQ_9845B893AFB9BA219F75D7B0 (auth_service, external_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE forgotten_password (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', requested_at DATETIME NOT NULL, sent_to VARCHAR(255) NOT NULL, redirect_url VARCHAR(255) NOT NULL, ipaddress VARCHAR(255) NOT NULL, INDEX IDX_2EDC8D24A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE `group` (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', parent_group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', instance_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', admin_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(255) NOT NULL, external_id VARCHAR(255) DEFAULT NULL, description LONGTEXT NOT NULL, threshold DOUBLE PRECISION DEFAULT NULL, public_stats TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', is_public TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', deleted_at DATETIME DEFAULT NULL, INDEX IDX_6DC044C561997596 (parent_group_id), INDEX IDX_6DC044C53A51721D (instance_id), INDEX IDX_6DC044C5642B8210 (admin_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE group_membership (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', status VARCHAR(255) NOT NULL, type VARCHAR(255) NOT NULL, requested_at DATETIME DEFAULT NULL, joined_at DATETIME DEFAULT NULL, rejected_at DATETIME DEFAULT NULL, student_since DATETIME DEFAULT NULL, supervisor_since DATETIME DEFAULT NULL, INDEX IDX_5132B337A76ED395 (user_id), INDEX IDX_5132B337FE54D947 (group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE hardware_group (id VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE hardware_group_availability_log (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', hardware_group_id VARCHAR(255) DEFAULT NULL, is_available TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', logged_at DATETIME NOT NULL, description LONGTEXT NOT NULL, INDEX IDX_C6835B1523F56800 (hardware_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE instance (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', admin_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', root_group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(255) NOT NULL, description LONGTEXT DEFAULT NULL, is_open TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', is_allowed TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, needs_licence TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', INDEX IDX_4230B1DE642B8210 (admin_id), INDEX IDX_4230B1DE8509B3A1 (root_group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE licence (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', instance_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', is_valid TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', valid_until DATETIME NOT NULL, note VARCHAR(255) NOT NULL, INDEX IDX_1DAAE6483A51721D (instance_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE localized_text (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', locale VARCHAR(255) NOT NULL, short_text VARCHAR(255) DEFAULT NULL, text LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_7C6ACF3E3EA4CB4D (created_from_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE login (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', username VARCHAR(255) NOT NULL, password_hash VARCHAR(255) NOT NULL, UNIQUE INDEX UNIQ_AA08CB10F85E0677 (username), INDEX IDX_AA08CB10A76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE pipeline (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', pipeline_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', exercise_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(255) NOT NULL, version INT NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME NOT NULL, updated_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, INDEX IDX_7DFCD9D9579C4491 (pipeline_config_id), INDEX IDX_7DFCD9D9F675F31B (author_id), INDEX IDX_7DFCD9D93EA4CB4D (created_from_id), INDEX IDX_7DFCD9D9E934951A (exercise_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE pipeline_supplementary_exercise_file (pipeline_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', supplementary_exercise_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_DCF57288E80B93 (pipeline_id), INDEX IDX_DCF572882D777971 (supplementary_exercise_file_id), PRIMARY KEY(pipeline_id, supplementary_exercise_file_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE pipeline_config (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', pipeline_config LONGTEXT NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_32420693F675F31B (author_id), INDEX IDX_324206933EA4CB4D (created_from_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE reference_exercise_solution (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', exercise_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', uploaded_at DATETIME NOT NULL, description LONGTEXT NOT NULL, INDEX IDX_E414ABABE934951A (exercise_id), UNIQUE INDEX UNIQ_E414ABAB1C0BE183 (solution_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE reference_solution_evaluation (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', reference_solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', hw_group_id VARCHAR(255) DEFAULT NULL, evaluation_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', results_url VARCHAR(255) DEFAULT NULL, job_config_path VARCHAR(255) NOT NULL, INDEX IDX_62BA741FFA3CA3B7 (reference_solution_id), INDEX IDX_62BA741FA398D0F9 (hw_group_id), UNIQUE INDEX UNIQ_62BA741F456C5646 (evaluation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE reported_errors (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', type VARCHAR(255) NOT NULL, recipients VARCHAR(255) NOT NULL, subject VARCHAR(255) NOT NULL, sent_at DATETIME NOT NULL, description LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE runtime_environment (id VARCHAR(255) NOT NULL, name VARCHAR(255) NOT NULL, language VARCHAR(255) NOT NULL, extensions VARCHAR(255) NOT NULL, platform VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, default_variables LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE sis_group_binding (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', code VARCHAR(255) NOT NULL, INDEX IDX_A6670D06FE54D947 (group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE sis_valid_term (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', year INT NOT NULL, term INT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE solution (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', runtime_environment_id VARCHAR(255) DEFAULT NULL, evaluated TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', INDEX IDX_9F3329DBA76ED395 (user_id), INDEX IDX_9F3329DBC9F479A7 (runtime_environment_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE solution_evaluation (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', evaluated_at DATETIME NOT NULL, init_failed TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', score DOUBLE PRECISION NOT NULL, points INT NOT NULL, bonus_points INT DEFAULT NULL, is_valid TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', evaluation_failed TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', result_yml LONGTEXT NOT NULL, initiation_outputs LONGTEXT NOT NULL, PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE submission (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', assignment_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', original_submission_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', submitted_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', evaluation_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', submitted_at DATETIME NOT NULL, note LONGTEXT NOT NULL, results_url VARCHAR(255) DEFAULT NULL, job_config_path VARCHAR(255) NOT NULL, private TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', asynchronous TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', accepted TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', INDEX IDX_DB055AF3D19302F8 (assignment_id), INDEX IDX_DB055AF32E9C906C (original_submission_id), INDEX IDX_DB055AF3A76ED395 (user_id), INDEX IDX_DB055AF379F7D87D (submitted_by_id), INDEX IDX_DB055AF31C0BE183 (solution_id), UNIQUE INDEX UNIQ_DB055AF3456C5646 (evaluation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE submission_failure (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', submission_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', type VARCHAR(255) NOT NULL, description VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL, resolved_at DATETIME DEFAULT NULL, resolution_note VARCHAR(255) DEFAULT NULL, INDEX IDX_D7A9817E1FD4933 (submission_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE task_result (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', test_result_id INT DEFAULT NULL, task_name VARCHAR(255) NOT NULL, used_time DOUBLE PRECISION NOT NULL, used_memory INT NOT NULL, output LONGTEXT NOT NULL, INDEX IDX_28C345C0853A2189 (test_result_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE test_result (id INT AUTO_INCREMENT NOT NULL, solution_evaluation_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', test_name VARCHAR(255) NOT NULL, status VARCHAR(255) NOT NULL, score DOUBLE PRECISION NOT NULL, memory_exceeded TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', used_memory_ratio DOUBLE PRECISION NOT NULL, time_exceeded TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', used_time_ratio DOUBLE PRECISION NOT NULL, exit_code INT NOT NULL, message VARCHAR(255) NOT NULL, stats LONGTEXT NOT NULL, judge_output VARCHAR(255) DEFAULT NULL, INDEX IDX_84B3C63D1CCFA981 (solution_evaluation_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE `uploaded_file` (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(255) NOT NULL, local_file_path VARCHAR(255) DEFAULT NULL, uploaded_at DATETIME NOT NULL, is_public TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', file_size INT NOT NULL, discriminator VARCHAR(255) NOT NULL, hash_name VARCHAR(255) DEFAULT NULL, file_server_path VARCHAR(255) DEFAULT NULL, INDEX IDX_B40DF75DA76ED395 (user_id), INDEX IDX_B40DF75D1C0BE183 (solution_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE user (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', instance_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', settings_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', degrees_before_name VARCHAR(255) NOT NULL, first_name VARCHAR(255) NOT NULL, last_name VARCHAR(255) NOT NULL, degrees_after_name VARCHAR(255) NOT NULL, email VARCHAR(255) NOT NULL, avatar_url VARCHAR(255) NOT NULL, is_verified TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', is_allowed TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', created_at DATETIME NOT NULL, deleted_at DATETIME DEFAULT NULL, role VARCHAR(255) NOT NULL, INDEX IDX_8D93D6493A51721D (instance_id), UNIQUE INDEX UNIQ_8D93D64959949888 (settings_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE user_action (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', logged_at DATETIME NOT NULL, action VARCHAR(255) NOT NULL, params VARCHAR(255) NOT NULL, code INT NOT NULL, data VARCHAR(255) DEFAULT NULL, INDEX IDX_229E97AFA76ED395 (user_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE user_settings (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', dark_theme TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', vim_mode TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', default_language VARCHAR(255) NOT NULL, opened_sidebar TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', new_assignment_emails TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', assignment_deadline_emails TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', submission_evaluated_emails TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'ALTER TABLE assignment ADD CONSTRAINT FK_30C544BA8A0C0069 FOREIGN KEY (exercise_config_id) REFERENCES exercise_config (id)'
        );
        $this->addSql(
            'ALTER TABLE assignment ADD CONSTRAINT FK_30C544BAE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id)'
        );
        $this->addSql(
            'ALTER TABLE assignment ADD CONSTRAINT FK_30C544BAFE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id)'
        );
        $this->addSql(
            'ALTER TABLE assignment_runtime_environment ADD CONSTRAINT FK_6AA32A86D19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE assignment_runtime_environment ADD CONSTRAINT FK_6AA32A86C9F479A7 FOREIGN KEY (runtime_environment_id) REFERENCES runtime_environment (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE assignment_hardware_group ADD CONSTRAINT FK_DEC8DE33D19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE assignment_hardware_group ADD CONSTRAINT FK_DEC8DE3323F56800 FOREIGN KEY (hardware_group_id) REFERENCES hardware_group (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE assignment_exercise_limits ADD CONSTRAINT FK_696DD48D19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE assignment_exercise_limits ADD CONSTRAINT FK_696DD48146FFD8C FOREIGN KEY (exercise_limits_id) REFERENCES exercise_limits (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE assignment_exercise_environment_config ADD CONSTRAINT FK_E54F7F2BD19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE assignment_exercise_environment_config ADD CONSTRAINT FK_E54F7F2B3D5429CE FOREIGN KEY (exercise_environment_config_id) REFERENCES exercise_environment_config (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE assignment_localized_text ADD CONSTRAINT FK_9C8F78CD19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE assignment_localized_text ADD CONSTRAINT FK_9C8F78CA9B14E11 FOREIGN KEY (localized_text_id) REFERENCES localized_text (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE comment ADD CONSTRAINT FK_9474526CBEEA14F FOREIGN KEY (comment_thread_id) REFERENCES comment_thread (id)'
        );
        $this->addSql(
            'ALTER TABLE comment ADD CONSTRAINT FK_9474526CA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51CE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id)'
        );
        $this->addSql(
            'ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51CF675F31B FOREIGN KEY (author_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51CFE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id)'
        );
        $this->addSql(
            'ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51C8A0C0069 FOREIGN KEY (exercise_config_id) REFERENCES exercise_config (id)'
        );
        $this->addSql(
            'ALTER TABLE exercise_localized_text ADD CONSTRAINT FK_8327FD4EE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE exercise_localized_text ADD CONSTRAINT FK_8327FD4EA9B14E11 FOREIGN KEY (localized_text_id) REFERENCES localized_text (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE exercise_runtime_environment ADD CONSTRAINT FK_F5199605E934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE exercise_runtime_environment ADD CONSTRAINT FK_F5199605C9F479A7 FOREIGN KEY (runtime_environment_id) REFERENCES runtime_environment (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE exercise_hardware_group ADD CONSTRAINT FK_5427D4F1E934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE exercise_hardware_group ADD CONSTRAINT FK_5427D4F123F56800 FOREIGN KEY (hardware_group_id) REFERENCES hardware_group (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE exercise_supplementary_exercise_file ADD CONSTRAINT FK_4235999E934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE exercise_supplementary_exercise_file ADD CONSTRAINT FK_42359992D777971 FOREIGN KEY (supplementary_exercise_file_id) REFERENCES uploaded_file (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE exercise_additional_exercise_file ADD CONSTRAINT FK_C0EAC68CE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE exercise_additional_exercise_file ADD CONSTRAINT FK_C0EAC68CED6C0B59 FOREIGN KEY (additional_exercise_file_id) REFERENCES uploaded_file (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE exercise_exercise_limits ADD CONSTRAINT FK_737691DEE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE exercise_exercise_limits ADD CONSTRAINT FK_737691DE146FFD8C FOREIGN KEY (exercise_limits_id) REFERENCES exercise_limits (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE exercise_exercise_environment_config ADD CONSTRAINT FK_37295814E934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE exercise_exercise_environment_config ADD CONSTRAINT FK_372958143D5429CE FOREIGN KEY (exercise_environment_config_id) REFERENCES exercise_environment_config (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE exercise_config ADD CONSTRAINT FK_A1C5BA173EA4CB4D FOREIGN KEY (created_from_id) REFERENCES exercise_config (id)'
        );
        $this->addSql(
            'ALTER TABLE exercise_config ADD CONSTRAINT FK_A1C5BA17F675F31B FOREIGN KEY (author_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE exercise_environment_config ADD CONSTRAINT FK_89540FF7C9F479A7 FOREIGN KEY (runtime_environment_id) REFERENCES runtime_environment (id)'
        );
        $this->addSql(
            'ALTER TABLE exercise_environment_config ADD CONSTRAINT FK_89540FF73EA4CB4D FOREIGN KEY (created_from_id) REFERENCES exercise_environment_config (id)'
        );
        $this->addSql(
            'ALTER TABLE exercise_environment_config ADD CONSTRAINT FK_89540FF7F675F31B FOREIGN KEY (author_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE exercise_limits ADD CONSTRAINT FK_238CADD03EA4CB4D FOREIGN KEY (created_from_id) REFERENCES exercise_limits (id)'
        );
        $this->addSql(
            'ALTER TABLE exercise_limits ADD CONSTRAINT FK_238CADD0F675F31B FOREIGN KEY (author_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE exercise_limits ADD CONSTRAINT FK_238CADD0C9F479A7 FOREIGN KEY (runtime_environment_id) REFERENCES runtime_environment (id)'
        );
        $this->addSql(
            'ALTER TABLE exercise_limits ADD CONSTRAINT FK_238CADD023F56800 FOREIGN KEY (hardware_group_id) REFERENCES hardware_group (id)'
        );
        $this->addSql(
            'ALTER TABLE external_login ADD CONSTRAINT FK_9845B893A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE forgotten_password ADD CONSTRAINT FK_2EDC8D24A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE `group` ADD CONSTRAINT FK_6DC044C561997596 FOREIGN KEY (parent_group_id) REFERENCES `group` (id)'
        );
        $this->addSql(
            'ALTER TABLE `group` ADD CONSTRAINT FK_6DC044C53A51721D FOREIGN KEY (instance_id) REFERENCES instance (id)'
        );
        $this->addSql(
            'ALTER TABLE `group` ADD CONSTRAINT FK_6DC044C5642B8210 FOREIGN KEY (admin_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE group_membership ADD CONSTRAINT FK_5132B337A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE group_membership ADD CONSTRAINT FK_5132B337FE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id)'
        );
        $this->addSql(
            'ALTER TABLE hardware_group_availability_log ADD CONSTRAINT FK_C6835B1523F56800 FOREIGN KEY (hardware_group_id) REFERENCES hardware_group (id)'
        );
        $this->addSql(
            'ALTER TABLE instance ADD CONSTRAINT FK_4230B1DE642B8210 FOREIGN KEY (admin_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE instance ADD CONSTRAINT FK_4230B1DE8509B3A1 FOREIGN KEY (root_group_id) REFERENCES `group` (id)'
        );
        $this->addSql(
            'ALTER TABLE licence ADD CONSTRAINT FK_1DAAE6483A51721D FOREIGN KEY (instance_id) REFERENCES instance (id)'
        );
        $this->addSql(
            'ALTER TABLE localized_text ADD CONSTRAINT FK_7C6ACF3E3EA4CB4D FOREIGN KEY (created_from_id) REFERENCES localized_text (id)'
        );
        $this->addSql(
            'ALTER TABLE login ADD CONSTRAINT FK_AA08CB10A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE pipeline ADD CONSTRAINT FK_7DFCD9D9579C4491 FOREIGN KEY (pipeline_config_id) REFERENCES pipeline_config (id)'
        );
        $this->addSql(
            'ALTER TABLE pipeline ADD CONSTRAINT FK_7DFCD9D9F675F31B FOREIGN KEY (author_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE pipeline ADD CONSTRAINT FK_7DFCD9D93EA4CB4D FOREIGN KEY (created_from_id) REFERENCES pipeline (id)'
        );
        $this->addSql(
            'ALTER TABLE pipeline ADD CONSTRAINT FK_7DFCD9D9E934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id)'
        );
        $this->addSql(
            'ALTER TABLE pipeline_supplementary_exercise_file ADD CONSTRAINT FK_DCF57288E80B93 FOREIGN KEY (pipeline_id) REFERENCES pipeline (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE pipeline_supplementary_exercise_file ADD CONSTRAINT FK_DCF572882D777971 FOREIGN KEY (supplementary_exercise_file_id) REFERENCES uploaded_file (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE pipeline_config ADD CONSTRAINT FK_32420693F675F31B FOREIGN KEY (author_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE pipeline_config ADD CONSTRAINT FK_324206933EA4CB4D FOREIGN KEY (created_from_id) REFERENCES pipeline_config (id)'
        );
        $this->addSql(
            'ALTER TABLE reference_exercise_solution ADD CONSTRAINT FK_E414ABABE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id)'
        );
        $this->addSql(
            'ALTER TABLE reference_exercise_solution ADD CONSTRAINT FK_E414ABAB1C0BE183 FOREIGN KEY (solution_id) REFERENCES solution (id)'
        );
        $this->addSql(
            'ALTER TABLE reference_solution_evaluation ADD CONSTRAINT FK_62BA741FFA3CA3B7 FOREIGN KEY (reference_solution_id) REFERENCES reference_exercise_solution (id)'
        );
        $this->addSql(
            'ALTER TABLE reference_solution_evaluation ADD CONSTRAINT FK_62BA741FA398D0F9 FOREIGN KEY (hw_group_id) REFERENCES hardware_group (id)'
        );
        $this->addSql(
            'ALTER TABLE reference_solution_evaluation ADD CONSTRAINT FK_62BA741F456C5646 FOREIGN KEY (evaluation_id) REFERENCES solution_evaluation (id)'
        );
        $this->addSql(
            'ALTER TABLE sis_group_binding ADD CONSTRAINT FK_A6670D06FE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id)'
        );
        $this->addSql(
            'ALTER TABLE solution ADD CONSTRAINT FK_9F3329DBA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE solution ADD CONSTRAINT FK_9F3329DBC9F479A7 FOREIGN KEY (runtime_environment_id) REFERENCES runtime_environment (id)'
        );
        $this->addSql(
            'ALTER TABLE submission ADD CONSTRAINT FK_DB055AF3D19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id)'
        );
        $this->addSql(
            'ALTER TABLE submission ADD CONSTRAINT FK_DB055AF32E9C906C FOREIGN KEY (original_submission_id) REFERENCES submission (id)'
        );
        $this->addSql(
            'ALTER TABLE submission ADD CONSTRAINT FK_DB055AF3A76ED395 FOREIGN KEY (user_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE submission ADD CONSTRAINT FK_DB055AF379F7D87D FOREIGN KEY (submitted_by_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE submission ADD CONSTRAINT FK_DB055AF31C0BE183 FOREIGN KEY (solution_id) REFERENCES solution (id)'
        );
        $this->addSql(
            'ALTER TABLE submission ADD CONSTRAINT FK_DB055AF3456C5646 FOREIGN KEY (evaluation_id) REFERENCES solution_evaluation (id)'
        );
        $this->addSql(
            'ALTER TABLE submission_failure ADD CONSTRAINT FK_D7A9817E1FD4933 FOREIGN KEY (submission_id) REFERENCES submission (id)'
        );
        $this->addSql(
            'ALTER TABLE task_result ADD CONSTRAINT FK_28C345C0853A2189 FOREIGN KEY (test_result_id) REFERENCES test_result (id)'
        );
        $this->addSql(
            'ALTER TABLE test_result ADD CONSTRAINT FK_84B3C63D1CCFA981 FOREIGN KEY (solution_evaluation_id) REFERENCES solution_evaluation (id)'
        );
        $this->addSql(
            'ALTER TABLE `uploaded_file` ADD CONSTRAINT FK_B40DF75DA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE `uploaded_file` ADD CONSTRAINT FK_B40DF75D1C0BE183 FOREIGN KEY (solution_id) REFERENCES solution (id)'
        );
        $this->addSql(
            'ALTER TABLE user ADD CONSTRAINT FK_8D93D6493A51721D FOREIGN KEY (instance_id) REFERENCES instance (id)'
        );
        $this->addSql(
            'ALTER TABLE user ADD CONSTRAINT FK_8D93D64959949888 FOREIGN KEY (settings_id) REFERENCES user_settings (id)'
        );
        $this->addSql(
            'ALTER TABLE user_action ADD CONSTRAINT FK_229E97AFA76ED395 FOREIGN KEY (user_id) REFERENCES user (id)'
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

        $this->addSql('ALTER TABLE assignment_runtime_environment DROP FOREIGN KEY FK_6AA32A86D19302F8');
        $this->addSql('ALTER TABLE assignment_hardware_group DROP FOREIGN KEY FK_DEC8DE33D19302F8');
        $this->addSql('ALTER TABLE assignment_exercise_limits DROP FOREIGN KEY FK_696DD48D19302F8');
        $this->addSql('ALTER TABLE assignment_exercise_environment_config DROP FOREIGN KEY FK_E54F7F2BD19302F8');
        $this->addSql('ALTER TABLE assignment_localized_text DROP FOREIGN KEY FK_9C8F78CD19302F8');
        $this->addSql('ALTER TABLE submission DROP FOREIGN KEY FK_DB055AF3D19302F8');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CBEEA14F');
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BAE934951A');
        $this->addSql('ALTER TABLE exercise DROP FOREIGN KEY FK_AEDAD51CE934951A');
        $this->addSql('ALTER TABLE exercise_localized_text DROP FOREIGN KEY FK_8327FD4EE934951A');
        $this->addSql('ALTER TABLE exercise_runtime_environment DROP FOREIGN KEY FK_F5199605E934951A');
        $this->addSql('ALTER TABLE exercise_hardware_group DROP FOREIGN KEY FK_5427D4F1E934951A');
        $this->addSql('ALTER TABLE exercise_supplementary_exercise_file DROP FOREIGN KEY FK_4235999E934951A');
        $this->addSql('ALTER TABLE exercise_additional_exercise_file DROP FOREIGN KEY FK_C0EAC68CE934951A');
        $this->addSql('ALTER TABLE exercise_exercise_limits DROP FOREIGN KEY FK_737691DEE934951A');
        $this->addSql('ALTER TABLE exercise_exercise_environment_config DROP FOREIGN KEY FK_37295814E934951A');
        $this->addSql('ALTER TABLE pipeline DROP FOREIGN KEY FK_7DFCD9D9E934951A');
        $this->addSql('ALTER TABLE reference_exercise_solution DROP FOREIGN KEY FK_E414ABABE934951A');
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BA8A0C0069');
        $this->addSql('ALTER TABLE exercise DROP FOREIGN KEY FK_AEDAD51C8A0C0069');
        $this->addSql('ALTER TABLE exercise_config DROP FOREIGN KEY FK_A1C5BA173EA4CB4D');
        $this->addSql('ALTER TABLE assignment_exercise_environment_config DROP FOREIGN KEY FK_E54F7F2B3D5429CE');
        $this->addSql('ALTER TABLE exercise_exercise_environment_config DROP FOREIGN KEY FK_372958143D5429CE');
        $this->addSql('ALTER TABLE exercise_environment_config DROP FOREIGN KEY FK_89540FF73EA4CB4D');
        $this->addSql('ALTER TABLE assignment_exercise_limits DROP FOREIGN KEY FK_696DD48146FFD8C');
        $this->addSql('ALTER TABLE exercise_exercise_limits DROP FOREIGN KEY FK_737691DE146FFD8C');
        $this->addSql('ALTER TABLE exercise_limits DROP FOREIGN KEY FK_238CADD03EA4CB4D');
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BAFE54D947');
        $this->addSql('ALTER TABLE exercise DROP FOREIGN KEY FK_AEDAD51CFE54D947');
        $this->addSql('ALTER TABLE `group` DROP FOREIGN KEY FK_6DC044C561997596');
        $this->addSql('ALTER TABLE group_membership DROP FOREIGN KEY FK_5132B337FE54D947');
        $this->addSql('ALTER TABLE instance DROP FOREIGN KEY FK_4230B1DE8509B3A1');
        $this->addSql('ALTER TABLE sis_group_binding DROP FOREIGN KEY FK_A6670D06FE54D947');
        $this->addSql('ALTER TABLE assignment_hardware_group DROP FOREIGN KEY FK_DEC8DE3323F56800');
        $this->addSql('ALTER TABLE exercise_hardware_group DROP FOREIGN KEY FK_5427D4F123F56800');
        $this->addSql('ALTER TABLE exercise_limits DROP FOREIGN KEY FK_238CADD023F56800');
        $this->addSql('ALTER TABLE hardware_group_availability_log DROP FOREIGN KEY FK_C6835B1523F56800');
        $this->addSql('ALTER TABLE reference_solution_evaluation DROP FOREIGN KEY FK_62BA741FA398D0F9');
        $this->addSql('ALTER TABLE `group` DROP FOREIGN KEY FK_6DC044C53A51721D');
        $this->addSql('ALTER TABLE licence DROP FOREIGN KEY FK_1DAAE6483A51721D');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D6493A51721D');
        $this->addSql('ALTER TABLE assignment_localized_text DROP FOREIGN KEY FK_9C8F78CA9B14E11');
        $this->addSql('ALTER TABLE exercise_localized_text DROP FOREIGN KEY FK_8327FD4EA9B14E11');
        $this->addSql('ALTER TABLE localized_text DROP FOREIGN KEY FK_7C6ACF3E3EA4CB4D');
        $this->addSql('ALTER TABLE pipeline DROP FOREIGN KEY FK_7DFCD9D93EA4CB4D');
        $this->addSql('ALTER TABLE pipeline_supplementary_exercise_file DROP FOREIGN KEY FK_DCF57288E80B93');
        $this->addSql('ALTER TABLE pipeline DROP FOREIGN KEY FK_7DFCD9D9579C4491');
        $this->addSql('ALTER TABLE pipeline_config DROP FOREIGN KEY FK_324206933EA4CB4D');
        $this->addSql('ALTER TABLE reference_solution_evaluation DROP FOREIGN KEY FK_62BA741FFA3CA3B7');
        $this->addSql('ALTER TABLE assignment_runtime_environment DROP FOREIGN KEY FK_6AA32A86C9F479A7');
        $this->addSql('ALTER TABLE exercise_runtime_environment DROP FOREIGN KEY FK_F5199605C9F479A7');
        $this->addSql('ALTER TABLE exercise_environment_config DROP FOREIGN KEY FK_89540FF7C9F479A7');
        $this->addSql('ALTER TABLE exercise_limits DROP FOREIGN KEY FK_238CADD0C9F479A7');
        $this->addSql('ALTER TABLE solution DROP FOREIGN KEY FK_9F3329DBC9F479A7');
        $this->addSql('ALTER TABLE reference_exercise_solution DROP FOREIGN KEY FK_E414ABAB1C0BE183');
        $this->addSql('ALTER TABLE submission DROP FOREIGN KEY FK_DB055AF31C0BE183');
        $this->addSql('ALTER TABLE `uploaded_file` DROP FOREIGN KEY FK_B40DF75D1C0BE183');
        $this->addSql('ALTER TABLE reference_solution_evaluation DROP FOREIGN KEY FK_62BA741F456C5646');
        $this->addSql('ALTER TABLE submission DROP FOREIGN KEY FK_DB055AF3456C5646');
        $this->addSql('ALTER TABLE test_result DROP FOREIGN KEY FK_84B3C63D1CCFA981');
        $this->addSql('ALTER TABLE submission DROP FOREIGN KEY FK_DB055AF32E9C906C');
        $this->addSql('ALTER TABLE submission_failure DROP FOREIGN KEY FK_D7A9817E1FD4933');
        $this->addSql('ALTER TABLE task_result DROP FOREIGN KEY FK_28C345C0853A2189');
        $this->addSql('ALTER TABLE exercise_supplementary_exercise_file DROP FOREIGN KEY FK_42359992D777971');
        $this->addSql('ALTER TABLE exercise_additional_exercise_file DROP FOREIGN KEY FK_C0EAC68CED6C0B59');
        $this->addSql('ALTER TABLE pipeline_supplementary_exercise_file DROP FOREIGN KEY FK_DCF572882D777971');
        $this->addSql('ALTER TABLE comment DROP FOREIGN KEY FK_9474526CA76ED395');
        $this->addSql('ALTER TABLE exercise DROP FOREIGN KEY FK_AEDAD51CF675F31B');
        $this->addSql('ALTER TABLE exercise_config DROP FOREIGN KEY FK_A1C5BA17F675F31B');
        $this->addSql('ALTER TABLE exercise_environment_config DROP FOREIGN KEY FK_89540FF7F675F31B');
        $this->addSql('ALTER TABLE exercise_limits DROP FOREIGN KEY FK_238CADD0F675F31B');
        $this->addSql('ALTER TABLE external_login DROP FOREIGN KEY FK_9845B893A76ED395');
        $this->addSql('ALTER TABLE forgotten_password DROP FOREIGN KEY FK_2EDC8D24A76ED395');
        $this->addSql('ALTER TABLE `group` DROP FOREIGN KEY FK_6DC044C5642B8210');
        $this->addSql('ALTER TABLE group_membership DROP FOREIGN KEY FK_5132B337A76ED395');
        $this->addSql('ALTER TABLE instance DROP FOREIGN KEY FK_4230B1DE642B8210');
        $this->addSql('ALTER TABLE login DROP FOREIGN KEY FK_AA08CB10A76ED395');
        $this->addSql('ALTER TABLE pipeline DROP FOREIGN KEY FK_7DFCD9D9F675F31B');
        $this->addSql('ALTER TABLE pipeline_config DROP FOREIGN KEY FK_32420693F675F31B');
        $this->addSql('ALTER TABLE solution DROP FOREIGN KEY FK_9F3329DBA76ED395');
        $this->addSql('ALTER TABLE submission DROP FOREIGN KEY FK_DB055AF3A76ED395');
        $this->addSql('ALTER TABLE submission DROP FOREIGN KEY FK_DB055AF379F7D87D');
        $this->addSql('ALTER TABLE `uploaded_file` DROP FOREIGN KEY FK_B40DF75DA76ED395');
        $this->addSql('ALTER TABLE user_action DROP FOREIGN KEY FK_229E97AFA76ED395');
        $this->addSql('ALTER TABLE user DROP FOREIGN KEY FK_8D93D64959949888');
        $this->addSql('DROP TABLE assignment');
        $this->addSql('DROP TABLE assignment_runtime_environment');
        $this->addSql('DROP TABLE assignment_hardware_group');
        $this->addSql('DROP TABLE assignment_exercise_limits');
        $this->addSql('DROP TABLE assignment_exercise_environment_config');
        $this->addSql('DROP TABLE assignment_localized_text');
        $this->addSql('DROP TABLE comment');
        $this->addSql('DROP TABLE comment_thread');
        $this->addSql('DROP TABLE exercise');
        $this->addSql('DROP TABLE exercise_localized_text');
        $this->addSql('DROP TABLE exercise_runtime_environment');
        $this->addSql('DROP TABLE exercise_hardware_group');
        $this->addSql('DROP TABLE exercise_supplementary_exercise_file');
        $this->addSql('DROP TABLE exercise_additional_exercise_file');
        $this->addSql('DROP TABLE exercise_exercise_limits');
        $this->addSql('DROP TABLE exercise_exercise_environment_config');
        $this->addSql('DROP TABLE exercise_config');
        $this->addSql('DROP TABLE exercise_environment_config');
        $this->addSql('DROP TABLE exercise_limits');
        $this->addSql('DROP TABLE external_login');
        $this->addSql('DROP TABLE forgotten_password');
        $this->addSql('DROP TABLE `group`');
        $this->addSql('DROP TABLE group_membership');
        $this->addSql('DROP TABLE hardware_group');
        $this->addSql('DROP TABLE hardware_group_availability_log');
        $this->addSql('DROP TABLE instance');
        $this->addSql('DROP TABLE licence');
        $this->addSql('DROP TABLE localized_text');
        $this->addSql('DROP TABLE login');
        $this->addSql('DROP TABLE pipeline');
        $this->addSql('DROP TABLE pipeline_supplementary_exercise_file');
        $this->addSql('DROP TABLE pipeline_config');
        $this->addSql('DROP TABLE reference_exercise_solution');
        $this->addSql('DROP TABLE reference_solution_evaluation');
        $this->addSql('DROP TABLE reported_errors');
        $this->addSql('DROP TABLE runtime_environment');
        $this->addSql('DROP TABLE sis_group_binding');
        $this->addSql('DROP TABLE sis_valid_term');
        $this->addSql('DROP TABLE solution');
        $this->addSql('DROP TABLE solution_evaluation');
        $this->addSql('DROP TABLE submission');
        $this->addSql('DROP TABLE submission_failure');
        $this->addSql('DROP TABLE task_result');
        $this->addSql('DROP TABLE test_result');
        $this->addSql('DROP TABLE `uploaded_file`');
        $this->addSql('DROP TABLE user');
        $this->addSql('DROP TABLE user_action');
        $this->addSql('DROP TABLE user_settings');
    }
}
