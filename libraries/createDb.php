<?php


function createAuthDb()
{
    $tables = array('authed', 'keepstar');

    $createCode = array(
        'authed' => '
            BEGIN;
            CREATE TABLE IF NOT EXISTS `authed` (
	            `id`	INTEGER PRIMARY KEY AUTOINCREMENT,
	            `characterID`	INTEGER NOT NULL UNIQUE,
	            `discordID`	INTEGER NOT NULL UNIQUE,
	            `groups`	TEXT NOT NULL
            );
            COMMIT;',
        'keepstar' => '
            BEGIN;
            CREATE TABLE IF NOT EXISTS `keepstar` (
	            `id`	INTEGER PRIMARY KEY AUTOINCREMENT,
	            `variable`	TEXT NOT NULL UNIQUE,
	            `value`	TEXT NOT NULL
            );
            COMMIT;');

    // Does the file exist?
    if (!file_exists(__DIR__ . '/database/auth.sqlite')) {
        touch(__DIR__ . '/database/auth.sqlite');
    }

    // Create table if not exists
    foreach ($tables as $table) {
        $exists = dbQueryField("SELECT name FROM sqlite_master WHERE type = 'table' AND name = :name", 'name', array(':name' => $table));
        if (!$exists) {
            dbExecute(trim($createCode[$table]));
        }
    }
}