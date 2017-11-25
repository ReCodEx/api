<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;
use Symfony\Component\Yaml\Yaml;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171124153919 extends AbstractMigration
{
  /**
   * @param Schema $schema
   */
  public function up(Schema $schema)
  {
    // this up() migration is auto-generated, please modify it to your needs
    $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    $this->addSql('CREATE TABLE assignment_exercise_test (assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', exercise_test_id INT NOT NULL, INDEX IDX_49C1327CD19302F8 (assignment_id), INDEX IDX_49C1327CC448F31C (exercise_test_id), PRIMARY KEY(assignment_id, exercise_test_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
    $this->addSql('CREATE TABLE exercise_exercise_test (exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', exercise_test_id INT NOT NULL, INDEX IDX_C3021C67E934951A (exercise_id), INDEX IDX_C3021C67C448F31C (exercise_test_id), PRIMARY KEY(exercise_id, exercise_test_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
    $this->addSql('CREATE TABLE exercise_test (id INT AUTO_INCREMENT NOT NULL, author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(255) NOT NULL, description LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', INDEX IDX_815A1CBEF675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
    $this->addSql('ALTER TABLE assignment_exercise_test ADD CONSTRAINT FK_49C1327CD19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id) ON DELETE CASCADE');
    $this->addSql('ALTER TABLE assignment_exercise_test ADD CONSTRAINT FK_49C1327CC448F31C FOREIGN KEY (exercise_test_id) REFERENCES exercise_test (id) ON DELETE CASCADE');
    $this->addSql('ALTER TABLE exercise_exercise_test ADD CONSTRAINT FK_C3021C67E934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE');
    $this->addSql('ALTER TABLE exercise_exercise_test ADD CONSTRAINT FK_C3021C67C448F31C FOREIGN KEY (exercise_test_id) REFERENCES exercise_test (id) ON DELETE CASCADE');
    $this->addSql('ALTER TABLE exercise_test ADD CONSTRAINT FK_815A1CBEF675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
  }

  /**
   * Go through exercises and assignments and assign ExerciseTest entities to
   * them based on exercise config data
   * @param $exercise
   * @param string $type
   */
  private function createTest($exercise, string $type) {
    if (empty($exercise["exercise_config_id"])) {
      return;
    }

    $exerciseConfig = $this->connection->executeQuery("SELECT * FROM exercise_config WHERE id = '{$exercise["exercise_config_id"]}'")->fetch();
    $authorId = $exerciseConfig["author_id"];
    $config = Yaml::parse($exerciseConfig["config"]);

    foreach ($config["tests"] as $name => $test) {
      $this->connection->executeQuery("INSERT INTO exercise_test (author_id, name, description, created_at, updated_at) " .
        "VALUES (:author, :name, :description, NOW(), NOW())",
        ["author" => $authorId, "name" => $name, "description" => ""]);
      $testId = $this->connection->lastInsertId();
      $this->connection->executeQuery("INSERT INTO {$type}_exercise_test ({$type}_id, exercise_test_id) " .
        "VALUES (:exercise, :testId)", ["exercise" => $exercise["id"], "testId" => $testId]);
    }
  }

  public function postUp(Schema $schema) {
    $this->connection->beginTransaction();

    $exercisesResult = $this->connection->executeQuery("SELECT * FROM exercise");
    foreach ($exercisesResult as $exercise) {
      $this->createTest($exercise, "exercise");
    }

    $assignmentsResult = $this->connection->executeQuery("SELECT * FROM assignment");
    foreach ($assignmentsResult as $assignment) {
      $this->createTest($assignment, "assignment");
    }

    $this->connection->commit();
  }

  /**
   * @param Schema $schema
   */
  public function down(Schema $schema)
  {
    // this down() migration is auto-generated, please modify it to your needs
    $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    $this->addSql('ALTER TABLE assignment_exercise_test DROP FOREIGN KEY FK_49C1327CC448F31C');
    $this->addSql('ALTER TABLE exercise_exercise_test DROP FOREIGN KEY FK_C3021C67C448F31C');
    $this->addSql('DROP TABLE assignment_exercise_test');
    $this->addSql('DROP TABLE exercise_exercise_test');
    $this->addSql('DROP TABLE exercise_test');
  }

}
