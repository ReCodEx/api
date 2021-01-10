<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use App\Helpers\Yaml;
use App\Helpers\YamlException;
use App\Helpers\Evaluation\WeightedScoreCalculator;

/**
 * Manual migration.
 * Identifies weighted score configs which are in fact uniform and change them accordingly.
 */
final class Version20200421000600 extends AbstractMigration
{
    private const UNIFORM_ID = 'uniform'; // This should match UniformScoreCalculator::ID
    private const WEIGHTED_ID = WeightedScoreCalculator::ID; // This should match WeightedScoreCalculator::ID (former SimpleScoreCalculator)

    public function getDescription(): string
    {
        return '';
    }

    /**
     * Verify whether given weighted configuration is in fact uniform.
     * Uniform configs have all the weights the same.
     */
    private function isUniform($config)
    {
        try {
            $yaml = Yaml::parse($config);
            if (!isset($yaml['testWeights']) || !is_array($yaml['testWeights'])) {
                return false;
            }
            
            $lastValue = null;
            foreach ($yaml['testWeights'] as $value) {
                if (!is_integer($value)) {
                    return false;
                }
                if ($lastValue !== null && $lastValue !== $value) {
                    return false; // at least one value is different to others
                }
                $lastValue = $value;
            }

            return true; // if we got here, all uniformity checks must have passed
        } catch (YamlException $e) {
            return false;
        }
    }

    public function up(Schema $schema): void
    {
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $scoreConfigs = $this->connection->executeQuery(
            "SELECT id, config FROM exercise_score_config WHERE calculator = :calculator",
            [ "calculator" => self::WEIGHTED_ID ]
        )->fetchAllAssociative();

        foreach ($scoreConfigs as $config) {
            if (!$config['config'] || $this->isUniform($config['config'])) {
                $this->addSql(
                    'UPDATE exercise_score_config SET calculator = :calculator, config = NULL WHERE id = :id',
                    [ 'id' => $config['id'], 'calculator' => self::UNIFORM_ID ]
                );
            }
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $calculator = new WeightedScoreCalculator();

        foreach (['exercise', 'assignment'] as $table) {
            // get uniform configs to be transformed...
            $scoreConfigs = $this->connection->executeQuery(
                "SELECT t.id AS tid, esc.id AS id FROM $table AS t
                JOIN exercise_score_config AS esc ON t.score_config_id = esc.id WHERE esc.calculator = :calculator",
                [ "calculator" => self::UNIFORM_ID ]
            )->fetchAllAssociative();

            foreach ($scoreConfigs as $config) {
                // get list of tests so we can use it to construct config
                $tests = $this->connection->executeQuery(
                    "SELECT et.name AS `name` FROM exercise_test AS et
                    JOIN ${table}_exercise_test AS eet ON eet.exercise_test_id = et.id WHERE eet.${table}_id = :tid",
                    [ 'tid' => $config['tid'] ]
                )->fetchFirstColumn();

                $this->addSql(
                    'UPDATE exercise_score_config SET config = :config, calculator = :calculator WHERE id = :id',
                    [
                        'id' => $config['id'],
                        'config' => $calculator->getDefaultConfig($tests), // weighted config with uniform weights
                        'calculator' => self::WEIGHTED_ID,
                    ]
                );
            }
        }

        // remove all remaining configs with uniform calculator (since they are not attached to exercise nor assignment)
        $this->addSql(
            'DELETE FROM exercise_score_config WHERE calculator = :calculator',
            [ "calculator" => self::UNIFORM_ID ]
        );
    }
}
