<?php

namespace Migrations;

use Doctrine\DBAL\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171130122128 extends AbstractMigration
{
  /**
   * @param Schema $schema
   */
  public function up(Schema $schema)
  {
    // this up() migration is auto-generated, please modify it to your needs
    $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

    $this->addSql('CREATE TABLE assignment_supplementary_exercise_file (assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', supplementary_exercise_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_D6457EA6D19302F8 (assignment_id), INDEX IDX_D6457EA62D777971 (supplementary_exercise_file_id), PRIMARY KEY(assignment_id, supplementary_exercise_file_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
    $this->addSql('CREATE TABLE assignment_attachment_file (assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', attachment_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_51F652B7D19302F8 (assignment_id), INDEX IDX_51F652B75B5E2CEA (attachment_file_id), PRIMARY KEY(assignment_id, attachment_file_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
    $this->addSql('ALTER TABLE assignment_supplementary_exercise_file ADD CONSTRAINT FK_D6457EA6D19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id) ON DELETE CASCADE');
    $this->addSql('ALTER TABLE assignment_supplementary_exercise_file ADD CONSTRAINT FK_D6457EA62D777971 FOREIGN KEY (supplementary_exercise_file_id) REFERENCES uploaded_file (id) ON DELETE CASCADE');
    $this->addSql('ALTER TABLE assignment_attachment_file ADD CONSTRAINT FK_51F652B7D19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id) ON DELETE CASCADE');
    $this->addSql('ALTER TABLE assignment_attachment_file ADD CONSTRAINT FK_51F652B75B5E2CEA FOREIGN KEY (attachment_file_id) REFERENCES uploaded_file (id) ON DELETE CASCADE');
  }

  public function postUp(Schema $schema) {
    $this->connection->beginTransaction();

    $assignments = $this->connection->executeQuery("SELECT * FROM assignment");
    foreach ($assignments as $assignment) {
      $supplementaryFiles = $this->connection->executeQuery("SELECT * FROM exercise_supplementary_exercise_file WHERE exercise_id = :id",
        ["id" => $assignment["exercise_id"]]);
      $supplementaryFilesQuery = [];
      foreach ($supplementaryFiles as $file) {
        $supplementaryFilesQuery[] = "('{$assignment["id"]}', '{$file["supplementary_exercise_file_id"]}')";
      }
      if (!empty($supplementaryFilesQuery)) {
        $this->connection->executeQuery("INSERT INTO assignment_supplementary_exercise_file (assignment_id, supplementary_exercise_file_id) VALUES " . implode(', ', $supplementaryFilesQuery));
      }

      $attachmentFiles = $this->connection->executeQuery("SELECT * FROM exercise_attachment_file WHERE exercise_id = :id",
        ["id" => $assignment["exercise_id"]]);
      $attachmentFilesQuery = [];
      foreach ($attachmentFiles as $file) {
        $attachmentFilesQuery[] = "('{$assignment["id"]}', '{$file["attachment_file_id"]}')";
      }
      if (!empty($attachmentFilesQuery)) {
        $this->connection->executeQuery("INSERT INTO assignment_attachment_file (assignment_id, attachment_file_id) VALUES " . implode(', ', $attachmentFilesQuery));
      }
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

    $this->addSql('DROP TABLE assignment_supplementary_exercise_file');
    $this->addSql('DROP TABLE assignment_attachment_file');
  }
}
