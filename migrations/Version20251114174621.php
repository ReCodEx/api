<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20251114174621 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE exercise_file_link (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', exercise_file_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', exercise_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', assignment_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', `key` VARCHAR(16) NOT NULL, save_name VARCHAR(255) DEFAULT NULL, required_role VARCHAR(255) DEFAULT NULL, created_at DATETIME NOT NULL, INDEX IDX_1187F77549DE8E29 (exercise_file_id), INDEX IDX_1187F775E934951A (exercise_id), INDEX IDX_1187F775D19302F8 (assignment_id), UNIQUE INDEX UNIQ_1187F7758A90ABA9E934951A (`key`, exercise_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE exercise_file_link ADD CONSTRAINT FK_1187F77549DE8E29 FOREIGN KEY (exercise_file_id) REFERENCES `uploaded_file` (id)');
        $this->addSql('ALTER TABLE exercise_file_link ADD CONSTRAINT FK_1187F775E934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id)');
        $this->addSql('ALTER TABLE exercise_file_link ADD CONSTRAINT FK_1187F775D19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id)');
        $this->addSql('ALTER TABLE uploaded_file DROP is_public');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exercise_file_link DROP FOREIGN KEY FK_1187F77549DE8E29');
        $this->addSql('ALTER TABLE exercise_file_link DROP FOREIGN KEY FK_1187F775E934951A');
        $this->addSql('ALTER TABLE exercise_file_link DROP FOREIGN KEY FK_1187F775D19302F8');
        $this->addSql('DROP TABLE exercise_file_link');
        $this->addSql('ALTER TABLE `uploaded_file` ADD is_public TINYINT(1) NOT NULL');
    }
}
