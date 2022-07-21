<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220721143127 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Changing guid to uuid in id columns comments.';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assignment CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE exercise_config_id exercise_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE exercise_id exercise_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE group_id group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE score_config_id score_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE assignment_localized_assignment CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE localized_assignment_id localized_assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE assignment_disabled_runtime_environments CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE assignment_localized_exercise CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE localized_exercise_id localized_exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE assignment_runtime_environment CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE assignment_hardware_group CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE assignment_exercise_limits CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE exercise_limits_id exercise_limits_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE assignment_exercise_environment_config CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE exercise_environment_config_id exercise_environment_config_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE assignment_exercise_test CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE assignment_supplementary_exercise_file CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE supplementary_exercise_file_id supplementary_exercise_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE assignment_attachment_file CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE attachment_file_id attachment_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE assignment_solution CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE assignment_id assignment_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE solution_id solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE last_submission_id last_submission_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE assignment_solution_submission CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE assignment_solution_id assignment_solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE evaluation_id evaluation_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE submitted_by_id submitted_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE failure_id failure_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE async_job CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_by_id created_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE associated_assignment_id associated_assignment_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE comment CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE text text TEXT NOT NULL');
        $this->addSql('ALTER TABLE exercise CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE exercise_id exercise_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE exercise_config_id exercise_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE score_config_id score_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE validation_error validation_error TEXT NOT NULL');
        $this->addSql('ALTER TABLE exercise_runtime_environment CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE exercise_group CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE group_id group_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE exercise_localized_exercise CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE localized_exercise_id localized_exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE exercise_hardware_group CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE exercise_exercise_limits CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE exercise_limits_id exercise_limits_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE exercise_exercise_environment_config CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE exercise_environment_config_id exercise_environment_config_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE exercise_exercise_test CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE exercise_supplementary_exercise_file CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE supplementary_exercise_file_id supplementary_exercise_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE exercise_attachment_file CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE attachment_file_id attachment_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE exercise_config CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE exercise_environment_config CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE variables_table variables_table TEXT NOT NULL');
        $this->addSql('ALTER TABLE exercise_limits CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE exercise_score_config CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE exercise_tag CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE exercise_id exercise_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE exercise_test CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE external_login CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE forgotten_password CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE `group` CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE parent_group_id parent_group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE instance_id instance_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE group_membership CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE group_id group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE inherited_from_id inherited_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE hardware_group CHANGE metadata metadata TEXT NOT NULL');
        $this->addSql('ALTER TABLE instance CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE admin_id admin_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE root_group_id root_group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE licence CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE instance_id instance_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE localized_assignment CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE localized_exercise CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE localized_group CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE group_id group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE localized_notification CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE localized_shadow_assignment CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE login CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE notification CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE notification_localized_notification CHANGE notification_id notification_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE localized_notification_id localized_notification_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE notification_group CHANGE notification_id notification_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE group_id group_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE pipeline CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE pipeline_config_id pipeline_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE pipeline_supplementary_exercise_file CHANGE pipeline_id pipeline_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE supplementary_exercise_file_id supplementary_exercise_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE pipeline_runtime_environment CHANGE pipeline_id pipeline_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE pipeline_config CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE pipeline_parameter CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE pipeline_id pipeline_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE reference_exercise_solution CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE exercise_id exercise_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE solution_id solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE last_submission_id last_submission_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE reference_solution_submission CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE reference_solution_id reference_solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE evaluation_id evaluation_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE submitted_by_id submitted_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE failure_id failure_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE reported_errors CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE runtime_environment CHANGE default_variables default_variables TEXT NOT NULL');
        $this->addSql('ALTER TABLE shadow_assignment CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE group_id group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE shadow_assignment_localized_shadow_assignment CHANGE shadow_assignment_id shadow_assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE localized_shadow_assignment_id localized_shadow_assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE shadow_assignment_points CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE shadow_assignment_id shadow_assignment_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE awardee_id awardee_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE sis_group_binding CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE group_id group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE sis_valid_term CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE solution CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE solution_evaluation CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE score_config_id score_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE submission_failure CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE test_result CHANGE solution_evaluation_id solution_evaluation_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE uploaded_file CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE solution_id solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE uploaded_partial_file CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE user CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE settings_id settings_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', CHANGE ui_data_id ui_data_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE user_instance CHANGE user_id user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', CHANGE instance_id instance_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE user_settings CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE user_ui_data CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\'');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assignment CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE exercise_id exercise_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE group_id group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE exercise_config_id exercise_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE score_config_id score_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE assignment_attachment_file CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE attachment_file_id attachment_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE assignment_disabled_runtime_environments CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE assignment_exercise_environment_config CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE exercise_environment_config_id exercise_environment_config_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE assignment_exercise_limits CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE exercise_limits_id exercise_limits_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE assignment_exercise_test CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE assignment_hardware_group CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE assignment_localized_assignment CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE localized_assignment_id localized_assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE assignment_localized_exercise CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE localized_exercise_id localized_exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE assignment_runtime_environment CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE assignment_solution CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE assignment_id assignment_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE solution_id solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE last_submission_id last_submission_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE assignment_solution_submission CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE assignment_solution_id assignment_solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE failure_id failure_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE submitted_by_id submitted_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE evaluation_id evaluation_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE assignment_supplementary_exercise_file CHANGE assignment_id assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE supplementary_exercise_file_id supplementary_exercise_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE async_job CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE created_by_id created_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE associated_assignment_id associated_assignment_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE comment CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE text text LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE exercise CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE exercise_id exercise_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE exercise_config_id exercise_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE score_config_id score_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE validation_error validation_error LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE exercise_attachment_file CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE attachment_file_id attachment_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE exercise_config CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE exercise_environment_config CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE variables_table variables_table LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE exercise_exercise_environment_config CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE exercise_environment_config_id exercise_environment_config_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE exercise_exercise_limits CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE exercise_limits_id exercise_limits_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE exercise_exercise_test CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE exercise_group CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE group_id group_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE exercise_hardware_group CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE exercise_limits CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE exercise_localized_exercise CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE localized_exercise_id localized_exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE exercise_runtime_environment CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE exercise_score_config CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE exercise_supplementary_exercise_file CHANGE exercise_id exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE supplementary_exercise_file_id supplementary_exercise_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE exercise_tag CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE exercise_id exercise_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE exercise_test CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE external_login CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE forgotten_password CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE `group` CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE parent_group_id parent_group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE instance_id instance_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE group_membership CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE group_id group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE inherited_from_id inherited_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE hardware_group CHANGE metadata metadata LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE instance CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE admin_id admin_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE root_group_id root_group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE licence CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE instance_id instance_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE localized_assignment CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE localized_exercise CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE localized_group CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE group_id group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE localized_notification CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE localized_shadow_assignment CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE login CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE notification CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE notification_group CHANGE notification_id notification_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE group_id group_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE notification_localized_notification CHANGE notification_id notification_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE localized_notification_id localized_notification_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE pipeline CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE pipeline_config_id pipeline_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE pipeline_config CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE created_from_id created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE pipeline_parameter CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE pipeline_id pipeline_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE pipeline_runtime_environment CHANGE pipeline_id pipeline_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE pipeline_supplementary_exercise_file CHANGE pipeline_id pipeline_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE supplementary_exercise_file_id supplementary_exercise_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE reference_exercise_solution CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE exercise_id exercise_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE solution_id solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE last_submission_id last_submission_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE reference_solution_submission CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE reference_solution_id reference_solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE failure_id failure_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE submitted_by_id submitted_by_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE evaluation_id evaluation_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE reported_errors CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE runtime_environment CHANGE default_variables default_variables LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE shadow_assignment CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE group_id group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE shadow_assignment_localized_shadow_assignment CHANGE shadow_assignment_id shadow_assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE localized_shadow_assignment_id localized_shadow_assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE shadow_assignment_points CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE shadow_assignment_id shadow_assignment_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE awardee_id awardee_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE sis_group_binding CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE group_id group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE sis_valid_term CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE solution CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE author_id author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE solution_evaluation CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE score_config_id score_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE submission_failure CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE test_result CHANGE solution_evaluation_id solution_evaluation_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE `uploaded_file` CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE solution_id solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE uploaded_partial_file CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE user_id user_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE user CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE settings_id settings_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', CHANGE ui_data_id ui_data_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE user_instance CHANGE user_id user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', CHANGE instance_id instance_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE user_settings CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
        $this->addSql('ALTER TABLE user_ui_data CHANGE id id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\'');
    }
}
