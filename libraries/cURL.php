<?php

/**
 * @param $url
 * @return mixed|null
 */
function getData($url)
{
    try
    {
        $userAgent = "Discord Auth";

        $curl = curl_init();
        curl_setopt($curl, CURLOPT_USERAGENT, $userAgent);
        curl_setopt($curl, CURLOPT_TIMEOUT, 300);
        curl_setopt($curl, CURLOPT_POST, false);
        curl_setopt($curl, CURLOPT_FORBID_REUSE, false);
        curl_setopt($curl, CURLOPT_ENCODING, "");
        $headers = array();
        $headers[] = "Connection: keep-alive";
        $headers[] = "Keep-Alive: timeout=10, max=1000";
        curl_setopt($curl, CURLOPT_URL, $url);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        $result = curl_exec($curl);

        return $result;
    }
    catch(Exception $e)
    {
        var_dump("cURL Error: " . $e->getMessage());
        return null;
    }
}

function sendData($url, $postData = array(), $headers = array()) {
    $userAgent = "Discord Auth";

    // Define default headers
    if (empty($headers)) {
        $headers = array('Connection: keep-alive', 'Keep-Alive: timeout=10, max=1000');
    }

    // Init curl
    $curl = curl_init();

    // Init postLine
    $postLine = '';

    // Populate the $postData
    if (!empty($postData)) {
        foreach ($postData as $key => $value) {
            $postLine .= $key . '=' . $value . '&';
        }
    }

    // Trim the last &
    rtrim($postLine, '&');

    curl_setopt($curl, CURLOPT_URL, $url);
    curl_setopt($curl, CURLOPT_USERAGENT, $userAgent);
    curl_setopt($curl, CURLOPT_HTTPHEADER, $headers);
    if (!empty($postData)) {
        curl_setopt($curl, CURLOPT_POST, count($postData));
        curl_setopt($curl, CURLOPT_POSTFIELDS, $postLine);
    }

    curl_setopt($curl, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($curl, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($curl, CURLOPT_SSL_VERIFYHOST, 2);

    $result = curl_exec($curl);

    curl_close($curl);

    return $result;
}