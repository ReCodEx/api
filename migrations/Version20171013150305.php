<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171013150305 extends AbstractMigration
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

        $this->addSql('ALTER TABLE test_result ADD used_memory INT NOT NULL, ADD used_time DOUBLE PRECISION NOT NULL');
    }

    /**
     * @param Schema $schema
     */
    public function postUp(Schema $schema): void
    {
        $this->connection->beginTransaction();

        $task_results = $this->connection->executeQuery(
            "SELECT test_result_id, used_time, used_memory FROM task_result"
        );
        foreach ($task_results as $row) {
            $testResultId = $row["test_result_id"];
            $usedTime = $row["used_time"];
            $usedMemory = $row["used_memory"];

            $this->connection->executeQuery(
                "UPDATE test_result SET used_time = $usedTime, used_memory = $usedMemory WHERE id = '$testResultId'"
            );
        }

        $this->connection->commit();
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

        $this->addSql('ALTER TABLE test_result DROP used_memory, DROP used_time');
    }
}
