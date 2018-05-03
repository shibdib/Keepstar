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

$restcord = new DiscordClient([
	'token' => $config['discord']['botToken']
]);

// Loading our own libs
foreach(glob(BASEDIR . '/libraries/*.php') as $lib) {
	require_once $lib;
}

// Start Auth
$log->notice('AUTHCHECK INITIATED');

// Make sure bots nick is set
if(isset($config['discord']['botNick'])) {
	/**
	 * Since the restcord library changes this all the damn time,
	 * we have to add a workaround ...
	 */
	try {
		$restcord->guild->modifyCurrentUserNick([
			'guild.id' => (int) $config['discord']['guildId'],
			'nick' => $config['discord']['botNick']
		]);
	} catch(Exception $e) {
		$restcord->guild->modifyCurrentUsersNick([
			'guild.id' => (int) $config['discord']['guildId'],
			'nick' => $config['discord']['botNick']
		]);
	}
}

// Ensure DB Is Created
createAuthDb();

// get authed users
$users = getUsers();

// get TQ server status
$status = serverStatus();

// Downtime probably ...
if(!$status || $status['players'] === null || (int) $status['players'] < 100) {
	die();
}

if((int) $config['discord']['logChannel'] !== 0) {
	$restcord->channel->createMessage([
		'channel.id' => (int) $config['discord']['logChannel'],
		'content' => 'Starting cron job to check member access and roles ...'
	]);
}

// get discord members
$members = $restcord->guild->listGuildMembers([
	'guild.id' => $config['discord']['guildId'],
	'limit' => 1000
]);

// get discord roles
$roles = $restcord->guild->getGuildRoles([
	'guild.id' => $config['discord']['guildId']
]);

// get discord server informations
$currentGuild = $restcord->guild->getGuild([
	'guild.id' => (int) $config['discord']['guildId']
]);

/**
 * Walk through the users that are registered in the auth database
 * 5 at a time, than wait 10 seconds to not run into a rate limit
 */
foreach(array_chunk($users, 5, true) as $userSet) {
	foreach($userSet as $user) {
		$characterID = $user['characterID'];
		$characterData = characterDetails($characterID);

		$discordID = (int) $user['discordID']; // this has to be casted to int

		$type = json_decode($user['groups'], true);
		$id = $user['id'];

		$corporationID = $characterData['corporation_id'];
		$corporationData = corporationDetails($corporationID);

		if(!isset($characterData['alliance_id'])) {
			$allianceID = 1;
			$allianceTicker = null;
		} else {
			$allianceID = $characterData['alliance_id'];
			$allianceData = allianceDetails($allianceID);
			$allianceTicker = $allianceData['ticker'];
		}

		$eveName = $characterData['name'];
		$exists = false;

		foreach($members as $member) {
			if($member->user->id === $discordID) {
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
		// To keep compatible with older config files
		if(!isset($config['discord']['addCorpTicker'])) {
			$config['discord']['addCorpTicker'] = $config['discord']['addTicker'];
		}

		if(($config['discord']['enforceInGameName'] || $config['discord']['addCorpTicker']) && (int) $currentGuild->owner_id !== (int) $discordID) {
			if($config['discord']['enforceInGameName'] && $config['discord']['addCorpTicker']) {
				if(!empty($corporationData['ticker'])) {
					$newNick = '[' . $corporationData['ticker'] . '] ' . $eveName;

					if(isset($config['discord']['addAllianceTicker']) && $config['discord']['addAllianceTicker'] === true && !is_null($allianceTicker)) {
						$newNick = $allianceTicker . ' [' . $corporationData['ticker'] . '] ' . $eveName;
					}

					if (strlen($newNick) >= 32) {
					    $newNick = mb_strimwidth($newNick, 0, 32);
                    }

					$restcord->guild->modifyGuildMember([
						'guild.id' => (int) $config['discord']['guildId'],
						'user.id' => (int) $discordID,
						'nick' => $newNick
					]);
				}
			} else if(!$config['discord']['enforceInGameName'] && $config['discord']['addCorpTicker']) {
				$memberDetails = $restcord->guild->getGuildMember([
					'guild.id' => (int) $config['discord']['guildId'],
					'user.id' => (int) $discordId
				]);

				if($memberDetails->nick) {
					$searchstring = '[' . $corporationData['ticker'] . ']';

					if(isset($config['discord']['addAllianceTicker']) && $config['discord']['addAllianceTicker'] === true && !is_null($allianceTicker)) {
						$searchstring = $allianceTicker . ' [' . $corporationData['ticker'] . ']';
					}

					$discordNick = str_replace($searchstring, '', $memberDetails->nick);
					$cleanNick = trim($discordNick);
					$newNick = '[' . $corporationData['ticker'] . '] ' . $cleanNick;
				} else {
					$newNick = '[' . $corporationData['ticker'] . '] ' . trim($memberDetails->user->username);
				}

                if (strlen($newNick) >= 32) {
                    $newNick = mb_strimwidth($newNick, 0, 32);
                }

				$restcord->guild->modifyGuildMember([
					'guild.id' => (int) $config['discord']['guildId'],
					'user.id' => (int) $discordId,
					'nick' => $newNick
				]);
			} else {
                if (strlen($eveName) >= 32) {
                    $eveName = mb_strimwidth($eveName, 0, 32);
                }
				$restcord->guild->modifyGuildMember([
					'guild.id' => (int) $config['discord']['guildId'],
					'user.id' => (int) $discordID,
					'nick' => $eveName
				]);
			}
		}

		/**
		 * Modify user roles
		 *
		 * @todo Check if a user already has a role
		 */
		$access = [];
		foreach($config['groups'] as $authGroup) {
			if(is_array($authGroup['id'])) {
				$id = $authGroup['id'];
			} else {
				$id = [];
				$id[] = $authGroup['id'];
			}

			$role = null;

			// General "Authenticated" Role
			if(in_array('1234', $id)) {
				foreach($roles as $role) {
					if($role->name == $authGroup['role']) {
						break;
					}
				}

				$restcord->guild->addGuildMemberRole([
					'guild.id' => (int) $config['discord']['guildId'],
					'user.id' => (int) $discordID,
					'role.id' => (int) $role->id
				]);

				$access[] = 'character';

				continue;
			}

			// Authentication by characterID
			if(in_array($characterID, $id)) {
				foreach($roles as $role) {
					if($role->name == $authGroup['role']) {
						break;
					}
				}

				$restcord->guild->addGuildMemberRole([
					'guild.id' => (int) $config['discord']['guildId'],
					'user.id' => (int) $discordID,
					'role.id' => (int) $role->id
				]);

				$access[] = 'character';

				continue;
			}

			// Autnetification by allianceID
			if(in_array($allianceID, $id)) {
				foreach($roles as $role) {
					if($role->name == $authGroup['role']) {
						break;
					}
				}

				$restcord->guild->addGuildMemberRole([
					'guild.id' => (int) $config['discord']['guildId'],
					'user.id' => (int) $discordID,
					'role.id' => (int) $role->id
				]);

				$access[] = 'alliance';

				continue;
			}

			// Authentification by corporationID
			if(in_array($corporationID, $id)) {
				foreach($roles as $role) {
					if($role->name == $authGroup['role']) {
						break;
					}
				}

				$restcord->guild->addGuildMemberRole([
					'guild.id' => (int) $config['discord']['guildId'],
					'user.id' => (int) $discordID,
					'role.id' => (int) $role->id
				]);

				$access[] = 'corp';

				continue;
			}

			// This rate limit is annoying -.-
			usleep(1000000);
		}

		// Make the json access list
		$accessList = json_encode($access);

		// Insert it all into the db
		insertUser($characterID, (int) $discordID, $accessList);

		/**
		 * Removing roles in case
		 */
		try {
			$removeTheseRoles = [];
			$removeTheseRolesName = [];

			foreach($config['groups'] as $authGroup) {
				if(is_array($authGroup['id'])) {
					$id = $authGroup['id'];
				} else {
					$id = [];
					$id[] = $authGroup['id'];
				}

				foreach($roles as $role) {
					if($role->name === $authGroup['role']) {
						if(((isset($characterData['corporation_id']) && !in_array($characterData['corporation_id'], $id)) && (isset($characterData['alliance_id']) && !in_array($characterData['alliance_id'], $id)) && !in_array($characterID, $id) && !in_array('1234', $id)) && in_array($role->id, $member->roles)) {
							$removeTheseRoles[] = (int) $role->id;

							if((int) $config['discord']['logChannel'] !== 0) {
								$removeTheseRolesName[] = $role->name;
							}

							$log->notice($eveName  . ' has been removed from the role ' . $role->name);

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
						'user.id' => (int) $discordID,
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
					'content' => $eveName . ' has been removed from the following roles: ' . $removedRoles
				]);
			}

			if(!isset($config['discord']['removeUser'])) {
				$config['discord']['removeUser'] = false;
			}

			if($config['discord']['removeUser'] === true) {
				$restcord->guild->removeGuildMember([
					'guild.id' => (int) $config['discord']['guildId'],
					'user.id' => (int) $discordID
				]);
			}

            if(isset($config['discord']['removedRole']) && $config['discord']['removedRole'] !== false) {
                foreach ($roles as $role) {
                    if ($role->name == $config['discord']['removedRole']) {
                        break;
                    }
                }
                $restcord->guild->addGuildMemberRole([
                    'guild.id' => (int)$config['discord']['guildId'],
                    'user.id' => (int)$_SESSION['user_id'],
                    'role.id' => (int)$role->id
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
if((int) $config['discord']['logChannel'] !== 0) {
	$restcord->channel->createMessage([
		'channel.id' => (int) $config['discord']['logChannel'],
		'content' => 'Finished cron job'
	]);
}

$log->notice('AUTHCHECK FINISHED');
