<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230114002813 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE plagiarism_detected_similar_file (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', detected_similarity_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', file VARCHAR(255) NOT NULL, fragments TEXT NOT NULL, INDEX IDX_44236D1819466293 (detected_similarity_id), INDEX IDX_44236D181C0BE183 (solution_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE plagiarism_detected_similarity (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', batch_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', tested_solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', tested_file VARCHAR(255) NOT NULL, similarity DOUBLE PRECISION NOT NULL, INDEX IDX_626C38E7F39EBE7A (batch_id), INDEX IDX_626C38E7F675F31B (author_id), INDEX IDX_626C38E7A7494B3B (tested_solution_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('CREATE TABLE plagiarism_detection_batch (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', supervisor_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', detection_tool VARCHAR(255) NOT NULL, detection_tool_parameters VARCHAR(255) NOT NULL, upload_completed_at DATETIME DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_C35646BF19E9AC5F (supervisor_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE plagiarism_detected_similar_file ADD CONSTRAINT FK_44236D1819466293 FOREIGN KEY (detected_similarity_id) REFERENCES plagiarism_detected_similarity (id)');
        $this->addSql('ALTER TABLE plagiarism_detected_similar_file ADD CONSTRAINT FK_44236D181C0BE183 FOREIGN KEY (solution_id) REFERENCES assignment_solution (id)');
        $this->addSql('ALTER TABLE plagiarism_detected_similarity ADD CONSTRAINT FK_626C38E7F39EBE7A FOREIGN KEY (batch_id) REFERENCES plagiarism_detection_batch (id)');
        $this->addSql('ALTER TABLE plagiarism_detected_similarity ADD CONSTRAINT FK_626C38E7F675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE plagiarism_detected_similarity ADD CONSTRAINT FK_626C38E7A7494B3B FOREIGN KEY (tested_solution_id) REFERENCES assignment_solution (id)');
        $this->addSql('ALTER TABLE plagiarism_detection_batch ADD CONSTRAINT FK_C35646BF19E9AC5F FOREIGN KEY (supervisor_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE assignment_solution ADD plagiarism_batch_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE assignment_solution ADD CONSTRAINT FK_5B315D2E5B4CC7BF FOREIGN KEY (plagiarism_batch_id) REFERENCES plagiarism_detection_batch (id)');
        $this->addSql('CREATE INDEX IDX_5B315D2E5B4CC7BF ON assignment_solution (plagiarism_batch_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE plagiarism_detected_similar_file DROP FOREIGN KEY FK_44236D1819466293');
        $this->addSql('ALTER TABLE assignment_solution DROP FOREIGN KEY FK_5B315D2E5B4CC7BF');
        $this->addSql('ALTER TABLE plagiarism_detected_similarity DROP FOREIGN KEY FK_626C38E7F39EBE7A');
        $this->addSql('DROP TABLE plagiarism_detected_similar_file');
        $this->addSql('DROP TABLE plagiarism_detected_similarity');
        $this->addSql('DROP TABLE plagiarism_detection_batch');
        $this->addSql('DROP INDEX IDX_5B315D2E5B4CC7BF ON assignment_solution');
        $this->addSql('ALTER TABLE assignment_solution DROP plagiarism_batch_id');
    }
}
