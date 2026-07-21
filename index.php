<?php

header('Content-Type: application/json; charset=utf-8');

$url = "https://eltoque.com/tasas-de-cambio-cuba";

$cacheFile = __DIR__ . "/cache.json";
$cacheTime = 3600; // 1 hora

// Si el cache existe y no ha expirado
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheTime)) {
    readfile($cacheFile);
    exit;
}

// Descargar HTML
$ch = curl_init($url);

curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_TIMEOUT => 30,
    CURLOPT_USERAGENT => 'Mozilla/5.0',
    CURLOPT_SSL_VERIFYPEER => false,
    CURLOPT_SSL_VERIFYHOST => false,
]);

$html = curl_exec($ch);

if(curl_errno($ch)){
    http_response_code(500);
    die(json_encode([
        "success"=>false,
        "error"=>curl_error($ch)
    ], JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
}

curl_close($ch);

libxml_use_internal_errors(true);

$dom = new DOMDocument();
$dom->loadHTML($html);

$xpath = new DOMXPath($dom);

// Busca todas las filas que contienen cell-title-v2
$rows = $xpath->query("//tr[td/span[contains(@id,'cell-title-v2')]]");

$data = [];

foreach($rows as $row){

    $currencyNode = $xpath->query(".//span[contains(@id,'cell-title-v2')]", $row)->item(0);

    if(!$currencyNode) continue;

    $currency = trim(str_replace("1", "", str_replace("\xc2\xa0"," ",$currencyNode->textContent)));

    $priceNode = $xpath->query(".//span[contains(text(),'CUP')]", $row)->item(0);

    if(!$priceNode) continue;

    $priceText = trim($priceNode->textContent);

    preg_match('/([\d\.,]+)/', $priceText, $matches);

    $price = isset($matches[1]) ? floatval(str_replace(",", "", $matches[1])) : null;

    $changeNode = $xpath->query(".//span[last()]", $row)->item(0);

    $change = null;

    if($changeNode){
        if(preg_match('/^[\+\-]/', trim($changeNode->textContent))){
            $change = trim($changeNode->textContent);
        }
    }

    $data[] = [
        "currency" => $currency,
        "price" => $price,
        "currency_to" => "CUP",
        "change" => $change
    ];
}

$result = [
    "success" => true,
    "source" => $url,
    "updated_at" => date('c'),
    "count" => count($data),
    "rates" => $data
];

$json = json_encode($result, JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE);

file_put_contents($cacheFile, $json);

echo $json;
