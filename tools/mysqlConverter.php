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

        $x = 0;
        while ($user = mysqli_fetch_array($result, MYSQLI_NUM)) {
            if (!isset($user[$x]['characterID'])) {break;}
            $access = array();
            if ($user[$x]['role'] === "corp") {
                $access[] = 'corp';
            } else if ($user[$x]['role'] === "corp/ally") {
                $access[] = 'corp';
                $access[] = 'alliance';
            } else if ($user[$x]['role'] === "ally") {
                $access[] = 'alliance';
            } else {
                $access[] = 'character';
            }
            $accessList = json_encode($access);
            insertUser($user[$x]['characterID'], $user[$x]['discordID'], $accessList);
            $x++;
        }
        if (!file_exists(__DIR__ . '.blocker')) {
            touch(__DIR__ . '.blocker');
        }
    }
    return null;
}