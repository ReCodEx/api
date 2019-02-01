<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20180503102139 extends AbstractMigration
{
    /**
     * @param Schema $schema
     */
    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('CREATE TABLE localized_assignment (id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', created_from_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:guid)\', student_hint VARCHAR(255) NOT NULL, created_at DATETIME NOT NULL COMMENT \'(DC2Type:datetime)\', locale VARCHAR(255) NOT NULL, INDEX IDX_B00C8DFF3EA4CB4D (created_from_id), PRIMARY KEY(id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('CREATE TABLE assignment_localized_assignment (assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', localized_assignment_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\', INDEX IDX_C307B7F3D19302F8 (assignment_id), INDEX IDX_C307B7F35F425B40 (localized_assignment_id), PRIMARY KEY(assignment_id, localized_assignment_id)) DEFAULT CHARACTER SET utf8 COLLATE utf8_unicode_ci ENGINE = InnoDB');
        $this->addSql('ALTER TABLE localized_assignment ADD CONSTRAINT FK_B00C8DFF3EA4CB4D FOREIGN KEY (created_from_id) REFERENCES localized_assignment (id) ON DELETE SET NULL');
        $this->addSql('ALTER TABLE assignment_localized_assignment ADD CONSTRAINT FK_C307B7F3D19302F8 FOREIGN KEY (assignment_id) REFERENCES assignment (id) ON DELETE CASCADE');
        $this->addSql('ALTER TABLE assignment_localized_assignment ADD CONSTRAINT FK_C307B7F35F425B40 FOREIGN KEY (localized_assignment_id) REFERENCES localized_assignment (id) ON DELETE CASCADE');
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE localized_assignment DROP FOREIGN KEY FK_B00C8DFF3EA4CB4D');
        $this->addSql('ALTER TABLE assignment_localized_assignment DROP FOREIGN KEY FK_C307B7F35F425B40');
        $this->addSql('DROP TABLE localized_assignment');
        $this->addSql('DROP TABLE assignment_localized_assignment');
    }
}
