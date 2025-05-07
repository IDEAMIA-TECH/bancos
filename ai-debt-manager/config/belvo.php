<?php
// Belvo API Configuration
define('BELVO_API_URL', 'https://sandbox.belvo.com');
define('BELVO_API_KEY', 'f3958986-7c2a-44ef-99e5-e2e49570a66a');
define('BELVO_API_SECRET', 'ccTM5Gd@1I4vW6HRMnFszh7LrSf0OFoQyINZHzL*H3ppgehYaARx@k#4SgVHD3h#');

// Belvo Webhook Configuration
define('BELVO_WEBHOOK_SECRET', 'ccTM5Gd@1I4vW6HRMnFszh7LrSf0OFoQyINZHzL*H3ppgehYaARx@k#4SgVHD3h#'); // Using API secret as webhook secret for sandbox

// Belvo API Endpoints
define('BELVO_LINKS_ENDPOINT', '/api/links/');
define('BELVO_ACCOUNTS_ENDPOINT', '/api/accounts/');
define('BELVO_TRANSACTIONS_ENDPOINT', '/api/transactions/');
define('BELVO_BALANCES_ENDPOINT', '/api/balances/');

// Belvo API Headers
function getBelvoHeaders() {
    return [
        'Authorization: Basic ' . base64_encode(BELVO_API_KEY . ':' . BELVO_API_SECRET),
        'Content-Type: application/json',
        'Accept: application/json'
    ];
}

// Belvo API Functions
function belvoApiRequest($endpoint, $method = 'GET', $data = null) {
    $curl = curl_init();
    
    $url = BELVO_API_URL . $endpoint;
    
    $options = [
        CURLOPT_URL => $url,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_ENCODING => '',
        CURLOPT_MAXREDIRS => 10,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => getBelvoHeaders(),
    ];
    
    if ($data) {
        $options[CURLOPT_POSTFIELDS] = json_encode($data);
    }
    
    curl_setopt_array($curl, $options);
    
    $response = curl_exec($curl);
    $err = curl_error($curl);
    
    curl_close($curl);
    
    if ($err) {
        throw new Exception("cURL Error: " . $err);
    }
    
    return json_decode($response, true);
}

// Belvo Link Functions
function createBelvoLink($institution, $username, $password, $user_id) {
    $data = [
        'institution' => $institution,
        'username' => $username,
        'password' => $password,
        'access_mode' => 'single',
        'external_id' => $user_id
    ];
    
    return belvoApiRequest(BELVO_LINKS_ENDPOINT, 'POST', $data);
}

function getBelvoLink($link_id) {
    return belvoApiRequest(BELVO_LINKS_ENDPOINT . $link_id);
}

function deleteBelvoLink($link_id) {
    return belvoApiRequest(BELVO_LINKS_ENDPOINT . $link_id, 'DELETE');
}

// Belvo Account Functions
function getBelvoAccounts($link_id) {
    return belvoApiRequest(BELVO_ACCOUNTS_ENDPOINT . '?link=' . $link_id);
}

// Belvo Transaction Functions
function getBelvoTransactions($link_id, $date_from = null, $date_to = null) {
    $endpoint = BELVO_TRANSACTIONS_ENDPOINT . '?link=' . $link_id;
    
    if ($date_from) {
        $endpoint .= '&date_from=' . $date_from;
    }
    
    if ($date_to) {
        $endpoint .= '&date_to=' . $date_to;
    }
    
    return belvoApiRequest($endpoint);
}

// Belvo Balance Functions
function getBelvoBalances($link_id) {
    return belvoApiRequest(BELVO_BALANCES_ENDPOINT . '?link=' . $link_id);
}

// Belvo Webhook Functions
function verifyBelvoWebhook($payload, $signature) {
    $computedSignature = hash_hmac('sha256', $payload, BELVO_WEBHOOK_SECRET);
    return hash_equals($computedSignature, $signature);
} 