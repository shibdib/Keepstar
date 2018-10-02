<?php

define('BASEDIR', __DIR__);

ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once BASEDIR . '/config/config.php';
require_once BASEDIR . '/vendor/autoload.php';

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use RestCord\DiscordClient;

// Setup Logger
$log = new Logger('DScan');
$log->pushHandler(new RotatingFileHandler(__DIR__ . '/log/Keepstar.log', Logger::NOTICE));

// Setup Slim
$app = new \Slim\Slim($config['slim']);
$app->add(new \Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware());
$app->view(new \Slim\Views\Twig());

// Load libraries
foreach (glob(BASEDIR . '/libraries/*.php') as $lib) {
    require_once $lib;
}

// Ensure DB Is Created
createAuthDb();

// Convert mysql if needed
if (isset($config['mysql']['password']) && !file_exists(__DIR__ . '/tools/.blocker')) {
    require_once BASEDIR . '/tools/mysqlConverter.php';
    convertMysql($config);
}

//A dd a check for old configs
if (!isset($config['firetail'])) {
    $config['firetail']['active'] = False;
}

// Routes
$app->get('/admin/', function () use ($app, $config) {
    if (!getKeepstar('botStarted')) {
        $app->render('admin.twig', [
            'botToken' => $config['discord']['botToken']
        ]);

        insertKeepstar('botStarted', 'True');
    } else {
        echo 'You have no reason to go here.';
    }
});

$app->get('/', function () use ($app, $config) {
    //Clear out session just incase
    $_SESSION = [];
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time() - 42000, '/');
    }
    if (!isset($config['auth']['title'])) {
        $config['auth']['title'] = 'Keepstar Auth';
    }
    $url = 'https://login.eveonline.com/oauth/authorize?response_type=code&redirect_uri=' . $config['sso']['callbackURL'] . '&client_id=' . $config['sso']['clientID'];
    $app->render('index.twig', [
        'config' => $config,
        'crestURL' => $url
    ]);
});

// Dashboard
$app->get('/auth/', function () use ($app, $config, $log) {
    if (isset($_GET['code']) && !isset($_SESSION['eveCode'])) {
        $_SESSION['eveCode'] = $_GET['code'];
        $url = $config['sso']['callbackURL'];
        echo "<head><meta http-equiv='refresh' content='0; url=$url' /></head>";
        return;
    } else if (isset($_GET['code']) && isset($_SESSION['eveCode'])) {
        $_SESSION['discordCode'] = $_GET['code'];
        echo "<head><meta http-equiv='refresh' content='0; url=/discord/' /></head>";
        return;
    }
    if (!isset($_SESSION['eveData'])) {
        $code = $_SESSION['eveCode'];
        $tokenURL = 'https://login.eveonline.com/oauth/token';
        $base64 = base64_encode($config['sso']['clientID'] . ':' . $config['sso']['secretKey']);
        $data = json_decode(sendData($tokenURL, [
            'grant_type' => 'authorization_code',
            'code' => $code
        ], [
            "Authorization: Basic {$base64}"
        ]));
        $accessToken = $data->access_token;
        // Verify Token
        $verifyURL = 'https://login.eveonline.com/oauth/verify';
        $_SESSION['eveData'] = json_decode(sendData($verifyURL, [], ["Authorization: Bearer {$accessToken}"]));
    }
    $characterID = $_SESSION['eveData']->CharacterID;
    $characterData = characterDetails($characterID);
    $corporationID = $characterData['corporation_id'];
    $corporationData = corporationDetails($corporationID);
    $corporationName = $corporationData['name'];
    $eveName = trim($characterData['name']);
    if (!isset($characterData['alliance_id'])) {
        $allianceID = 1;
    } else {
        $allianceID = $characterData['alliance_id'];
    }
    // Set some session helpers
    $_SESSION['characterID'] = $characterID;
    $_SESSION['characterName'] = $eveName;
    $_SESSION['corporationID'] = $corporationID;
    $_SESSION['allianceID'] = $allianceID;
    $imageURL = 'https://image.eveonline.com/Character/' . $characterID . '_256.jpg';
    // Check if user can ping
    $canPing = null;
    if (isset($config['pings']['enabled']) && $config['pings']['enabled'] === true) {
        $authInfo = getUserWithEve($characterID);
        if (isset($authInfo[0]['discordID'])) {
            $restcord = new DiscordClient([
                'token' => $config['discord']['botToken']
            ]);
            $memberInfo = $restcord->guild->getGuildMember([
                'guild.id' => (int)$config['discord']['guildId'],
                'user.id' => (int)$authInfo[0]['discordID']
            ]);
            $memberRoles = $memberInfo->roles;
            $roles = $restcord->guild->getGuildRoles([
                'guild.id' => $config['discord']['guildId']
            ]);
            foreach ($roles as $role) {
                if ($role->name == $config['pings']['pingRole']) {
                    if (in_array((int)$role->id, $memberRoles, true)) {
                        $canPing = true;
                        break;
                    }
                    break;
                }
            }
        }
    }
    $app->render('dashboard.twig', [
        'image' => $imageURL,
        'name' => $eveName,
        'corp' => $corporationName,
        'canPing' => $canPing,
        'config' => $config
    ]);
});

// Discord roles
$app->get('/discord/', function () use ($app, $config, $log) {
    if (!isset($_SESSION['discordCode'])) {
        // If we don't have a code yet, we need to make the link
        $scopes = 'identify%20guilds.join';
        $discordLink = url($config['discord']['clientId'], $config['discord']['redirectUri'], $scopes);
        $app->render('discord.twig', [
            'botToken' => $config['discord']['botToken'],
            'discordLink' => $discordLink
        ]);
    } else {
        $code = $_SESSION['discordCode'];
        init($code, $config['discord']['redirectUri'], $config['discord']['clientId'], $config['discord']['clientSecret']);
        get_user();
        $restcord = new DiscordClient([
            'token' => $config['discord']['botToken']
        ]);

        // Check if user is in the server and add them if not
        try {
            $restcord->guild->getGuildMember([
                'guild.id' => (int)$config['discord']['guildId'],
                'user.id' => (int)$_SESSION['user_id']
            ]);
        } catch (Exception $e) {
            try {
                $log->error((string)$e);
                $restcord->guild->addGuildMember([
                    'guild.id' => (int)$config['discord']['guildId'],
                    'user.id' => (int)$_SESSION['user_id'],
                    'access_token' => $_SESSION['auth_token']
                ]);
            } catch (Exception $e) {
                $log->error((string)$e);
                $app->render('notinserver.twig', [
                    'discordLink' => $config['discord']['inviteLink']
                ]);
                return;
            }
        }

        //Make sure bots nick is set
        if (isset($config['discord']['botNick'])) {
            /**
             * Since the restcord library changes this all the damn time,
             * we have to add a workaround ...
             */
            try {
                $restcord->guild->modifyCurrentUserNick([
                    'guild.id' => (int)$config['discord']['guildId'],
                    'nick' => $config['discord']['botNick']
                ]);
            } catch (Exception $e) {
                $restcord->guild->modifyCurrentUsersNick([
                    'guild.id' => (int)$config['discord']['guildId'],
                    'nick' => $config['discord']['botNick']
                ]);
            }
        }

        // Get some EVE info
        $characterID = $_SESSION['characterID'];
        $characterData = characterDetails($characterID);
        $corporationID = $characterData['corporation_id'];
        $corporationData = corporationDetails($corporationID);
        $eveName = $_SESSION['characterName'];
        if ($_SESSION['allianceID'] !== 1) {
            $allianceData = allianceDetails($_SESSION['allianceID']);
            $allianceTicker = $allianceData['ticker'];
        }

        // Now check if the person is in a corp or alliance on the blue / allowed list
        // Whatever ID matches whatever group, they get added to. Discord role ordering decides what they can and can't see
        $access = [];
        $roles = $restcord->guild->getGuildRoles([
            'guild.id' => $config['discord']['guildId']
        ]);
        $currentGuild = $restcord->guild->getGuild([
            'guild.id' => (int)$config['discord']['guildId']
        ]);
        // To keep compatible with older config files
        if (!isset($config['discord']['addCorpTicker'])) {
            $config['discord']['addCorpTicker'] = $config['discord']['addTicker'];
        }
        // Handle new nicknames
        if (($config['discord']['enforceInGameName'] || $config['discord']['addCorpTicker']) && (int)$currentGuild->owner_id !== (int)$_SESSION['user_id']) {
            if ($config['discord']['enforceInGameName'] && $config['discord']['addCorpTicker']) {
                $newNick = '[' . $corporationData['ticker'] . '] ' . $eveName;
                if (isset($config['discord']['addAllianceTicker']) && $config['discord']['addAllianceTicker'] === true && null !== $allianceTicker) {
                    $newNick = $allianceTicker . ' [' . $corporationData['ticker'] . '] ' . $eveName;
                }
                if (strlen($newNick) >= 32) {
                    $newNick = mb_strimwidth($newNick, 0, 32);
                }
                $restcord->guild->modifyGuildMember([
                    'guild.id' => (int)$config['discord']['guildId'],
                    'user.id' => (int)$_SESSION['user_id'],
                    'nick' => $newNick
                ]);
            } else if (!$config['discord']['enforceInGameName'] && $config['discord']['addCorpTicker']) {
                $memberDetails = $restcord->guild->getGuildMember([
                    'guild.id' => (int)$config['discord']['guildId'],
                    'user.id' => (int)$_SESSION['user_id']
                ]);
                if ($memberDetails->nick) {
                    $searchstring = '[' . $corporationData['ticker'] . ']';
                    if (isset($config['discord']['addAllianceTicker']) && $config['discord']['addAllianceTicker'] === true && null !== $allianceTicker) {
                        $searchstring = $allianceTicker . ' [' . $corporationData['ticker'] . ']';
                    }
                    $discordNick = str_replace($searchstring, '', $memberDetails->nick);
                    $cleanNick = trim($discordNick);
                    $newNick = '[' . $corporationData['ticker'] . '] ' . $cleanNick;
                } else {
                    $newNick = '[' . $corporationData['ticker'] . '] ' . trim($memberDetails->user->username);
                }
                $restcord->guild->modifyGuildMember([
                    'guild.id' => (int)$config['discord']['guildId'],
                    'user.id' => (int)$_SESSION['user_id'], 'nick' => $newNick
                ]);
            } else {
                if (strlen($eveName) >= 32) {
                    $eveName = mb_strimwidth($eveName, 0, 32);
                }
                $restcord->guild->modifyGuildMember([
                    'guild.id' => (int)$config['discord']['guildId'],
                    'user.id' => (int)$_SESSION['user_id'],
                    'nick' => $eveName
                ]);
            }
        }

        // Handle role assignment
        foreach ($config['groups'] as $authGroup) {
            if (is_array($authGroup['id'])) {
                $id = $authGroup['id'];
            } else {
                $id = [];
                $id[] = $authGroup['id'];
            }
            $role = null;
            // General "Authenticated" Role
            if (in_array('1234', $id)) {
                foreach ($roles as $role) {
                    if ($role->name == $authGroup['role']) {
                        break;
                    }
                }
                $restcord->guild->addGuildMemberRole([
                    'guild.id' => (int)$config['discord']['guildId'],
                    'user.id' => (int)$_SESSION['user_id'],
                    'role.id' => (int)$role->id
                ]);
                if ((int)$config['discord']['logChannel'] !== 0) {
                    $restcord->channel->createMessage([
                        'channel.id' => (int)$config['discord']['logChannel'],
                        'content' => $eveName . ' has been added to the role ' . $role->name
                    ]);
                }
                $access[] = 'character';
                continue;
            }

            // Authentication by characterID
            if (in_array($characterID, $id)) {
                foreach ($roles as $role) {
                    if ($role->name == $authGroup['role']) {
                        break;
                    }
                }
                $restcord->guild->addGuildMemberRole([
                    'guild.id' => (int)$config['discord']['guildId'],
                    'user.id' => (int)$_SESSION['user_id'],
                    'role.id' => (int)$role->id
                ]);
                if ((int)$config['discord']['logChannel'] !== 0) {
                    $restcord->channel->createMessage([
                        'channel.id' => (int)$config['discord']['logChannel'],
                        'content' => $eveName . ' has been added to the role ' . $role->name
                    ]);
                }
                $access[] = 'character';
                continue;
            }

            // Authentication by allianceID
            if (in_array($_SESSION['allianceID'], $id)) {
                foreach ($roles as $role) {
                    if ($role->name == $authGroup['role']) {
                        break;
                    }
                }
                $restcord->guild->addGuildMemberRole([
                    'guild.id' => (int)$config['discord']['guildId'],
                    'user.id' => (int)$_SESSION['user_id'],
                    'role.id' => (int)$role->id
                ]);
                if ((int)$config['discord']['logChannel'] !== 0) {
                    $restcord->channel->createMessage([
                        'channel.id' => (int)$config['discord']['logChannel'],
                        'content' => $eveName . ' has been added to the role ' . $role->name
                    ]);
                }
                $access[] = 'alliance';
                continue;
            }

            // Authentication by corporationID
            if (in_array($corporationID, $id)) {
                foreach ($roles as $role) {
                    if ($role->name == $authGroup['role']) {
                        break;
                    }
                }
                $restcord->guild->addGuildMemberRole([
                    'guild.id' => (int)$config['discord']['guildId'],
                    'user.id' => (int)$_SESSION['user_id'],
                    'role.id' => (int)$role->id
                ]);
                if ((int)$config['discord']['logChannel'] !== 0) {
                    $restcord->channel->createMessage([
                        'channel.id' => (int)$config['discord']['logChannel'],
                        'content' => $eveName . ' has been added to the role ' . $role->name
                    ]);
                }
                $access[] = 'corp';
                continue;
            }
        }

        // Make the json access list
        $accessList = json_encode($access);

        // Insert it all into the db
        insertUser($characterID, (int)$_SESSION['user_id'], $accessList);

        // If firetail link is active, insert into firetail db
        if ($config['firetail']['active'] === true) {
            $refreshToken = $_SESSION['auth_token'];

            firetailEntry($characterID, (int)$_SESSION['user_id'], $refreshToken, $config['firetail']['path']);
        }

        if (count($access) > 0) {
            if ($eveName !== null) {$log->notice("$eveName has been added to the role $role->name.");} else {$log->notice("$characterID has been added to the role $role->name.");}
            $_SESSION['discordCode'] = null;
            $app->render('authed.twig');
        } else {
            if ($eveName !== null) {$log->notice("Auth Failed - $eveName attempted to auth but no roles were found.");} else {$log->notice("Auth Failed - $characterID attempted to auth but no roles were found.");}
            $app->render('norole.twig');
        }
    }
});

// Ping module
$app->get('/ping/', function () use ($app, $config, $log) {
    if (isset($_GET['message'])) {
        $restcord = new DiscordClient([
            'token' => $config['discord']['botToken']
        ]);
        $data = $_SESSION['eveData'];
        $characterID = $data->CharacterID;
        $characterData = characterDetails($characterID);
        $characterName = $characterData['name'];
        $content = '';
        if (isset($_GET['everyone'])) {
            $content = '@everyone';
        }
        $restcord->channel->createMessage([
            'channel.id' => (int)$_GET['channel'],
            'content' => $content,
            'embed' => [
                'title' => 'Incoming Ping',
                'description' => 'Ping From: ' . $characterName,
                'color' => 14290439,
                'footer' => [
                    'icon_url' => 'https://webimg.ccpgamescdn.com/kvd74o0q2fjg/1M08UMgc7y8u6sQcikSuqk/6ef1923a91e38e800fb3bfca575a23c0/UPDATES_PALATINE.png_w=1280&fm=jpg',
                    'text' => $config['pings']['append']
                ],
                'thumbnail' => [
                    'url' => 'https://image.eveonline.com/Character/' . $characterID . '_32.jpg'
                ],
                'fields' => [
                    [
                        'name' => '-',
                        'value' => $_GET['message']
                    ]
                ]
            ]
        ]);
        echo "<head><meta http-equiv='refresh' content='0; url=/auth/' /></head>";
        return;
    }
    $app->render('ping.twig', [
        'config' => $config
    ]);
});

$app->run();

/**
 * Var_dumps and dies, quicker than var_dump($input); die();
 *
 * @param $input
 */
function dd($input)
{
    var_dump($input);
    die();
}
