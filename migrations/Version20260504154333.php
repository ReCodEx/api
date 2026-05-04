<?php

declare(strict_types=1);

namespace Migrations;

use App\Model\GroupExamLockType;
use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

/**
 * Changes group and exam group lock-strict to lock-type with values from GroupExamLockType enum.
 * The GroupExamLockType::Visible corresponds to the previous strict = false,
 * GroupExamLockType::Restricted corresponds to strict = true.
 */
final class Version20260504154333 extends AbstractMigration
{
    public function getDescription(): string
    {
        return '';
    }

    private $groupStrict = [];
    private $examStrict = [];
    private $userStrict = [];

    public function preUp(Schema $schema): void
    {
        $result = $this->connection->fetchAllAssociative("SELECT id, exam_lock_strict FROM `group`");
        foreach ($result as $row) {
            $this->groupStrict[$row['id']] = $row['exam_lock_strict'];
        }

        $result = $this->connection->fetchAllAssociative("SELECT id, lock_strict FROM `group_exam`");
        foreach ($result as $row) {
            $this->examStrict[$row['id']] = $row['lock_strict'];
        }

        $result = $this->connection->fetchAllAssociative("SELECT id, group_lock_strict FROM `user`");
        foreach ($result as $row) {
            $this->userStrict[$row['id']] = $row['group_lock_strict'];
        }
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `group` ADD exam_lock_type VARCHAR(255) NOT NULL, DROP exam_lock_strict');
        $this->addSql('ALTER TABLE `group_exam` ADD lock_type VARCHAR(255) NOT NULL, DROP lock_strict');
        $this->addSql('ALTER TABLE `user` ADD group_lock_type VARCHAR(255) NOT NULL, DROP group_lock_strict');
    }

    public function postUp(Schema $schema): void
    {
        foreach ($this->groupStrict as $id => $strict) {
            $type = $strict ? GroupExamLockType::Restricted->value : GroupExamLockType::Visible->value;
            $this->connection->executeQuery("UPDATE `group` SET exam_lock_type = '$type' WHERE id = '$id'");
        }

        foreach ($this->examStrict as $id => $strict) {
            $type = $strict ? GroupExamLockType::Restricted->value : GroupExamLockType::Visible->value;
            $this->connection->executeQuery("UPDATE `group_exam` SET lock_type = '$type' WHERE id = '$id'");
        }

        foreach ($this->userStrict as $id => $strict) {
            $type = $strict ? GroupExamLockType::Restricted->value : GroupExamLockType::Visible->value;
            $this->connection->executeQuery("UPDATE `user` SET group_lock_type = '$type' WHERE id = '$id'");
        }
    }

    public function preDown(Schema $schema): void
    {
        $result = $this->connection->fetchAllAssociative("SELECT id, exam_lock_type FROM `group`");
        foreach ($result as $row) {
            $this->groupStrict[$row['id']] = $row['exam_lock_type'] === GroupExamLockType::Restricted->value ? 1 : 0;
        }

        $result = $this->connection->fetchAllAssociative("SELECT id, lock_type FROM `group_exam`");
        foreach ($result as $row) {
            $this->examStrict[$row['id']] = $row['lock_type'] === GroupExamLockType::Restricted->value ? 1 : 0;
        }

        $result = $this->connection->fetchAllAssociative("SELECT id, group_lock_type FROM `user`");
        foreach ($result as $row) {
            $this->userStrict[$row['id']] = $row['group_lock_type'] === GroupExamLockType::Restricted->value ? 1 : 0;
        }
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->addSql('ALTER TABLE `group` ADD exam_lock_strict TINYINT(1) NOT NULL, DROP exam_lock_type');
        $this->addSql('ALTER TABLE `group_exam` ADD lock_strict TINYINT(1) NOT NULL, DROP lock_type');
        $this->addSql('ALTER TABLE `user` ADD group_lock_strict TINYINT(1) NOT NULL, DROP group_lock_type');
    }

    public function postDown(Schema $schema): void
    {
        foreach ($this->groupStrict as $id => $strict) {
            $this->connection->executeQuery("UPDATE `group` SET exam_lock_strict = $strict WHERE id = '$id'");
        }

        foreach ($this->examStrict as $id => $strict) {
            $this->connection->executeQuery("UPDATE `group_exam` SET lock_strict = $strict WHERE id = '$id'");
        }

        foreach ($this->userStrict as $id => $strict) {
            $this->connection->executeQuery("UPDATE `user` SET group_lock_strict = $strict WHERE id = '$id'");
        }
    }
}
