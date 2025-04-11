<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20250411155056 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assignment ADD plagiarism_batch_id CHAR(36) DEFAULT NULL COMMENT \'(DC2Type:uuid)\'');
        $this->addSql('ALTER TABLE assignment ADD CONSTRAINT FK_30C544BA5B4CC7BF FOREIGN KEY (plagiarism_batch_id) REFERENCES plagiarism_detection_batch (id)');
        $this->addSql('CREATE INDEX IDX_30C544BA5B4CC7BF ON assignment (plagiarism_batch_id)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE assignment DROP FOREIGN KEY FK_30C544BA5B4CC7BF');
        $this->addSql('DROP INDEX IDX_30C544BA5B4CC7BF ON assignment');
        $this->addSql('ALTER TABLE assignment DROP plagiarism_batch_id');
    }
}
