<?php
function characterDetails($characterID)
{
    try {
        // Initialize a new request for this URL
        $ch = curl_init("https://esi.tech.ccp.is/latest/characters/{$characterID}/");
        // Set the options for this request
        curl_setopt_array($ch, array(
            CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
            CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
            CURLOPT_TIMEOUT => 8,
            CURLOPT_SSL_VERIFYPEER => true, // Do not verify the SSL certificate
        ));
        // Fetch the data from the URL
        $data = curl_exec($ch);
        // Close the connection
        curl_close($ch);
        $data = json_decode($data, TRUE);

    } catch (Exception $e) {
        return null;
    }

    return $data;
}