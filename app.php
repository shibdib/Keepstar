<?php

define("BASEDIR", __DIR__);
ini_set("display_errors", 1);
error_reporting(E_ALL);

require_once(BASEDIR . "/config/config.php");
require_once(BASEDIR . "/vendor/autoload.php");

use Monolog\Handler\RotatingFileHandler;
use Monolog\Logger;
use RestCord\DiscordClient;

$log = new Logger('DScan');
$log->pushHandler(new RotatingFileHandler(__DIR__ . '/log/Keepstar.log', Logger::NOTICE));

$app = new \Slim\Slim($config["slim"]);
$app->add(new \Zeuxisoo\Whoops\Provider\Slim\WhoopsMiddleware());
$app->view(new \Slim\Views\Twig());

// Load libraries
foreach (glob(BASEDIR . "/libraries/*.php") as $lib)
    require_once($lib);

//Ensure DB Is Created
createAuthDb();
//Convert mysql if needed
if (isset($config['mysql']['password']) && !file_exists(__DIR__ . '/tools/.blocker')) {
    require_once(BASEDIR . "/tools/mysqlConverter.php");
    convertMysql($config);
}

//add a check for old configs
if (!isset($config['firetail'])) {
    $config['firetail']['active'] = False;
}

// Routes
$app->get("/admin/", function () use ($app, $config) {
    if (!getKeepstar('botStarted')) {
        $app->render("admin.twig", array("botToken" => $config['discord']['botToken']));
        insertKeepstar('botStarted','True');
    } else {
        echo "You have no reason to go here.";
    }
});
$app->get("/", function () use ($app, $config) {
    //Clear out session just incase
    $_SESSION = array();
    if (isset($_COOKIE[session_name()])) {
        setcookie(session_name(), '', time()-42000, '/');
    }
    //Check if keepstar is linked to firetail
    if ($config['firetail']['active'] === true) {
        $scopes = str_replace(' ', '%20', $config['firetail']['scopes']);
        $url = 'https://login.eveonline.com/oauth/authorize?response_type=code&scope=' . $scopes . '&redirect_uri=' . $config['sso']['callbackURL'] . '&client_id=' . $config['sso']['clientID'];
    } else {
        $url = 'https://login.eveonline.com/oauth/authorize?response_type=code&redirect_uri=' . $config['sso']['callbackURL'] . '&client_id=' . $config['sso']['clientID'];
    }
    $app->render("index.twig", array("crestURL" => $url));
});

$app->get("/auth/", function () use ($app, $config, $log) {
    if (isset($_GET['code']) && !isset($_SESSION["eveCode"])) {
        $_SESSION["eveCode"] = $_GET['code'];
        $url = $config['sso']['callbackURL'];
        echo "<head><meta http-equiv='refresh' content='0; url=$url' /></head>";
        return;
    }
    if (!isset($_GET['code'])) {
        // If we don't have a code yet, we need to make the link
        $scopes = 'identify%20guilds';
        $discordLink = url($config['discord']['clientId'], $config['discord']['redirectUri'], $scopes);
        $app->render("discord.twig", array("botToken" => $config['discord']['botToken'], "discordLink" => $discordLink));

    } else {
        // If we do have a code, use it to get a token
        $code = $_GET['code'];
        init($code, $config['discord']['redirectUri'], $config['discord']['clientId'], $config['discord']['clientSecret']);
        get_user();
        $guilds = get_guilds();
        $guildIds = [];
        foreach ($guilds as $guild) {
            $guildIds[] = $guild['id'];
        }
        $restcord = new DiscordClient(['token' => $config['discord']['botToken']]);
        if (!in_array($config['discord']['guildId'], $guildIds, false)) {
            $app->render("notinserver.twig", array("discordLink" => $config['discord']['inviteLink']));
            return;
        }

        $code = $_SESSION["eveCode"];

        //Make sure bots nick is set
        if (isset($config['discord']['botNick'])) {
            $restcord->guild->modifyCurrentUserNick(['guild.id' => (int)$config['discord']['guildId'], 'nick' => $config['discord']['botNick']]);
        }

        $tokenURL = "https://login.eveonline.com/oauth/token";
        $base64 = base64_encode($config["sso"]["clientID"] . ":" . $config["sso"]["secretKey"]);

        $data = json_decode(sendData($tokenURL, array(
            "grant_type" => "authorization_code",
            "code" => $code
        ), array("Authorization: Basic {$base64}")));

        $accessToken = $data->access_token;


        // Verify Token
        $verifyURL = "https://login.eveonline.com/oauth/verify";
        $data = json_decode(sendData($verifyURL, array(), array("Authorization: Bearer {$accessToken}")));

        $characterID = $data->CharacterID;
        $characterData = characterDetails($characterID);
        $corporationID = $characterData['corporation_id'];
        $corporationData = corporationDetails($corporationID);
        $eveName = $characterData['name'];
        $currentGuild = $restcord->guild->getGuild(['guild.id' => (int)$config['discord']['guildId']]);
        if (!isset($characterData['alliance_id'])) {
            $allianceID = 1;
        } else {
            $allianceID = $characterData['alliance_id'];
        }

        // Now check if the person is in a corp or alliance on the blue / allowed list
        // Whatever ID matches whatever group, they get added to. Discord role ordering decides what they can and can't see
        $access = array();
        $roles = $restcord->guild->getGuildRoles(['guild.id' => $config['discord']['guildId']]);
        if (($config['discord']['enforceInGameName'] || $config['discord']['addTicker']) && (int)$currentGuild->owner_id !== (int)$_SESSION['user_id']) {
            if ($config['discord']['enforceInGameName'] && $config['discord']['addTicker']) {
                $newNick = "[" . $corporationData['ticker'] . "] " . $eveName;
                $restcord->guild->modifyGuildMember(['guild.id' => (int)$config['discord']['guildId'], 'user.id' => (int)$_SESSION['user_id'], 'nick' => $newNick]);
            } else if (!$config['discord']['enforceInGameName'] && $config['discord']['addTicker']) {
                $memberDetails = $restcord->guild->getGuildMember(['guild.id' => (int)$config['discord']['guildId'], 'user.id' => (int)$_SESSION['user_id']]);
                if ($memberDetails->nick) {
                    $cleanNick = str_replace("[" . $corporationData['ticker'] . "]", "", $memberDetails->nick);
                    $newNick = "[" . $corporationData['ticker'] . "]" . $cleanNick;
                } else {
                    $newNick = "[" . $corporationData['ticker'] . "]" . $memberDetails->user->username;
                }
                $restcord->guild->modifyGuildMember(['guild.id' => (int)$config['discord']['guildId'], 'user.id' => (int)$_SESSION['user_id'], 'nick' => $newNick]);
            } else {
                $restcord->guild->modifyGuildMember(['guild.id' => (int)$config['discord']['guildId'], 'user.id' => (int)$_SESSION['user_id'], 'nick' => $eveName]);
            }
        }
        foreach ($config["groups"] as $authGroup) {
            $id = $authGroup["id"];
            $role = null;
            if ($id == 1234) {
                foreach ($roles as $role) {
                    if ($role->name == $authGroup["role"]) {
                        break;
                    }
                }
                $restcord->guild->addGuildMemberRole(['guild.id' => (int)$config['discord']['guildId'], 'user.id' => (int)$_SESSION['user_id'], 'role.id' => (int)$role->id]);
                if ((int)$config['discord']['logChannel'] !== 0) {
                    $restcord->channel->createMessage(['channel.id' => (int)$config['discord']['logChannel'], 'content' => "$eveName has been added to the role $role->name"]);
                }
                $access[] = 'character';
                continue;
            }
            if ($id == $characterID) {
                foreach ($roles as $role) {
                    if ($role->name == $authGroup["role"]) {
                        break;
                    }
                }
                $restcord->guild->addGuildMemberRole(['guild.id' => (int)$config['discord']['guildId'], 'user.id' => (int)$_SESSION['user_id'], 'role.id' => (int)$role->id]);
                if ((int)$config['discord']['logChannel'] !== 0) {
                    $restcord->channel->createMessage(['channel.id' => (int)$config['discord']['logChannel'], 'content' => "$eveName has been added to the role $role->name"]);
                }
                $access[] = 'character';
                continue;
            }
            if ($id == $allianceID) {
                foreach ($roles as $role) {
                    if ($role->name == $authGroup["role"]) {
                        break;
                    }
                }
                $restcord->guild->addGuildMemberRole(['guild.id' => (int)$config['discord']['guildId'], 'user.id' => (int)$_SESSION['user_id'], 'role.id' => (int)$role->id]);
                if ((int)$config['discord']['logChannel'] !== 0) {
                    $restcord->channel->createMessage(['channel.id' => (int)$config['discord']['logChannel'], 'content' => "$eveName has been added to the role $role->name"]);
                }
                $access[] = 'alliance';
                continue;
            }
            if ($id == $corporationID) {
                foreach ($roles as $role) {
                    if ($role->name == $authGroup["role"]) {
                        break;
                    }
                }
                $restcord->guild->addGuildMemberRole(['guild.id' => (int)$config['discord']['guildId'], 'user.id' => (int)$_SESSION['user_id'], 'role.id' => (int)$role->id]);
                if ((int)$config['discord']['logChannel'] !== 0) {
                    $restcord->channel->createMessage(['channel.id' => (int)$config['discord']['logChannel'], 'content' => "$eveName has been added to the role $role->name"]);
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
            $refreshToken = $data->refresh_token;
            firetailEntry($characterID, (int)$_SESSION['user_id'], $refreshToken, $config['firetail']['path']);
        }


        if (count($access) > 0) {
            //if (isset($eveName)) {$log->notice("$eveName has been added to the role $role->name.");} else {$log->notice("$discordId has been added to the role $role->name.");}
            $app->render("authed.twig");
        } else {
            //if (isset($eveName)) {$log->notice("Auth Failed - $eveName attempted to auth but no roles were found.");} else {$log->notice("Auth Failed - $discordId attempted to auth but no roles were found.");}
            $app->render("norole.twig");
        }
    }
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
