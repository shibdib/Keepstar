<?php
$config = array();

// CREST
$config["sso"] = array(
    "clientID" => "", // https://developers.eveonline.com/
    "secretKey" => "",
    "callbackURL" => "", // Include trailing / (Will be the url_to_the_index.com/auth/)
);

$config["db"] = array(
    "url" => "",
    "user" => "",
    "pass" => "",
    "dbname" => ""
);

$config["discord"] = array(
    "guildId" => 12345, //Get the guild ID for your discord server
    "logChannel" => 0, //The channel ID for where you want the bot to report auth stuff (leave as 0 to disable)
    "inviteLink" => "", //Make sure it's set to never expire and set to a public channel.
    "botToken" => "", //Must be the firetail bot in your server
    "clientId" => "", //Must be the firetail bot in your server
    "clientSecret" => "", //Must be the firetail bot in your server
);

$config["groups"] = array(
    "group1" => array(
        "id" => "1234", // Corp/Alliance/Player ID
        "role" => "" //Role Name
    ),
    "group2" => array(
        "id" => "1234", // Corp/Alliance/Player ID
        "role" => "" //Role Name
    ),
);

// Site IGNORE EVERYTHING BELOW THIS LINE
$config["site"] = array(
    "debug" => true,
    "userAgent" => null, // Use pre-defined user agents
    "apiRequestsPrMinute" => 1800,
);

// Cookies
$config["cookies"] = array(
    "name" => "rena",
    "ssl" => true,
    "time" => (3600 * 24 * 30),
    "secret" => "",
);

// Slim
$config["slim"] = array(
    "mode" => $config["site"]["debug"] ? "development" : "production",
    "debug" => $config["site"]["debug"],
    "cookies.secret_key" => $config["cookies"]["secret"],
    "templates.path" => BASEDIR . "/view/",
);

