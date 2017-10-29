<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Yaml\Yaml;


/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171028192549 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema)
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE assignment CHANGE score_config score_config LONGTEXT NOT NULL');
        $this->addSql('ALTER TABLE exercise ADD score_calculator VARCHAR(255) DEFAULT NULL, ADD score_config LONGTEXT NOT NULL');
    }

    public function postUp(Schema $schema)
    {
      // Fix each exercise ...
      $exercises = $this->connection->executeQuery("SELECT e.id, ec.config FROM exercise AS e JOIN exercise_config AS ec ON e.exercise_config_id = ec.id");
      foreach ($exercises as $exercise) {
        if (empty($exercise["config"])) {
          continue;
        }
        $id = $exercise["id"];

        // Get the exercise config ...
        $config = Yaml::parse($exercise["config"]);
        if (!empty($config["tests"])) {
          // Prepare test weights ...
          $tests = $config["tests"];
          foreach ($tests as &$value) {
            $value = 100;
          }
        }
        else {
          $tests = [];
        }

        // Fill back newly initialized score configs.
        $res = Yaml::dump([ "testWeights" => $tests ]);
        $this->connection->executeQuery("UPDATE exercise SET score_config = :res WHERE id = :id", [ 'res' => $res, 'id' => $id ]);
      }
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema)
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE assignment CHANGE score_config score_config LONGTEXT DEFAULT NULL COLLATE utf8_unicode_ci');
        $this->addSql('ALTER TABLE exercise DROP score_calculator, DROP score_config');
    }
}
