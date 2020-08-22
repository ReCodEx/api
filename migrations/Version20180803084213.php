<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180803084213 extends AbstractMigration
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

        $this->addSql(
            'CREATE TABLE shadow_assignment_points (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', shadow_assignment_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', awardee_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', points INT NOT NULL, note LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', awarded_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', INDEX IDX_AA4F8AFA806900AC (shadow_assignment_id), INDEX IDX_AA4F8AFAF675F31B (author_id), INDEX IDX_AA4F8AFA79946661 (awardee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'ALTER TABLE shadow_assignment_points ADD CONSTRAINT FK_AA4F8AFA806900AC FOREIGN KEY (shadow_assignment_id) REFERENCES shadow_assignment (id)'
        );
        $this->addSql(
            'ALTER TABLE shadow_assignment_points ADD CONSTRAINT FK_AA4F8AFAF675F31B FOREIGN KEY (author_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE shadow_assignment_points ADD CONSTRAINT FK_AA4F8AFA79946661 FOREIGN KEY (awardee_id) REFERENCES user (id)'
        );
        $this->addSql('DROP TABLE shadow_assignment_evaluation');
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

        $this->addSql(
            'CREATE TABLE shadow_assignment_evaluation (id CHAR(36) NOT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:guid)\', shadow_assignment_id CHAR(36) DEFAULT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:guid)\', author_id CHAR(36) DEFAULT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:guid)\', evaluatee_id CHAR(36) DEFAULT NULL COLLATE utf8_unicode_ci COMMENT \'(DC2Type:guid)\', points INT NOT NULL, note LONGTEXT NOT NULL COLLATE utf8_unicode_ci, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', evaluated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', INDEX IDX_DA09F1BB806900AC (shadow_assignment_id), INDEX IDX_DA09F1BBF675F31B (author_id), INDEX IDX_DA09F1BBC4292C65 (evaluatee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'ALTER TABLE shadow_assignment_evaluation ADD CONSTRAINT FK_DA09F1BB806900AC FOREIGN KEY (shadow_assignment_id) REFERENCES shadow_assignment (id)'
        );
        $this->addSql(
            'ALTER TABLE shadow_assignment_evaluation ADD CONSTRAINT FK_DA09F1BBC4292C65 FOREIGN KEY (evaluatee_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE shadow_assignment_evaluation ADD CONSTRAINT FK_DA09F1BBF675F31B FOREIGN KEY (author_id) REFERENCES user (id)'
        );
        $this->addSql('DROP TABLE shadow_assignment_points');
    }
}
