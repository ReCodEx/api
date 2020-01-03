<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180730212119 extends AbstractMigration
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
            'CREATE TABLE localized_shadow_assignment (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', name VARCHAR(255) NOT NULL, assignment_text LONGTEXT NOT NULL, external_assignment_link LONGTEXT DEFAULT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', locale VARCHAR(255) NOT NULL, INDEX IDX_B2F7A3B63EA4CB4D (created_from_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE shadow_assignment (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', group_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', is_public TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', is_bonus TINYINT(1) NOT NULL COMMENT \'(DC2Type:boolean)\', created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', max_points INT NOT NULL, updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', deleted_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime)\', version INT NOT NULL, INDEX IDX_9B7F8A85FE54D947 (group_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE shadow_assignment_localized_shadow_assignment (shadow_assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', localized_shadow_assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_8B27D504806900AC (shadow_assignment_id), INDEX IDX_8B27D504161CFB5 (localized_shadow_assignment_id), PRIMARY KEY(shadow_assignment_id, localized_shadow_assignment_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'CREATE TABLE shadow_assignment_evaluation (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', shadow_assignment_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', author_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', evaluatee_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', points INT NOT NULL, note LONGTEXT NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', evaluated_at DATETIME DEFAULT NULL COMMENT \'(DC2Type:datetime)\', updated_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', INDEX IDX_DA09F1BB806900AC (shadow_assignment_id), INDEX IDX_DA09F1BBF675F31B (author_id), INDEX IDX_DA09F1BBC4292C65 (evaluatee_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB'
        );
        $this->addSql(
            'ALTER TABLE localized_shadow_assignment ADD CONSTRAINT FK_B2F7A3B63EA4CB4D FOREIGN KEY (created_from_id) REFERENCES localized_shadow_assignment (id) ON DELETE SET NULL'
        );
        $this->addSql(
            'ALTER TABLE shadow_assignment ADD CONSTRAINT FK_9B7F8A85FE54D947 FOREIGN KEY (group_id) REFERENCES `group` (id)'
        );
        $this->addSql(
            'ALTER TABLE shadow_assignment_localized_shadow_assignment ADD CONSTRAINT FK_8B27D504806900AC FOREIGN KEY (shadow_assignment_id) REFERENCES shadow_assignment (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE shadow_assignment_localized_shadow_assignment ADD CONSTRAINT FK_8B27D504161CFB5 FOREIGN KEY (localized_shadow_assignment_id) REFERENCES localized_shadow_assignment (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE shadow_assignment_evaluation ADD CONSTRAINT FK_DA09F1BB806900AC FOREIGN KEY (shadow_assignment_id) REFERENCES shadow_assignment (id)'
        );
        $this->addSql(
            'ALTER TABLE shadow_assignment_evaluation ADD CONSTRAINT FK_DA09F1BBF675F31B FOREIGN KEY (author_id) REFERENCES user (id)'
        );
        $this->addSql(
            'ALTER TABLE shadow_assignment_evaluation ADD CONSTRAINT FK_DA09F1BBC4292C65 FOREIGN KEY (evaluatee_id) REFERENCES user (id)'
        );
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

        $this->addSql('ALTER TABLE localized_shadow_assignment DROP FOREIGN KEY FK_B2F7A3B63EA4CB4D');
        $this->addSql('ALTER TABLE shadow_assignment_localized_shadow_assignment DROP FOREIGN KEY FK_8B27D504161CFB5');
        $this->addSql('ALTER TABLE shadow_assignment_localized_shadow_assignment DROP FOREIGN KEY FK_8B27D504806900AC');
        $this->addSql('ALTER TABLE shadow_assignment_evaluation DROP FOREIGN KEY FK_DA09F1BB806900AC');
        $this->addSql('DROP TABLE localized_shadow_assignment');
        $this->addSql('DROP TABLE shadow_assignment');
        $this->addSql('DROP TABLE shadow_assignment_localized_shadow_assignment');
        $this->addSql('DROP TABLE shadow_assignment_evaluation');
    }
}
