<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use App\Helpers\Evaluation\SimpleScoreCalculator;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20200418223054 extends AbstractMigration
{
    /*
     * Fortunately, there is but only score calculator at present and all score_calculator fields are null.
     * Hence, we create only one type of ExerciseScoreConfigs baring SimpleScoreCalculator ID.
     */
    
    public function getDescription(): string
    {
        return '';
    }

    /*
     * 'Up' variables that keep data that needs migrating
     */
    private $upScoreConfigs = []; // list of existing score configs [idx => config-string]
    private $upExerciseScoreConfigs = []; // list of exercise score configs [ exercise_id => upScoreConfigs-idx ]
    private $upAssignmentScoreConfigs = []; // list of assignment score configs [ assignment_id => upScoreConfigs - idx ]

    /**
     * @param Schema $schema
     */
    public function preUp(Schema $schema): void
    {
        // Fill in exercise configs (they form the base)
        $exercises = $this->connection->executeQuery("SELECT id, score_config, updated_at FROM exercise");
        foreach ($exercises as $exercise) {
            $this->upExerciseScoreConfigs[$exercise['id']] = count($this->upScoreConfigs);
            $this->upScoreConfigs[] = $exercise;
        }

        // Fill in the assignment configs -- only when they differ from their respective exercise configs...
        $assignments = $this->connection->executeQuery("SELECT id, score_config, exercise_id, updated_at FROM assignment");
        foreach ($assignments as $assignment) {
            $config = $assignment['score_config'];
            $exerciseId = $assignment['exercise_id'];
            $exerciseConfig = $this->upScoreConfigs[$this->upExerciseScoreConfigs[$exerciseId]];
            if ($config === $exerciseConfig['score_config']) {
                // Use the same index so later we can assign the same id
                $idx = $this->upExerciseScoreConfigs[$exerciseId];
            } else {
                // Differs from exercise config -> must have own copy
                $idx = count($this->upScoreConfigs);
                $this->upScoreConfigs[] = $assignment;
            }
            $this->upAssignmentScoreConfigs[$assignment['id']] = $idx;
        }
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE exercise_score_config (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', calculator VARCHAR(255) NOT NULL, config LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', INDEX IDX_DDC8DD8B3EA4CB4D (created_from_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE exercise_score_config ADD CONSTRAINT FK_DDC8DD8B3EA4CB4D FOREIGN KEY (created_from_id) REFERENCES exercise_score_config (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE assignment ADD score_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', DROP score_calculator, DROP score_config');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT FK_30C544BA7AF2FC52 FOREIGN KEY (score_config_id) REFERENCES exercise_score_config (id)');
        $this->addSql('CREATE INDEX IDX_30C544BA7AF2FC52 ON assignment (score_config_id)');
        $this->addSql('ALTER TABLE exercise ADD score_config_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', DROP score_calculator, DROP score_config');
        $this->addSql('ALTER TABLE exercise ADD CONSTRAINT FK_AEDAD51C7AF2FC52 FOREIGN KEY (score_config_id) REFERENCES exercise_score_config (id)');
        $this->addSql('CREATE INDEX IDX_AEDAD51C7AF2FC52 ON exercise (score_config_id)');
    }

    /**
     * @param Schema $schema
     */
    public function postUp(Schema $schema): void
    {
        // Create new score config entities
        foreach ($this->upScoreConfigs as &$config) {
            $uuid = $this->connection->executeQuery('SELECT UUID()')->fetchColumn(); // this is the only way...
            $this->connection->executeQuery(
                "INSERT INTO exercise_score_config (id, calculator, config, created_at) VALUES (:id, :calculator, :config, :created_at)",
                [
                    "id" => $uuid,
                    "calculator" => SimpleScoreCalculator::ID,
                    "config" => $config['score_config'],
                    "created_at" => $config['updated_at'], // we do not have exact creation time, last update is the best approximation
                ]
            );
            $config = $uuid;
        }
        unset($config); // safeguard - it was a reference

        // Fill in exercise FKs
        foreach ($this->upExerciseScoreConfigs as $exerciseId => $idx) {
            $this->connection->executeQuery(
                "UPDATE exercise SET score_config_id = :cid WHERE id = :eid",
                [ "cid" => $this->upScoreConfigs[$idx], "eid" => $exerciseId ]
            );
        }

        // Fill in assignment FKs
        foreach ($this->upAssignmentScoreConfigs as $assignmentId => $idx) {
            $this->connection->executeQuery(
                "UPDATE assignment SET score_config_id = :cid WHERE id = :aid",
                [ "cid" => $this->upScoreConfigs[$idx], "aid" => $assignmentId ]
            );
        }
    }


    /*
     * 'Down' variables that keep data that needs migrating
     */
    private $downScoreConfigs = [
        'exercise' => null,
        'assignment' => null,
    ]; // list of score configs [ table => [ exercise_id => config ] ]

    /**
     * @param Schema $schema
     */
    public function preDown(Schema $schema): void
    {
        $unconvertableConfigsCount = $this->connection->executeQuery(
            "SELECT COUNT(*) FROM exercise_score_config WHERE calculator != :calculator",
            ["calculator" => SimpleScoreCalculator::ID]
        )->fetchColumn();
        $this->abortIf($unconvertableConfigsCount > 0, 'Some of the configs cannot be converted back to previous format.');

        foreach ($this->downScoreConfigs as $table => $_) {
            $this->downScoreConfigs[$table] = $this->connection->executeQuery(
                "SELECT t.id AS id, esc.config AS config FROM $table AS t
                JOIN exercise_score_config AS esc ON esc.id = t.score_config_id
                WHERE esc.calculator = :calculator",
                ["calculator" => SimpleScoreCalculator::ID]
            )->fetchAll();
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BA7AF2FC52');
        $this->addSql('ALTER TABLE exercise DROP FOREIGN KEY FK_AEDAD51C7AF2FC52');
        $this->addSql('ALTER TABLE exercise_score_config DROP FOREIGN KEY FK_DDC8DD8B3EA4CB4D');
        $this->addSql('DROP TABLE exercise_score_config');
        $this->addSql('DROP INDEX IDX_30C544BA7AF2FC52 ON assignment');
        $this->addSql('ALTER TABLE assignment ADD score_calculator VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD score_config LONGTEXT NOT NULL COLLATE utf8mb4_unicode_ci, DROP score_config_id');
        $this->addSql('DROP INDEX IDX_AEDAD51C7AF2FC52 ON exercise');
        $this->addSql('ALTER TABLE exercise ADD score_calculator VARCHAR(255) DEFAULT NULL COLLATE utf8mb4_unicode_ci, ADD score_config LONGTEXT NOT NULL COLLATE utf8mb4_unicode_ci, DROP score_config_id');
    }

    /**
     * @param Schema $schema
     */
    public function postDown(Schema $schema): void
    {
        foreach ($this->downScoreConfigs as $table => $data) {
            foreach ($data as $config) {
                $this->connection->executeQuery(
                    "UPDATE $table SET score_config = :config WHERE id = :id",
                    [ "id" => $config['id'], "config" => $config['config'] ]
                );
            }
        }
    }
}
