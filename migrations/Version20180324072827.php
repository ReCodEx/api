<?php

namespace Migrations;

use Doctrine\DBAL\DBALException;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use App\Helpers\Yaml;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180324072827 extends AbstractMigration
{
    public static $FILE_TYPE = "file";
    public static $FILES_TYPE = "file[]";
    public static $FILE_IN_TYPE = "file-in";
    public static $FILES_IN_TYPE = "files-in";
    public static $FILE_OUT_TYPE = "file-out";
    public static $FILES_OUT_TYPE = "files-out";
    public static $SOURCE_FILE_PORT = "source-file";
    public static $SOURCE_FILES_PORT = "source-files";
    public static $PYC_FILE_PORT = "pyc-file";
    public static $PYC_FILES_PORT = "pyc-files";
    public static $ENTRY_POINT_PORT = "entry-point";
    public static $ENTRY_POINT_REF = '$entry-point';

    /**
     * For PHP and Node change their relation to compilation pipeline, which is now source files passthrough.
     * @throws DBALException
     */
    private function updatePassthroughEnvironments()
    {
        $passthroughFilePipeline = $this->connection->executeQuery(
            "SELECT * FROM pipeline WHERE name = :name",
            ["name" => "Compilation source file pass-through"]
        )->fetch()["id"];
        $passthroughFilesPipeline = $this->connection->executeQuery(
            "SELECT * FROM pipeline WHERE name = :name",
            ["name" => "Compilation source files pass-through"]
        )->fetch()["id"];

        $phpid = "php-linux";
        $nodeid = "node-linux";
        $this->connection->executeQuery(
            "DELETE FROM pipeline_runtime_environment WHERE pipeline_id = :pid AND runtime_environment_id = :phpid",
            ["pid" => $passthroughFilePipeline, "phpid" => $phpid]
        );
        $this->connection->executeQuery(
            "DELETE FROM pipeline_runtime_environment WHERE pipeline_id = :pid AND runtime_environment_id = :nodeid",
            ["pid" => $passthroughFilePipeline, "nodeid" => $nodeid]
        );

        if ($passthroughFilesPipeline) {
            $this->connection->executeQuery(
                "INSERT INTO pipeline_runtime_environment VALUES (:pid, :envId)",
                ["pid" => $passthroughFilesPipeline, "envId" => $phpid]
            );
            $this->connection->executeQuery(
                "INSERT INTO pipeline_runtime_environment VALUES (:pid, :envId)",
                ["pid" => $passthroughFilesPipeline, "envId" => $nodeid]
            );
        }
    }

    /**
     * From PHP, Node, Python environments remove entry-point variables.
     * @throws DBALException
     */
    private function updateEnvironment()
    {
        $environments = ["node-linux", "php-linux", "python3"];
        foreach ($environments as $environmentId) {
            $configsResult = $this->connection->executeQuery(
                "SELECT * FROM exercise_environment_config WHERE runtime_environment_id = :id",
                ["id" => $environmentId]
            );
            foreach ($configsResult as $configRow) {
                $config = Yaml::parse($configRow["variables_table"]);

                $newConfig = [];
                foreach ($config as $variable) {
                    if ($variable["name"] === self::$ENTRY_POINT_PORT) {
                        continue;
                    }

                    $newConfig[] = $variable;
                }

                $this->connection->executeQuery(
                    "UPDATE exercise_environment_config SET variables_table = :config WHERE id = :id",
                    ["id" => $configRow["id"], "config" => Yaml::dump($newConfig)]
                );
            }
        }
    }

    /**
     * @return string[]
     * @throws DBALException
     */
    private function getEntryPointsRelevantPipelinesIds(): array
    {
        $pipelinesNames = [
            "Python execution & evaluation [stdout]",
            "Python execution & evaluation [outfile]",
            "PHP execution & evaluation [stdout]",
            "PHP execution & evaluation [outfile]",
            "Node.js execution & evaluation [stdout]",
            "Node.js execution & evaluation [outfile]",
        ];

        $pipelines = [];
        foreach ($pipelinesNames as $name) {
            $pipelines[] = $this->connection->executeQuery(
                "SELECT * FROM pipeline WHERE name = :name",
                ["name" => $name]
            )->fetch()["id"];
        }

        return $pipelines;
    }

    /**
     * Update exercise config for PHP, Node and Python environments and execution
     * pipelines, so they contains submit references on entry-points.
     * @throws DBALException
     */
    private function updateExerciseConfigs()
    {
        $pipelinesIds = $this->getEntryPointsRelevantPipelinesIds();
        $configsResult = $this->connection->executeQuery("SELECT * FROM exercise_config");
        foreach ($configsResult as $exerciseConfig) {
            $config = Yaml::parse($exerciseConfig["config"]);

            $changed = false;
            foreach ($config["tests"] as &$test) {
                foreach ($test["environments"] as $envName => &$env) {
                    foreach ($env["pipelines"] as &$pipeline) {
                        if (!in_array($pipeline["name"], $pipelinesIds)) {
                            continue;
                        }

                        $changed = true;
                        $pipeline["variables"][] = [
                            "name" => self::$ENTRY_POINT_PORT,
                            "type" => self::$FILE_TYPE,
                            "value" => self::$ENTRY_POINT_REF
                        ];
                    }
                }
            }

            if ($changed) {
                $this->connection->executeQuery(
                    "UPDATE exercise_config SET config = :config WHERE id = :id",
                    ["id" => $exerciseConfig["id"], "config" => Yaml::dump($config)]
                );
            }
        }
    }

    /**
     * @param Schema $schema
     * @throws DBALException
     */
    public function up(Schema $schema): void
    {
        $this->connection->beginTransaction();
        $this->updatePassthroughEnvironments();
        $this->updateEnvironment();
        $this->updateExerciseConfigs();
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
