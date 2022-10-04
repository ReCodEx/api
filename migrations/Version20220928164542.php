<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20220928164542 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE review_comment (id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', solution_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\', file VARCHAR(255) NOT NULL, line INT NOT NULL, text TEXT NOT NULL, issue TINYINT(1) NOT NULL, created_at DATETIME NOT NULL, INDEX IDX_F9AE69B1C0BE183 (solution_id), INDEX IDX_F9AE69BF675F31B (author_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE review_comment ADD CONSTRAINT FK_F9AE69B1C0BE183 FOREIGN KEY (solution_id) REFERENCES assignment_solution (id)');
        $this->addSql('ALTER TABLE review_comment ADD CONSTRAINT FK_F9AE69BF675F31B FOREIGN KEY (author_id) REFERENCES user (id)');
        $this->addSql('ALTER TABLE user_settings ADD solution_reviews_emails TINYINT(1) NOT NULL');

        // add new cols to assignment_solution
        $this->addSql('ALTER TABLE assignment_solution ADD review_started_at DATETIME DEFAULT NULL, ADD reviewed_at DATETIME DEFAULT NULL, ADD issues INT NOT NULL');

        // perform updates (reviewed flag into new cols)
        $this->addSql('UPDATE assignment_solution AS asol SET reviewed_at = (SELECT created_at FROM solution AS sol WHERE sol.id = asol.solution_id) WHERE reviewed = 1');
        $this->addSql('UPDATE assignment_solution SET review_started_at = reviewed_at WHERE reviewed = 1');

        // remove old reviewed column
        $this->addSql('ALTER TABLE assignment_solution DROP reviewed');
    }

    public function postUp(Schema $schema): void
    {
        $this->connection->executeQuery("UPDATE `user_settings` SET solution_reviews_emails = 1");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('DROP TABLE review_comment');
        $this->addSql('ALTER TABLE assignment_solution ADD reviewed TINYINT(1) NOT NULL');
        $this->addSql('UPDATE assignment_solution SET reviewed = 1 WHERE reviewed_at IS NOT NULL');
        $this->addSql('ALTER TABLE assignment_solution DROP review_started_at, DROP reviewed_at, DROP issues');
        $this->addSql('ALTER TABLE user_settings DROP solution_reviews_emails');
    }
}
