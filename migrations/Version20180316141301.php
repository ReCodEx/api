<?php

namespace Migrations;

use Doctrine\DBAL\DBALException;
use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use App\Helpers\Yaml;

/**
 * Migrate Node, PHP and Python to reflect new entry points and multiple file inputs.
 */
class Version20180316141301 extends AbstractMigration
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
     * Update array of ports
     * @param $box
     * @param $portArray
     */
    private function updateHolyMadafokingPortArray(&$box, &$portArray)
    {
        foreach ($portArray as $portName => &$port) {
            if ($port["value"] == self::$SOURCE_FILE_PORT) {
                if ($portName == self::$SOURCE_FILE_PORT) {
                    unset($portArray[$portName]);
                    $portArray[self::$SOURCE_FILES_PORT] = [
                        "type" => self::$FILES_TYPE,
                        "value" => self::$SOURCE_FILES_PORT
                    ];
                } else {
                    $port["type"] = self::$FILES_TYPE;
                    $port["value"] = self::$SOURCE_FILES_PORT;
                }

                if ($box["type"] == self::$FILE_IN_TYPE) {
                    $box["type"] = self::$FILES_IN_TYPE;
                } else {
                    if ($box["type"] == self::$FILE_OUT_TYPE) {
                        $box["type"] = self::$FILES_OUT_TYPE;
                    }
                }
            }

            if ($port["value"] == self::$PYC_FILE_PORT) {
                if ($portName == self::$PYC_FILE_PORT) {
                    unset($portArray[$portName]);
                    $portArray[self::$PYC_FILES_PORT] = [
                        "type" => self::$FILES_TYPE,
                        "value" => self::$PYC_FILES_PORT
                    ];
                } else {
                    $port["type"] = self::$FILES_TYPE;
                    $port["value"] = self::$PYC_FILES_PORT;
                }

                if ($box["type"] == self::$FILE_IN_TYPE) {
                    $box["type"] = self::$FILES_IN_TYPE;
                } else {
                    if ($box["type"] == self::$FILE_OUT_TYPE) {
                        $box["type"] = self::$FILES_OUT_TYPE;
                    }
                }
            }
        }
    }

    /**
     * Add entry point and rename source-file to source-files variables into given pipeline.
     * @param string $pipelineName
     * @param string $entryBox
     * @param bool $hasEntryPoint
     * @throws DBALException
     */
    private function updatePipeline(string $pipelineName, string $entryBox, bool $hasEntryPoint)
    {
        $pipeline = $this->connection->executeQuery(
            "SELECT * FROM pipeline WHERE name = :name",
            ["name" => $pipelineName]
        )->fetch();
        $pipelineConfig = $this->connection->executeQuery(
            "SELECT * FROM pipeline_config WHERE id = :id",
            ["id" => $pipeline["pipeline_config_id"]]
        )->fetch();
        $config = Yaml::parse($pipelineConfig["pipeline_config"]);

        foreach ($config["boxes"] as &$box) {
            $this->updateHolyMadafokingPortArray($box, $box["portsIn"]);
            $this->updateHolyMadafokingPortArray($box, $box["portsOut"]);

            if ($hasEntryPoint && $box["type"] == $entryBox) {
                $box["portsIn"][self::$ENTRY_POINT_PORT] = [
                    "type" => self::$FILE_TYPE,
                    "value" => self::$ENTRY_POINT_PORT
                ];
            }
        }

        foreach ($config["variables"] as &$variable) {
            if ($variable["name"] == self::$SOURCE_FILE_PORT) {
                $variable["name"] = self::$SOURCE_FILES_PORT;
                $variable["type"] = self::$FILES_TYPE;
                $variable["value"] = [];
            }

            if ($variable["name"] == self::$PYC_FILE_PORT) {
                $variable["name"] = self::$PYC_FILES_PORT;
                $variable["type"] = self::$FILES_TYPE;
                $variable["value"] = [];
            }
        }

        if ($hasEntryPoint) {
            $config["variables"][] = [
                "name" => self::$ENTRY_POINT_PORT,
                "type" => self::$FILE_TYPE,
                "value" => self::$ENTRY_POINT_REF
            ];
        }

        $this->connection->executeQuery(
            "UPDATE pipeline_config SET pipeline_config = :config WHERE id = :id",
            ["id" => $pipelineConfig["id"], "config" => Yaml::dump($config)]
        );
    }

    /**
     * Change runtime_environment to reflect multiple files and also all
     * environment exercises configurations.
     * @param string $environmentId
     * @param string $ext
     * @throws DBALException
     */
    private function updateEnvironment(string $environmentId, string $ext)
    {
        $defVars = "- name: \"source-files\"\n  type: \"file[]\"\n  value: \"*.{$ext}\"";
        $this->connection->executeQuery(
            "UPDATE runtime_environment SET default_variables = :variables WHERE id = :id",
            ["id" => $environmentId, "variables" => $defVars]
        );

        // update exercise environment configs
        $configsResult = $this->connection->executeQuery(
            "SELECT * FROM exercise_environment_config WHERE runtime_environment_id = :id",
            ["id" => $environmentId]
        );
        foreach ($configsResult as $configRow) {
            $config = Yaml::parse($configRow["variables_table"]);

            foreach ($config as &$variable) {
                if ($variable["name"] !== self::$SOURCE_FILE_PORT) {
                    continue;
                }

                $variable["name"] = self::$SOURCE_FILES_PORT;
                $variable["type"] = self::$FILES_TYPE;
            }

            $config[] = [
                "name" => self::$ENTRY_POINT_PORT,
                "type" => self::$FILE_TYPE,
                "value" => self::$ENTRY_POINT_REF
            ];

            $this->connection->executeQuery(
                "UPDATE exercise_environment_config SET variables_table = :config WHERE id = :id",
                ["id" => $configRow["id"], "config" => Yaml::dump($config)]
            );
        }
    }

    /**
     * Update pass-through pipelines of exercise configs of given runtime environments which just got multiple submit files.
     * @param array $environments
     * @throws DBALException
     */
    private function updateExerciseConfig(array $environments)
    {
        $passthroughFilePipeline = $this->connection->executeQuery(
            "SELECT * FROM pipeline WHERE name = :name",
            ["name" => "Compilation source file pass-through"]
        )->fetch()["id"];
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
                    if (!in_array($envName, $environments)) {
                        continue;
                    }

                    foreach ($env["pipelines"] as &$pipeline) {
                        if ($pipeline["name"] == $passthroughFilePipeline) {
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
     * For given environment add solution parameter with the uploaded file as entry point.
     * @param string $environmentId
     * @throws DBALException
     */
    private function addSolutionParams(string $environmentId)
    {
        $solutionsResult = $this->connection->executeQuery(
            "SELECT * FROM solution WHERE runtime_environment_id = :id",
            ["id" => $environmentId]
        );
        foreach ($solutionsResult as $solution) {
            $filesResult = $configsResult = $this->connection->executeQuery(
                "SELECT * FROM uploaded_file WHERE solution_id = :id",
                ["id" => $solution["id"]]
            );

            if ($filesResult->rowCount() == 0) {
                continue;
            }

            $file = $filesResult->fetch();
            $solutionParams = [
                "variables" => [
                    ["name" => self::$ENTRY_POINT_PORT, "value" => $file["name"]]
                ]
            ];

            $this->connection->executeQuery(
                "UPDATE solution SET solution_params = :params WHERE id = :id",
                ["id" => $solution["id"], "params" => Yaml::dump($solutionParams)]
            );
        }
    }

    /**
     * @param Schema $schema
     * @throws DBALException
     */
    public function up(Schema $schema): void
    {
        $this->connection->beginTransaction();

        $this->updatePipeline("Python execution & evaluation [stdout]", "python3", true);
        $this->updatePipeline("Python execution & evaluation [outfile]", "python3", true);
        $this->updatePipeline("Python compilation", "python3", false);
        $this->updatePipeline("PHP execution & evaluation [stdout]", "php", true);
        $this->updatePipeline("PHP execution & evaluation [outfile]", "php", true);
        $this->updatePipeline("Node.js execution & evaluation [stdout]", "node", true);
        $this->updatePipeline("Node.js execution & evaluation [outfile]", "node", true);

        $this->updateEnvironment("node-linux", "js");
        $this->updateEnvironment("php-linux", "php");
        $this->updateEnvironment("python3", "py");

        $this->updateExerciseConfig(["node-linux", "php-linux"]);

        $this->addSolutionParams("node-linux");
        $this->addSolutionParams("php-linux");
        $this->addSolutionParams("python3");

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
