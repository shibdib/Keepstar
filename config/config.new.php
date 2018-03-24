<?php
$config = array();

// CREST
$config['sso'] = array(
    'clientID' => '', // https://developers.eveonline.com/
    'secretKey' => '',
    'callbackURL' => '', // Include trailing / (Will be the url_to_the_index.com/auth/)
);

$config['discord'] = array(
    'botNick' => 'Keepstar', //Change the nickname of the auth bot here
    'guildId' => 12345, //Get the guild ID for your discord server
    'logChannel' => 0, //The channel ID for where you want the bot to report auth stuff (leave as 0 to disable)
    'enforceInGameName' => false, //Setting this to true will change players names to match their ingame name when they auth (this works retroactively)
    'addTicker' => false, //Setting this to true will add the corp ticker to the beginning of the users discord name
    'removeUser' => False, //Setting this to true will kick the user from the server if their roles are removed (Requires the bot to have admin or kicking permissions)
    'inviteLink' => '', //Make sure it's set to never expire and set to a public channel.
    'botToken' => '', //The bot must be a member of your server
    'clientId' => '', //The bot must be a member of your server
    'clientSecret' => '', //The bot must be a member of your server
    'redirectUri' => '' //The bot must be a member of your server (same as SSO callbackURL)
);

$config['groups'] = array(
    'group1' => array(
        'id' => ['1234'], // Corp/Alliance/Player ID
        'role' => '' //Role Name
    ),
    'group2' => array(
        'id' => ['1234', '1234'], // Corp/Alliance/Player ID
        'role' => '' //Role Name
    ),
);

// Site IGNORE EVERYTHING BELOW THIS LINE
$config['site'] = array(
    'debug' => true,
    'userAgent' => null, // Use pre-defined user agents
    'apiRequestsPrMinute' => 1800,
);

// Cookies
$config['cookies'] = array(
    'name' => 'rena',
    'ssl' => true,
    'time' => 3600 * 24 * 30,
    'secret' => '',
);

// Slim
$config['slim'] = array(
    'mode' => $config['site']['debug'] ? 'development' : 'production',
    'debug' => $config['site']['debug'],
    'cookies.secret_key' => $config['cookies']['secret'],
    'templates.path' => BASEDIR . '/view/',
);

//DO NOT USE THIS YET
$config['firetail'] = array( // Only change this section if you're linking to a local install of firetail
    'active' => false, //Set to true if you have a local install of firetail and want to link it with keepstar
    'path' => '/home/user/firetail/firetail.sqlite', //Database path for firetail
    'scopes' => '' // Click copy scopes to clipboard and paste them here
);

