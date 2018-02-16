<?php

function insertUser($characterID, $discordID, $accessList)
{
    dbExecute('REPLACE INTO authed (`characterID`, `discordID`, `groups`) VALUES (:characterID,:discordID,:groups)', array(':characterID' => $characterID, ':discordID' => $discordID, ':groups' => $accessList));
    return null;
}

function getUsers()
{
    return dbQuery('SELECT * FROM authed');
}

function getUser($discordId)
{
    return dbQuery('SELECT * FROM authed WHERE `discordID` = :discordID', array(':discordID' => $discordId));
}

function deleteUser($id)
{
    dbQueryRow('DELETE from authed WHERE `id` = :id', array(':id' => $id));
    return null;
}

function firetailEntry($characterID, $discordID, $token, $db)
{
    dbExecute('REPLACE INTO access_tokens (`character_id`, `discord_id`, `token`) VALUES (:character_id,:discord_id,:token)', array(':character_id' => $characterID, ':discord_id' => $discordID, ':token' => $token), $db);
    return null;
}

function insertKeepstar($variable, $value)
{
    dbExecute('REPLACE INTO keepstar (`variable`, `value`) VALUES (:variable,:value)', array(':variable' => $variable, ':value' => $value));
    return null;
}

function getKeepstar($variable)
{
    return dbQuery('SELECT * FROM keepstar WHERE `variable` = :variable', array(':variable' => $variable));
}

function openDB($db = true)
{
    if ($db === true) {
        $db = __DIR__ . '/database/auth.sqlite';
    }

    $dsn = "sqlite:$db";
    try {
        $pdo = new PDO($dsn, '', '', array(
                PDO::ATTR_PERSISTENT => false,
                PDO::ATTR_EMULATE_PREPARES => true,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
            )
        );
    } catch (Exception $e) {
        $pdo = null;
        return $pdo;
    }

    return $pdo;
}

function dbExecute($query, array $params = array(), $db = true)
{
    $pdo = openDB($db);
    if ($pdo === NULL) {
        return;
    }

    // This is ugly, but, yeah..
    if (false !== strpos($query, ';')) {
        $explodedQuery = explode(';', $query);
        $stmt = null;
        foreach ($explodedQuery as $newQry) {
            $stmt = $pdo->prepare($newQry);
            $stmt->execute($params);
        }
        $stmt->closeCursor();
    } else {
        $stmt = $pdo->prepare($query);
        $stmt->execute($params);
        $stmt->closeCursor();
    }
}

function dbQuery($query, array $params = array())
{
    $pdo = openDB();
    if ($pdo === NULL) {
        return null;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    return $result;
}
function dbQueryRow($query, array $params = array())
{
    $pdo = openDB();
    if ($pdo == NULL) {
        return null;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (count($result) >= 1) {
        return $result[0];
    }
    return null;
}

function dbQueryField($query, $field, array $params = array(), $db = null)
{
    $pdo = openDB($db);
    if ($pdo == NULL) {
        return null;
    }

    $stmt = $pdo->prepare($query);
    $stmt->execute($params);

    $result = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt->closeCursor();

    if (count($result) == 0) {
        return null;
    }

    $resultRow = $result[0];
    return $resultRow[$field];
}