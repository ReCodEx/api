<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use App\Helpers\Yaml;

/**
 * Python box uses custom written runner, so apply this change to the data in
 * database. Note that further action is needed, the runner itself has to be
 * uploaded to the application manually from the location:
 * https://github.com/ReCodEx/utils/blob/master/runners/py/runner.py
 */
class Version20180805075758 extends AbstractMigration
{
    const PYTHON_RUNNER_NAME = "runner.py";
    const PYTHON_BOX = "python3";
    const RUNNER_PORT = "runner";
    const REMOTE_TYPE = "remote-file";
    const FILE_TYPE = "file";
    const FETCH_RUNNER_NAME = "fetch-runner";
    const FETCH_BOX = "fetch-file";
    const RUNNER_FILE_NAME = "runner-filename";

    /**
     * To pipelines with python3 add fetch box and port with python runner.
     * @throws \Doctrine\DBAL\DBALException
     */
    private function updatePipelines()
    {
        $pipelinesResult = $this->connection->executeQuery("SELECT * FROM pipeline");
        foreach ($pipelinesResult as $pipeline) {
            $pipelineConfig = $this->connection->executeQuery(
                "SELECT * FROM pipeline_config WHERE id = '{$pipeline["pipeline_config_id"]}'"
            )->fetchAssociative();
            $config = Yaml::parse($pipelineConfig["pipeline_config"]);

            $found = false;
            foreach ($config["boxes"] as &$box) {
                if ($box["type"] == self::PYTHON_BOX) {
                    $found = true;
                    $box["portsIn"][self::RUNNER_PORT] = [
                        "type" => self::FILE_TYPE,
                        "value" => self::RUNNER_PORT
                    ];
                }
            }

            if ($found) {
                // add runner variable
                $config["variables"][] = [
                    "name" => self::RUNNER_PORT,
                    "type" => self::FILE_TYPE,
                    "value" => ""
                ];

                // add runner hash variable
                $config["variables"][] = [
                    "name" => self::RUNNER_FILE_NAME,
                    "type" => self::REMOTE_TYPE,
                    "value" => self::PYTHON_RUNNER_NAME
                ];

                // add fetch box to download the runner
                $config["boxes"][] = [
                    "name" => self::FETCH_RUNNER_NAME,
                    "type" => self::FETCH_BOX,
                    "portsIn" => [
                        "remote" => [
                            "type" => self::REMOTE_TYPE,
                            "value" => self::RUNNER_FILE_NAME
                        ]
                    ],
                    "portsOut" => [
                        "input" => [
                            "type" => self::FILE_TYPE,
                            "value" => self::RUNNER_PORT
                        ]
                    ]
                ];

                $this->connection->executeQuery(
                    "UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id",
                    ["id" => $pipelineConfig["id"], "config" => Yaml::dump($config)]
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
        $this->updatePipelines();
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
