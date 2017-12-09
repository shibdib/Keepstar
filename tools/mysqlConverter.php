<?php
//Only used for old dramiel users
function convertMysql($config)
{
    $host = $config['mysql']['host'];
    $username = $config['mysql']['user'];
    $password = $config['mysql']['password'];
    $database = $config['mysql']['dbname'];
    $mysqli = mysqli_connect($host, $username, $password, $database);

    if ($stmt = $mysqli->prepare("SELECT * FROM authUsers WHERE active='yes'")) {

        // Execute the statement.
        $stmt->execute();

        // Return Row
        $result = $stmt->get_result();

        // Close the prepared statement.
        $stmt->close();

        while ($user = mysqli_fetch_object($result)) {
            if (!isset($user->characterID)) {break;}
            if ($user->role === "corp") {
                $access[] = 'corp';
            } else if ($user->role === "corp/ally") {
                $access[] = 'corp';
                $access[] = 'alliance';
            } else if ($user->role === "ally") {
                $access[] = 'alliance';
            } else {
                $access[] = 'corp';
                $access[] = 'alliance';
                $access[] = 'character';
            }
            $accessList = json_encode($access);
            insertUser($user->characterID, $user->discordID, $accessList);
        }
        if (!file_exists(__DIR__ . '/.blocker')) {
            touch(__DIR__ . '/.blocker');
        }
    }
    return null;
}