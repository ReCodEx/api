<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20190201111028 extends AbstractMigration
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

        $this->addSql('SET FOREIGN_KEY_CHECKS=0');

        $this->addSql(
            'ALTER TABLE assignment_disabled_runtime_environments CHANGE runtime_environment_id runtime_environment_id VARCHAR(32) NOT NULL'
        );
        $this->addSql(
            'ALTER TABLE assignment_runtime_environment CHANGE runtime_environment_id runtime_environment_id VARCHAR(32) NOT NULL'
        );
        $this->addSql(
            'ALTER TABLE assignment_hardware_group CHANGE hardware_group_id hardware_group_id VARCHAR(32) NOT NULL'
        );
        $this->addSql('ALTER TABLE comment CHANGE comment_thread_id comment_thread_id CHAR(36) DEFAULT NULL');
        $this->addSql('ALTER TABLE comment_thread CHANGE id id CHAR(36) NOT NULL');
        $this->addSql('ALTER TABLE doctrine_migrations CHANGE `version` `version` VARCHAR(32) NOT NULL');
        $this->addSql(
            'ALTER TABLE exercise_runtime_environment CHANGE runtime_environment_id runtime_environment_id VARCHAR(32) NOT NULL'
        );
        $this->addSql(
            'ALTER TABLE exercise_hardware_group CHANGE hardware_group_id hardware_group_id VARCHAR(32) NOT NULL'
        );
        $this->addSql(
            'ALTER TABLE exercise_environment_config CHANGE runtime_environment_id runtime_environment_id VARCHAR(32) DEFAULT NULL'
        );
        $this->addSql(
            'ALTER TABLE exercise_limits CHANGE runtime_environment_id runtime_environment_id VARCHAR(32) DEFAULT NULL, CHANGE hardware_group_id hardware_group_id VARCHAR(32) DEFAULT NULL'
        );
        $this->addSql(
            'ALTER TABLE external_login CHANGE external_id external_id VARCHAR(128) NOT NULL, CHANGE auth_service auth_service VARCHAR(32) NOT NULL'
        );
        $this->addSql('ALTER TABLE hardware_group CHANGE id id VARCHAR(32) NOT NULL');
        $this->addSql('ALTER TABLE login CHANGE username username VARCHAR(128) NOT NULL');
        $this->addSql(
            'ALTER TABLE pipeline_runtime_environment CHANGE runtime_environment_id runtime_environment_id VARCHAR(32) NOT NULL'
        );
        $this->addSql(
            'ALTER TABLE reference_solution_submission CHANGE hw_group_id hw_group_id VARCHAR(32) DEFAULT NULL'
        );
        $this->addSql('ALTER TABLE runtime_environment CHANGE id id VARCHAR(32) NOT NULL');
        $this->addSql(
            'ALTER TABLE solution CHANGE runtime_environment_id runtime_environment_id VARCHAR(32) DEFAULT NULL'
        );

        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
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

        $this->addSql('SET FOREIGN_KEY_CHECKS=0');

        $this->addSql(
            'ALTER TABLE assignment_disabled_runtime_environments CHANGE runtime_environment_id runtime_environment_id VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci'
        );
        $this->addSql(
            'ALTER TABLE assignment_hardware_group CHANGE hardware_group_id hardware_group_id VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci'
        );
        $this->addSql(
            'ALTER TABLE assignment_runtime_environment CHANGE runtime_environment_id runtime_environment_id VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci'
        );
        $this->addSql(
            'ALTER TABLE comment CHANGE comment_thread_id comment_thread_id VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci'
        );
        $this->addSql('ALTER TABLE comment_thread CHANGE id id VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci');
        $this->addSql(
            'ALTER TABLE doctrine_migrations CHANGE `version` `version` VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci'
        );
        $this->addSql(
            'ALTER TABLE exercise_environment_config CHANGE runtime_environment_id runtime_environment_id VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci'
        );
        $this->addSql(
            'ALTER TABLE exercise_hardware_group CHANGE hardware_group_id hardware_group_id VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci'
        );
        $this->addSql(
            'ALTER TABLE exercise_limits CHANGE runtime_environment_id runtime_environment_id VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci, CHANGE hardware_group_id hardware_group_id VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci'
        );
        $this->addSql(
            'ALTER TABLE exercise_runtime_environment CHANGE runtime_environment_id runtime_environment_id VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci'
        );
        $this->addSql(
            'ALTER TABLE external_login CHANGE external_id external_id VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci, CHANGE auth_service auth_service VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci'
        );
        $this->addSql('ALTER TABLE hardware_group CHANGE id id VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE login CHANGE username username VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci');
        $this->addSql(
            'ALTER TABLE pipeline_runtime_environment CHANGE runtime_environment_id runtime_environment_id VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci'
        );
        $this->addSql(
            'ALTER TABLE reference_solution_submission CHANGE hw_group_id hw_group_id VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci'
        );
        $this->addSql('ALTER TABLE runtime_environment CHANGE id id VARCHAR(255) NOT NULL COLLATE utf8_unicode_ci');
        $this->addSql(
            'ALTER TABLE solution CHANGE runtime_environment_id runtime_environment_id VARCHAR(255) DEFAULT NULL COLLATE utf8_unicode_ci'
        );

        $this->addSql('SET FOREIGN_KEY_CHECKS=1');
    }
}
