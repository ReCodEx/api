<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20190504100254 extends AbstractMigration
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
            'CREATE INDEX assignment_solution_submission_submitted_at_idx ON assignment_solution_submission (submitted_at)'
        );
        $this->addSql(
            'CREATE INDEX ref_solution_submission_submitted_at_idx ON reference_solution_submission (submitted_at)'
        );
        $this->addSql('CREATE INDEX solution_created_at_idx ON solution (created_at)');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf(
            $this->connection->getDatabasePlatform()->getName() !== 'mysql',
            'Migration can only be executed safely on \'mysql\'.'
        );

        $this->addSql('DROP INDEX assignment_solution_submission_submitted_at_idx ON assignment_solution_submission');
        $this->addSql('DROP INDEX ref_solution_submission_submitted_at_idx ON reference_solution_submission');
        $this->addSql('DROP INDEX solution_created_at_idx ON solution');
    }
}
