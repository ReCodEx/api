<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190207114613 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql(
            'CREATE TABLE exercise_tag (id INT AUTO_INCREMENT NOT NULL, author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', exercise_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(32) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', INDEX IDX_95D612FFF675F31B (author_id), INDEX IDX_95D612FFE934951A (exercise_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql('CREATE UNIQUE INDEX UNIQ_95D612FF5E237E06E934951A ON exercise_tag (name, exercise_id)');
        $this->addSql(
            'ALTER TABLE exercise_tag ADD CONSTRAINT FK_95D612FFF675F31B FOREIGN KEY (author_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE exercise_tag ADD CONSTRAINT FK_95D612FFE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id)'
        );
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('DROP TABLE exercise_tag');
    }
}
