<?php

namespace Migrations;

use Doctrine\DBAL\DBALException;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use App\Helpers\Yaml;

/**
 * Remove python compilation pipelines and replace them with passthrough ones.
 */
class Version20181207152232 extends AbstractMigration
{
    private const FILES_TYPE = "file[]";
    private const SOURCE_FILES_PORT = "source-files";
    private const PYC_FILES_PORT = "pyc-files";
    private const PYTHON_RUN_BOX = "python3";
    private const EXTRA_FILES_PORT = "extra-files";
    private const EXTRAS_BOX_NAME = "extras";
    private const FILES_IN_TYPE = "files-in";

    private const PYTHON_RUN_PIPELINES = [
        "Python execution & evaluation [stdout]",
        "Python execution & evaluation [outfile]"
    ];
    private const PYTHON_COMPILATION_PIPELINE_NAME = "Python compilation";

    /**
     * For Python change their relation to compilation pipeline, which is now source files passthrough.
     * @throws DBALException
     */
    private function updatePassthroughEnvironments()
    {
        $passthroughFilesPipeline = $this->connection->executeQuery(
            "SELECT * FROM pipeline WHERE name = :name",
            ["name" => "Compilation source files pass-through"]
        )->fetch()["id"];

        $python = "python3";
        if ($passthroughFilesPipeline) {
            $this->connection->executeQuery(
                "INSERT INTO pipeline_runtime_environment VALUES (:pid, :envId)",
                ["pid" => $passthroughFilesPipeline, "envId" => $python]
            );
        }
    }

    /**
     * Remove compilation python pipeline which is no longer needed.
     * @throws DBALException
     */
    private function removePythonCompilationPipeline(): ?string
    {
        $pipelineId = $this->connection->executeQuery(
            "SELECT * FROM pipeline WHERE name = :name",
            ["name" => self::PYTHON_COMPILATION_PIPELINE_NAME]
        )->fetch()["id"];

        $this->connection->executeQuery(
            "DELETE FROM pipeline_parameter WHERE pipeline_id = :id",
            ["id" => $pipelineId]
        );
        $this->connection->executeQuery(
            "DELETE FROM pipeline WHERE id = :id",
            ["id" => $pipelineId]
        );

        return $pipelineId;
    }

    /**
     * Update python run pipelines.
     * @return string[]
     * @throws \Doctrine\DBAL\DBALException
     */
    private function updatePythonRunPipelines(): array
    {
        $pipelines = [];
        foreach (self::PYTHON_RUN_PIPELINES as $name) {
            $pipeline = $this->connection->executeQuery(
                "SELECT * FROM pipeline WHERE name = :name",
                ["name" => $name]
            )->fetch();
            $pipelines[] = $pipeline["id"];

            $pipelineConfig = $this->connection->executeQuery(
                "SELECT * FROM pipeline_config WHERE id = '{$pipeline["pipeline_config_id"]}'"
            )->fetch();
            $config = Yaml::parse($pipelineConfig["pipeline_config"]);

            foreach ($config["boxes"] as &$box) {
                foreach ($box["portsIn"] as $portName => $portIn) {
                    if ($portIn["value"] == self::PYC_FILES_PORT) {
                        unset($box["portsIn"][$portName]);
                        $box["portsIn"][self::SOURCE_FILES_PORT] = [
                            "type" => self::FILES_TYPE,
                            "value" => self::SOURCE_FILES_PORT
                        ];
                    }
                }

                foreach ($box["portsOut"] as $portName => &$portOut) {
                    if ($portOut["value"] == self::PYC_FILES_PORT) {
                        $portOut["value"] = self::SOURCE_FILES_PORT;
                    }
                }

                if ($box["type"] == self::PYTHON_RUN_BOX) {
                    $box["portsIn"][self::EXTRA_FILES_PORT] = [
                        "type" => self::FILES_TYPE,
                        "value" => self::EXTRA_FILES_PORT
                    ];
                }
            }

            // add extra files box
            $config["boxes"][] = [
                "name" => self::EXTRAS_BOX_NAME,
                "type" => self::FILES_IN_TYPE,
                "portsIn" => [],
                "portsOut" => [
                    "input" => [
                        "type" => self::FILES_TYPE,
                        "value" => self::EXTRA_FILES_PORT
                    ]
                ]
            ];

            foreach ($config["variables"] as &$variable) {
                if ($variable["name"] == self::PYC_FILES_PORT) {
                    $variable["name"] = self::SOURCE_FILES_PORT;
                    $variable["value"] = "";
                }
            }

            // add extra-files variable
            $config["variables"][] = [
                "name" => self::EXTRA_FILES_PORT,
                "type" => self::FILES_TYPE,
                "value" => []
            ];

            $this->connection->executeQuery(
                "UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id",
                ["id" => $pipelineConfig["id"], "config" => Yaml::dump($config)]
            );
        }

        return $pipelines;
    }

    /**
     * @param string $compilationPipelineId
     * @throws DBALException
     */
    private function updatePythonConfigs(?string $compilationPipelineId)
    {
        $passthroughFilesPipeline = $this->connection->executeQuery(
            "SELECT * FROM pipeline WHERE name = :name",
            ["name" => "Compilation source files pass-through"]
        )->fetch()["id"];

        $configsResult = $this->connection->executeQuery("SELECT * FROM exercise_config");
        foreach ($configsResult as $exerciseConfig) {
            $config = Yaml::parse($exerciseConfig["config"]);

            $changed = false;
            foreach ($config["tests"] as &$test) {
                foreach ($test["environments"] as $envName => &$env) {
                    foreach ($env["pipelines"] as &$pipeline) {
                        if ($pipeline["name"] === $compilationPipelineId) {
                            $pipeline["name"] = $passthroughFilesPipeline;
                            $changed = true;
                        }
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
     * @throws \Doctrine\DBAL\ConnectionException
     * @throws \Doctrine\DBAL\DBALException
     */
    public function up(Schema $schema): void
    {
        $this->connection->beginTransaction();
        $this->updatePassthroughEnvironments();
        $pipelineId = $this->removePythonCompilationPipeline();
        $this->updatePythonRunPipelines();
        $this->updatePythonConfigs($pipelineId);
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
