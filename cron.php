<?php

define('BASEDIR', __DIR__);
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once BASEDIR . '/config/config.php';
require_once BASEDIR . '/vendor/autoload.php';

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use RestCord\DiscordClient;

$log = new Logger('DScan');
$log->pushHandler(new RotatingFileHandler(__DIR__ . '/log/KeepstarCron.log', Logger::NOTICE));

$restcord = new DiscordClient(['token' => $config['discord']['botToken']]);

foreach(glob(BASEDIR . '/libraries/*.php') as $lib) {
	require_once $lib;
}

//Start Auth
$log->notice('AUTHCHECK INITIATED');

//Make sure bots nick is set
if (isset($config['discord']['botNick'])) {
    $restcord->guild->modifyCurrentUsersNick(['guild.id' => (int)$config['discord']['guildId'], 'nick' => $config['discord']['botNick']]);
}

//Ensure DB Is Created
createAuthDb();

// get authed users
$users = getUsers();

// get TQ server status
$status = serverStatus();

// Downtime probably ...
if(!$status || $status['players'] === null || (int) $status['players'] < 100) {
	die();
}

// get discord members
$members = $restcord->guild->listGuildMembers(['guild.id' => $config['discord']['guildId'], 'limit' => 1000]);

// get discord roles
$roles = $restcord->guild->getGuildRoles(['guild.id' => $config['discord']['guildId']]);

// get discord server informations
$currentGuild = $restcord->guild->getGuild(['guild.id' => (int) $config['discord']['guildId']]);

/**
 * Walk through the users that are registered in the auth database
 * 5 at a time, than wait 10 seconds to not run into a rate limit
 */
foreach(array_chunk($users, 5, true) as $userSet) {
	foreach($userSet as $user) {
		$characterId = $user['characterID'];
		$discordId = (int) $user['discordID']; // this has to be casted to int
		$type = json_decode($user['groups'], true);
		$id = $user['id'];
		$characterData = characterDetails($characterId);
		$corporationData = corporationDetails($characterData['corporation_id']);
		$eveName = $characterData['name'];
		$exists = false;

		foreach($members as $member) {
			if($member->user->id === $discordId) {
				$exists = true;

				break;
			}
		}

		// Additional ESI Check
		if(!(int) $characterData['corporation_id'] || (int) $characterData['corporation_id'] === null) {
			continue;
		}

		if($exists === false) {
			$log->notice("$eveName has been removed from the database as they are no longer a member of the server.");

			deleteUser($id);

			continue;
		}

		/**
		 * Set EVE Name and Corp Ticker
		 * Server owner will not be touched
		 */
		if(($config['discord']['enforceInGameName'] || $config['discord']['addTicker']) && (int) $currentGuild->owner_id !== (int) $discordId) {
			if($config['discord']['enforceInGameName'] && $config['discord']['addTicker']) {
				$newNick = '[' . $corporationData['ticker'] . '] ' . $eveName;

				$restcord->guild->modifyGuildMember([
					'guild.id' => (int) $config['discord']['guildId'],
					'user.id' => (int) $discordId,
					'nick' => $newNick
				]);
			} else if(!$config['discord']['enforceInGameName'] && $config['discord']['addTicker']) {
				$memberDetails = $restcord->guild->getGuildMember([
					'guild.id' => (int) $config['discord']['guildId'],
					'user.id' => (int) $discordId
				]);

				if($memberDetails->nick) {
					$cleanNick = str_replace('[' . $corporationData['ticker'] . ']', '', $memberDetails->nick);
					$newNick = '[' . $corporationData['ticker'] . ']' . $cleanNick;
				} else {
					$newNick = '[' . $corporationData['ticker'] . ']' . $memberDetails->user->username;
				}

				$restcord->guild->modifyGuildMember([
					'guild.id' => (int) $config['discord']['guildId'],
					'user.id' => (int) $discordId,
					'nick' => $newNick
				]);
			} else {
				$restcord->guild->modifyGuildMember([
					'guild.id' => (int) $config['discord']['guildId'],
					'user.id' => (int) $discordId,
					'nick' => $eveName
				]);
			}
		}

		/**
		 * Removing roles
		 */
		try {
			$removeTheseRoles = [];
			$removeTheseRolesName = [];

			foreach($config['groups'] as $authGroup) {
				$id = $authGroup['id'];

				foreach($roles as $role) {
					if($role->name === $authGroup['role']) {
						if(((int) $id !== (int) $characterData['corporation_id'] && (int) $id !== (int) $characterData['alliance_id'] && (int) $id !== (int) $characterId && (int) $id !== 1234) && in_array($role->id, $member->roles)) {
							$removeTheseRoles[] = (int) $role->id;

							if((int) $config['discord']['logChannel'] !== 0) {
								$removeTheseRolesName[] = $role->name;
							}

							$log->notice("$eveName has been removed from the role $role->name");

							continue;
						}

						if(in_array($role->id, $removeTheseRoles, true)) {
							unset($removeTheseRoles[array_search($role->id, $removeTheseRoles, true)]);
						}
					}
				}
			}
		} catch(Exception $e) {
			// Check if we're being rate limited
			if(strpos($error, 'rate limited') !== false) {
				break;
			}

			$log->error('ERROR: ' . $error);
		}

		if(count($removeTheseRoles) > 0) {
			foreach($removeTheseRoles as $removeRole) {
				try {
					$restcord->guild->removeGuildMemberRole([
						'guild.id' => (int) $config['discord']['guildId'],
						'user.id' => (int) $discordId,
						'role.id' => (int) $removeRole
					]);
				} catch(Exception $e) {
					$error = $e->getMessage();
					// Check if error is user left server and if so remove them
					if(strpos($error, '10007') !== false) {
						deleteUser($id);

						continue 2;
					}

					// Check if we're being rate limited
					if(strpos($error, 'rate limited') !== false) {
						break 2;
					}

					$log->error('ERROR: ' . $error);
				}
			}

			if((int) $config['discord']['logChannel'] !== 0) {
				$removedRoles = implode(', ', $removeTheseRolesName);
				$restcord->channel->createMessage([
					'channel.id' => (int) $config['discord']['logChannel'],
					'content' => "$eveName has been removed from the following roles $removedRoles"
				]);
			}

			if(!isset($config['discord']['removeUser'])) {
				$config['discord']['removeUser'] = false;
			}

			if($config['discord']['removeUser'] === true) {
				$restcord->guild->removeGuildMember([
					'guild.id' => (int) $config['discord']['guildId'],
					'user.id' => (int) $discordId
				]);
			}
		}

		if(count($type) === 0) {
			$log->notice("2 $type");

			deleteUser($id);
		}
	} // END DB User Check

	// Don't run into a rate limit, just wait 10 seconds
	usleep(10000000);
} // END Auth DB User Check

/**
 * @todo Check for users on the server that are NOT ni the auth DB
 * and remove all roles they might have. Just to be absolutely sure.
 * Don't touch the bot here!
 */
