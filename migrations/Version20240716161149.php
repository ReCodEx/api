<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use App\Helpers\ExerciseConfig\Pipeline\Box\ElfExecutionBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\HaskellExecutionBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\JvmRunBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\WrappedExecutionBox;
use App\Helpers\ExerciseConfig\Pipeline\Box\ScriptExecutionBox;
use App\Helpers\Yaml;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20240716161149 extends AbstractMigration
{
    private $boxes;

    public function __construct(...$args)
    {
        parent::__construct(...$args);
        $this->boxes = [
            ElfExecutionBox::$BOX_TYPE => true,
            HaskellExecutionBox::$BOX_TYPE => true,
            JvmRunBox::$BOX_TYPE => true,
            WrappedExecutionBox::$BOX_TYPE => true,
            ScriptExecutionBox::$BOX_TYPE => true,
        ];
    }

    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assignment ADD can_view_measured_values TINYINT(1) NOT NULL');

        $this->addSql('ALTER TABLE assignment_localized_exercise DROP FOREIGN KEY FK_9C8F78CD19302F8');
        $this->addSql('ALTER TABLE assignment_localized_exercise ADD CONSTRAINT FK_9DF069D6D19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE assignment_solution DROP FOREIGN KEY FK_DB055AF3D19302F8');
        $this->addSql('ALTER TABLE assignment_solution DROP FOREIGN KEY FK_DB055AF31C0BE183');
        $this->addSql('ALTER TABLE assignment_solution ADD CONSTRAINT FK_5B315D2ED19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id)');
        $this->addSql('ALTER TABLE assignment_solution ADD CONSTRAINT FK_5B315D2E1C0BE183 FOREIGN KEY (solution_id) REFERENCES solution (id)');
        $this->addSql('ALTER TABLE exercise_localized_exercise DROP FOREIGN KEY FK_8327FD4EE934951A');
        $this->addSql('ALTER TABLE exercise_localized_exercise ADD CONSTRAINT FK_98A84F90E934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercise_attachment_file DROP FOREIGN KEY FK_C0EAC68CED6C0B59');
        $this->addSql('ALTER TABLE exercise_attachment_file DROP FOREIGN KEY FK_C0EAC68CE934951A');
        $this->addSql('ALTER TABLE exercise_attachment_file ADD CONSTRAINT FK_24161E21E934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercise_attachment_file ADD CONSTRAINT FK_24161E215B5E2CEA FOREIGN KEY (attachment_file_id) REFERENCES `uploaded_file` (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE localized_exercise DROP FOREIGN KEY FK_7C6ACF3E3EA4CB4D');
        $this->addSql('ALTER TABLE localized_exercise ADD CONSTRAINT FK_BCAD00373EA4CB4D FOREIGN KEY (created_from_id) REFERENCES localized_exercise (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_62BA741F79F7D87D');
        $this->addSql('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_62BA741FA398D0F9');
        $this->addSql('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_62BA741F456C5646');
        $this->addSql('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_62BA741FFA3CA3B7');
        $this->addSql('ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_AA9C8B99FA3CA3B7 FOREIGN KEY (reference_solution_id) REFERENCES reference_exercise_solution (id)');
        $this->addSql('ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_AA9C8B99A398D0F9 FOREIGN KEY (hw_group_id) REFERENCES hardware_group (id)');
        $this->addSql('ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_AA9C8B9979F7D87D FOREIGN KEY (submitted_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_AA9C8B99456C5646 FOREIGN KEY (evaluation_id) REFERENCES solution_evaluation (id)');
    }

    public function postUp(Schema $schema): void
    {
        /*
         * scan all pipelines
         */
        $pipelines = $this->connection->fetchAllAssociative('SELECT p.id AS id, pc.id AS cid, pc.pipeline_config AS `config`
            FROM pipeline AS p JOIN pipeline_config AS pc ON p.pipeline_config_id = pc.id');
        $modifiedPipelines = [];
        foreach ($pipelines as $pipeline) {
            $id = $pipeline['id'];
            $config = Yaml::parse($pipeline['config']);
            if (empty($config['boxes'])) {
                continue;
            }

            // scan all pipeline boxes
            foreach ($config['boxes'] as &$box) {
                // if this is one of the boxes that needs updating and it still does not have the 'success-exit-codes'
                if (array_key_exists($box['type'], $this->boxes) && empty($box['portsIn']['success-exit-codes'])) {
                    $modifiedPipelines[$id] = true;  // mark the pipeline as modified
                    $box['portsIn']['success-exit-codes'] = [ // add the port
                        'type' => 'string[]',
                        'value' => 'success-exit-codes',
                    ];
                }
            }

            if (!empty($modifiedPipelines[$id])) { // if the configuration was modified...
                // let's make sure we have a variable connected to newly created port...
                $secVariable = array_filter($config['variables'], function ($var) {
                    return $var['name'] === 'success-exit-codes';
                });
                if (!$secVariable) {
                    // add the variable if missing
                    $config['variables'][] = [
                        'name' => 'success-exit-codes',
                        'type' => 'string[]',
                        'value' => '$success-exit-codes',  // refers to external var in exercise config
                    ];
                }

                // ... and save the modified pipeline config
                $this->connection->executeQuery(
                    "UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id",
                    ['id' => $pipeline['cid'], 'config' => Yaml::dump($config)]
                );
            }
        }

        /*
         * scan all exercise configs
         */
        $econfigs = $this->connection->fetchAllKeyValue('SELECT id, `config` FROM exercise_config');
        foreach ($econfigs as $id => $configYaml) {
            $config = Yaml::parse($configYaml);
            if (empty($config['tests'])) {
                continue;
            }

            $modified = false;
            foreach ($config['tests'] as &$test) {
                foreach ($test['environments'] as &$environment) {
                    foreach ($environment['pipelines'] as &$pipeline) {
                        // check if the pipeline was modified
                        if (!$modifiedPipelines[$pipeline['name']]) {
                            continue;
                        }

                        // ... if so, we must ensure that success-exit-codes variable is in the configuration
                        $secVariable = array_filter($pipeline['variables'], function ($var) {
                            return $var['name'] === 'success-exit-codes';
                        });

                        if (!$secVariable) {
                            // add the variable if it is not present
                            $pipeline['variables'][] = [
                                'name' => 'success-exit-codes',
                                'type' => 'string[]',
                                'value' => ['0'], // zero exit code is the default success
                            ];
                            $modified = true;
                        }
                    }
                }
            }

            if ($modified) {
                // save the changes
                $this->connection->executeQuery(
                    "UPDATE exercise_config SET `config` = :config WHERE id = :id",
                    ['id' => $id, 'config' => Yaml::dump($config)]
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assignment DROP can_view_measured_values');
        $this->addSql('ALTER TABLE assignment_localized_exercise DROP FOREIGN KEY FK_9DF069D6D19302F8');
        $this->addSql('ALTER TABLE assignment_localized_exercise ADD CONSTRAINT FK_9C8F78CD19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE assignment_solution DROP FOREIGN KEY FK_5B315D2ED19302F8');
        $this->addSql('ALTER TABLE assignment_solution DROP FOREIGN KEY FK_5B315D2E1C0BE183');
        $this->addSql('ALTER TABLE assignment_solution ADD CONSTRAINT FK_DB055AF3D19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id)');
        $this->addSql('ALTER TABLE assignment_solution ADD CONSTRAINT FK_DB055AF31C0BE183 FOREIGN KEY (solution_id) REFERENCES solution (id)');
        $this->addSql('ALTER TABLE exercise_attachment_file DROP FOREIGN KEY FK_24161E21E934951A');
        $this->addSql('ALTER TABLE exercise_attachment_file DROP FOREIGN KEY FK_24161E215B5E2CEA');
        $this->addSql('ALTER TABLE exercise_attachment_file ADD CONSTRAINT FK_C0EAC68CED6C0B59 FOREIGN KEY (attachment_file_id) REFERENCES uploaded_file (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercise_attachment_file ADD CONSTRAINT FK_C0EAC68CE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercise_localized_exercise DROP FOREIGN KEY FK_98A84F90E934951A');
        $this->addSql('ALTER TABLE exercise_localized_exercise ADD CONSTRAINT FK_8327FD4EE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE localized_exercise DROP FOREIGN KEY FK_BCAD00373EA4CB4D');
        $this->addSql('ALTER TABLE localized_exercise ADD CONSTRAINT FK_7C6ACF3E3EA4CB4D FOREIGN KEY (created_from_id) REFERENCES localized_exercise (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_AA9C8B99FA3CA3B7');
        $this->addSql('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_AA9C8B99A398D0F9');
        $this->addSql('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_AA9C8B9979F7D87D');
        $this->addSql('ALTER TABLE reference_solution_submission DROP FOREIGN KEY FK_AA9C8B99456C5646');
        $this->addSql('ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_62BA741F79F7D87D FOREIGN KEY (submitted_by_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_62BA741FA398D0F9 FOREIGN KEY (hw_group_id) REFERENCES hardware_group (id)');
        $this->addSql('ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_62BA741F456C5646 FOREIGN KEY (evaluation_id) REFERENCES solution_evaluation (id)');
        $this->addSql('ALTER TABLE reference_solution_submission ADD CONSTRAINT FK_62BA741FFA3CA3B7 FOREIGN KEY (reference_solution_id) REFERENCES reference_exercise_solution (id)');
    }

    public function postDown(Schema $schema): void
    {
        // scan all pipelines
        $pipelines = $this->connection->fetchAllAssociative('SELECT p.id AS id, pc.id AS cid, pc.pipeline_config AS `config`
            FROM pipeline AS p JOIN pipeline_config AS pc ON p.pipeline_config_id = pc.id');
        $modifiedPipelines = [];
        foreach ($pipelines as $pipeline) {
            $id = $pipeline['id'];
            $config = Yaml::parse($pipeline['config']);
            if (empty($config['boxes'])) {
                continue;
            }

            // scan all pipeline boxes
            foreach ($config['boxes'] as &$box) {
                // remove success-exit-codes port
                if (array_key_exists($box['type'], $this->boxes) && !empty($box['portsIn']['success-exit-codes'])) {
                    $modifiedPipelines[$id] = true;  // mark the pipeline as modified
                    unset($box['portsIn']['success-exit-codes']);
                }
            }

            if (!empty($modifiedPipelines[$id])) { // if the configuration was modified...
                // let's make sure the corresponding variable is removed as well
                $config['variables'] = array_filter($config['variables'], function ($var) {
                    return $var['name'] !== 'success-exit-codes';
                });

                // ... and save the modified pipeline config
                $this->connection->executeQuery(
                    "UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id",
                    ['id' => $pipeline['cid'], 'config' => Yaml::dump($config)]
                );
            }
        }

        // scan all exercise configs
        $econfigs = $this->connection->fetchAllKeyValue('SELECT id, `config` FROM exercise_config');
        foreach ($econfigs as $id => $configYaml) {
            $config = Yaml::parse($configYaml);
            if (empty($config['tests'])) {
                continue;
            }

            $modified = false;
            foreach ($config['tests'] as &$test) {
                foreach ($test['environments'] as &$environment) {
                    foreach ($environment['pipelines'] as &$pipeline) {
                        // check if the pipeline was modified
                        if (!$modifiedPipelines[$pipeline['name']]) {
                            continue;
                        }

                        // ... if so, we must remove success-exit-codes variable
                        $variables = array_filter($pipeline['variables'], function ($var) {
                            return $var['name'] !== 'success-exit-codes';
                        });
                        if (count($variables) !== count($pipeline['variables'])) {
                            $pipeline['variables'] = $variables;
                            $modified = true;
                        }
                    }
                }
            }

            if ($modified) {
                // save the changes
                $this->connection->executeQuery(
                    "UPDATE exercise_config SET config = :config WHERE id = :id",
                    ['id' => $id, 'config' => Yaml::dump($config)]
                );
            }
        }
    }
}
