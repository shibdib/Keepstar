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

function deleteUser($id)
{
    dbQueryRow('DELETE from authed WHERE `id` = :id', array(':id' => $id));
    return null;
}

function openDB()
{
    $db = __DIR__ . '/database/auth.sqlite';

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

function dbExecute($query, array $params = array())
{
    $pdo = openDB();
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