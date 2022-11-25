<?php
use GuzzleHttp\Client;

function getStatsTotalFiles(string $ruta): array {
    $dir = new RecursiveDirectoryIterator($ruta, FilesystemIterator::SKIP_DOTS);
    $ite = new RecursiveIteratorIterator($dir);
    $info = ["size" => 0, "cant" => 0];

    foreach($ite as $file) {
        $info["cant"]++;
        $info["size"] += $file->getSize();
    }

    return $info;
}

function uuidv4(): string {
    $data = random_bytes(16);
    assert(strlen($data) == 16);

    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

function getFilename(string $ruta): string{
    $info = pathinfo($ruta);
    return $info['filename']. ( array_key_exists("extension", $info) ? ".".$info["extension"] : "");
}

function getAccessTokenPayPal(): string {
    $url_api = $_ENV["URL_API_PAYPAL"];
    $client_id = $_ENV["PAYPAL_CLIENT_ID"];
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, "$url_api/v1/oauth2/token");
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    curl_setopt($ch, CURLOPT_USERPWD, $client_id . ':' . $_ENV["PAYPAL_SECRET"]);    
    $headers = array();
    $headers[] = 'Content-Type: application/x-www-form-urlencoded';
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        return false;
    }
    $token = json_decode($result, true)["access_token"];
    curl_close($ch);
    return $token;
}

function createOrder(array $data, string $id): array {
    $apiUrl = $_ENV["URL_API_PAYPAL"];
    $url = "$apiUrl/v2/checkout/orders";

    $data = json_encode($data);
    // echo $data;
    $token = getAccessTokenPayPal();

    $ch = curl_init();

    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 1);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_FORBID_REUSE, 1);
    curl_setopt($ch, CURLOPT_POSTFIELDS, $data);

    $headers = array(
        'Content-Type: application/json', 
        "Authorization: Bearer $token"
    );
    $headers[] = "Paypal-Request-Id: $id";
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);

    $result = curl_exec($ch);
    if (curl_errno($ch)) {
        echo 'Error:' . curl_error($ch);
        return false;
    }
    curl_close($ch);
    return json_decode($result, true);
}

function checkOrder($orderId){
    $apiUrl = $_ENV["URL_API_PAYPAL"];
    $url = "$apiUrl/v2/checkout/orders/$orderId";

    $token = getAccessTokenPayPal();

    $client = new Client();
    $response = $client->get($url, [
        'headers' => [
            "Content-Type" => "application/json",
            "Authorization" => "Bearer $token"
        ],
    ]);
    $body = (string) $response->getBody();
    return json_decode($body, true);
}

?>