<?php

declare(strict_types=1);

namespace Migrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;
use Faker\Provider\Uuid;

/**
 * Auto-generated Migration: Please modify to your needs!
 */
final class Version20211015163456 extends AbstractMigration
{
    private static $transforms = [
        'dark_theme' => 'darkTheme',
        'vim_mode' => 'vimMode',
        'opened_sidebar' => 'openedSidebar',
        'use_gravatar' => 'useGravatar',
        'default_page' => 'defaultPage'
    ];

    private static $defaults = [
        'dark_theme' => true,
        'vim_mode' => false,
        'opened_sidebar' => true,
        'use_gravatar' => true,
        'default_page' => null,
    ];

    public function getDescription(): string
    {
        return '';
    }

    public function preUp(Schema $schema): void
    {

        $cols = join(', ', array_map(function ($c) {
            return "us.$c";
        }, array_keys(self::$transforms)));

        $settings = $this->connection->fetchAllAssociative("SELECT usr.id AS user_id, uud.id AS ui_id, $cols
            FROM user_settings AS us JOIN `user` AS usr ON us.id = usr.settings_id LEFT
            JOIN user_ui_data AS uud ON uud.id = usr.ui_data_id");
        $uiData = $this->connection->fetchAllKeyValue('SELECT id, `data` FROM user_ui_data');

        foreach ($settings as $s) {
            $userId = $s['user_id'];
            $uiId = $s['ui_id'];

            $json = $uiId && array_key_exists($uiId, $uiData) ? @json_decode($uiData[$uiId], true) : [];
            if (!$json) {
                $json = [];
            }
            foreach (self::$transforms as $col => $uiProp) {
                $json[$uiProp] = $s[$col];
            }

            if ($uiId && array_key_exists($uiId, $uiData)) {
                // update
                $this->connection->executeStatement(
                    "UPDATE user_ui_data SET `data` = :newData WHERE id = :id",
                    [ 'id' => $uiId, 'newData' => json_encode($json) ]
                );
            } else {
                // insert new
                $uuid = Uuid::uuid();
                $this->connection->executeStatement(
                    "INSERT INTO user_ui_data (id, `data`) VALUES (:id, :newData)",
                    [ 'id' => $uuid, 'newData' => json_encode($json) ]
                );
                $this->connection->executeStatement(
                    "UPDATE `user` SET `ui_data_id` = :uuid WHERE id = :id",
                    [ 'id' => $userId,'uuid' => $uuid ]
                );
            }
        }
    }

    public function up(Schema $schema): void
    {
        // this up() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user_settings DROP dark_theme, DROP vim_mode, DROP opened_sidebar, DROP use_gravatar, DROP default_page');
    }

    public function down(Schema $schema): void
    {
        // this down() migration is auto-generated, please modify it to your needs
        $this->abortIf($this->connection->getDatabasePlatform()->getName() !== 'mysql', 'Migration can only be executed safely on \'mysql\'.');

        $this->addSql('ALTER TABLE user_settings ADD dark_theme TINYINT(1) NOT NULL, ADD vim_mode TINYINT(1) NOT NULL, ADD opened_sidebar TINYINT(1) NOT NULL, ADD use_gravatar TINYINT(1) NOT NULL, ADD default_page VARCHAR(255) CHARACTER SET utf8mb4 DEFAULT NULL COLLATE `utf8mb4_unicode_ci`');
    }

    public function postDown(Schema $schema): void
    {
        $uiData = $this->connection->fetchAllAssociative('SELECT uud.id AS ui_id, us.id AS settings_id, uud.data AS json_data
            FROM user_ui_data AS uud
            JOIN `user` AS usr ON uud.id = usr.ui_data_id
            JOIN user_settings AS us ON us.id = usr.settings_id');

        // cols to be set in update of settings
        $sets = join(', ', array_map(function ($c) {
            return "$c = :$c";
        }, array_keys(self::$transforms)));

        foreach ($uiData as $s) {
            $uiId = $s['ui_id'];
            $sId = $s['settings_id'];
            $json = @json_decode($s['json_data'], true);
            if (!$json) {
                $json = [];
            }

            $updateData = [ 'id' => $sId ];
            foreach (self::$transforms as $col => $uiProp) {
                $updateData[$col] = $json[$uiProp] ?? self::$defaults[$col];
                unset($json[$uiProp]);
            }
            $this->connection->executeStatement("UPDATE user_settings SET $sets WHERE id = :id", $updateData);
            $this->connection->executeStatement(
                "UPDATE user_ui_data SET `data` = :newData WHERE id = :id",
                [ 'id' => $uiId, 'newData' => json_encode($json) ]
            );
        }
    }
}
