<?php
$basePath = dirname(__DIR__);
$pairs = [
    '　' => '',
];
$oFh = fopen($basePath . '/raw/address.csv', 'w');
fputcsv($oFh, ['city', 'area', 'address']);
$pool = [];
foreach(glob($basePath . '/raw/*/*.json') AS $jsonFile) {
    $json = json_decode(file_get_contents($jsonFile), true);
    if(!empty($json)) {
        foreach($json AS $point) {
            $point['market_address'] = trim(strtr($point['market_address'], $pairs));
            if(!isset($pool[$point['market_address']])) {
                $pool[$point['market_address']] = true;
                fputcsv($oFh, [$point['city'], $point['area'], $point['market_address']]);
            }
        }
    }
}