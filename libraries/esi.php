<?php

function characterDetails($characterID) {
	try {
		// Initialize a new request for this URL
		$ch = curl_init("https://esi.evetech.net/latest/characters/{$characterID}/");
		// Set the options for this request
		curl_setopt_array($ch, [
			CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
			CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
			CURLOPT_TIMEOUT => 8,
			CURLOPT_SSL_VERIFYPEER => true, // Do not verify the SSL certificate
		]);
		// Fetch the data from the URL
		$data = curl_exec($ch);
		// Close the connection
		curl_close($ch);
		$data = json_decode($data, true);
	} catch(Exception $e) {
		return null;
	}

	return $data;
}

function corporationDetails($corpID) {
	try {
		// Initialize a new request for this URL
		$ch = curl_init("https://esi.evetech.net/latest/corporations/{$corpID}/");
		// Set the options for this request
		curl_setopt_array($ch, [
			CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
			CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
			CURLOPT_TIMEOUT => 8,
			CURLOPT_SSL_VERIFYPEER => true, // Do not verify the SSL certificate
		]);
		// Fetch the data from the URL
		$data = curl_exec($ch);
		// Close the connection
		curl_close($ch);
		$data = json_decode($data, true);
	} catch(Exception $e) {
		return null;
	}

	return $data;
}

function allianceDetails($allianceID) {
	try {
		// Initialize a new request for this URL
		$ch = curl_init("https://esi.evetech.net/latest/alliances/{$allianceID}/");
		// Set the options for this request
		curl_setopt_array($ch, [
			CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
			CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
			CURLOPT_TIMEOUT => 8,
			CURLOPT_SSL_VERIFYPEER => true, // Do not verify the SSL certificate
		]);
		// Fetch the data from the URL
		$data = curl_exec($ch);
		// Close the connection
		curl_close($ch);
		$data = json_decode($data, true);
	} catch(Exception $e) {
		return null;
	}

	return $data;
}

function serverStatus() {
	try {
		// Initialize a new request for this URL
		$ch = curl_init("https://esi.evetech.net/latest/status/");
		// Set the options for this request
		curl_setopt_array($ch, [
			CURLOPT_FOLLOWLOCATION => true, // Yes, we want to follow a redirect
			CURLOPT_RETURNTRANSFER => true, // Yes, we want that curl_exec returns the fetched data
			CURLOPT_TIMEOUT => 8,
			CURLOPT_SSL_VERIFYPEER => true, // Do not verify the SSL certificate
		]);
		// Fetch the data from the URL
		$data = curl_exec($ch);
		// Close the connection
		curl_close($ch);
		$data = json_decode($data, true);
	} catch(Exception $e) {
		return null;
	}

	return $data;
}
