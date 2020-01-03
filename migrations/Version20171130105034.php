<?php

namespace Migrations;

use Doctrine\Migrations\AbstractMigration;
use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
class Version20171130105034 extends AbstractMigration
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

        $this->addSql("RENAME TABLE exercise_additional_exercise_file TO exercise_attachment_file");

        $this->addSql('ALTER TABLE exercise_attachment_file DROP FOREIGN KEY FK_C0EAC68CE934951A');
        $this->addSql('ALTER TABLE exercise_attachment_file DROP FOREIGN KEY FK_C0EAC68CED6C0B59');
        $this->addSql('DROP INDEX idx_c0eac68ce934951a ON exercise_attachment_file');
        $this->addSql('DROP INDEX idx_c0eac68ced6c0b59 ON exercise_attachment_file');

        $this->addSql(
            'ALTER TABLE exercise_attachment_file CHANGE additional_exercise_file_id attachment_file_id CHAR(36) NOT NULL COMMENT \'(DC2Type:guid)\''
        );

        $this->addSql('CREATE INDEX IDX_24161E21E934951A ON exercise_attachment_file (exercise_id)');
        $this->addSql('CREATE INDEX IDX_24161E215B5E2CEA ON exercise_attachment_file (attachment_file_id)');
        $this->addSql(
            'ALTER TABLE exercise_attachment_file ADD CONSTRAINT FK_C0EAC68CE934951A FOREIGN KEY (exercise_id) REFERENCES exercise (id) ON DELETE CASCADE'
        );
        $this->addSql(
            'ALTER TABLE exercise_attachment_file ADD CONSTRAINT FK_C0EAC68CED6C0B59 FOREIGN KEY (attachment_file_id) REFERENCES uploaded_file (id) ON DELETE CASCADE'
        );
    }

    public function postUp(Schema $schema): void
    {
        $this->connection->beginTransaction();
        $files = $this->connection->executeQuery(
            "SELECT * FROM uploaded_file WHERE discriminator = 'additionalexercisefile'"
        );
        foreach ($files as $file) {
            $this->connection->executeQuery(
                "UPDATE uploaded_file SET discriminator = 'attachmentfile' WHERE id = :id",
                ["id" => $file["id"]]
            );
        }
        $this->connection->commit();
    }

    /**
     * @param Schema $schema
     */
    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException();
    }
}
