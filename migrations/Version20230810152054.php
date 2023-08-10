<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20230810152054 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('CREATE TABLE exercise_user (exercise_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', user_id CHAR(36) NOT NULL COMMENT \'(DC2Type:uuid)\', INDEX IDX_D4B6B4FBE934951A (exercise_id), INDEX IDX_D4B6B4FBA76ED395 (user_id), PRIMARY KEY(exercise_id, user_id)) DEFAULT CHARACTER SET utf8mb4 COLLATE `utf8mb4_unicode_ci` ENGINE = InnoDB');
        $this->addSql('ALTER TABLE exercise_user ADD CONSTRAINT FK_D4B6B4FBE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercise_user ADD CONSTRAINT FK_D4B6B4FBA76ED395 FOREIGN KEY (user_id) REFERENCES user (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE exercise ADD archived_at DATETIME DEFAULT NULL');
        $this->addSql('ALTER TABLE reference_exercise_solution ADD visibility INT NOT NULL');
    }

    public function postUp(Schema $schema): void
    {
        $this->connection->executeQuery("UPDATE `reference_exercise_solution` SET visibility = 1");
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE exercise_user DROP FOREIGN KEY FK_D4B6B4FBE934951A');
        $this->addSql('ALTER TABLE exercise_user DROP FOREIGN KEY FK_D4B6B4FBA76ED395');
        $this->addSql('DROP TABLE exercise_user');
        $this->addSql('ALTER TABLE exercise DROP archived_at');
        $this->addSql('ALTER TABLE reference_exercise_solution DROP visibility');
    }
}
