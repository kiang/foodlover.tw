<?php
$basePath = dirname(__DIR__);
$ref = [];
$fh = fopen($basePath . '/raw/tgos/Address_GetAddressXY_Damo_Finish20211011231834.csv', 'r');
fgetcsv($fh, 2048);
while ($line = fgetcsv($fh, 2048)) {
    foreach ($line as $k => $v) {
        $line[$k] = mb_convert_encoding($v, 'utf-8', 'big5');
    }
    if (isset($line[3])) {
        $pos = strpos($line[0], '號');
        if (false !== $pos) {
            $address = trim(substr($line[0], 0, $pos)) . '號';
            $ref[$address] = [$line[2], $line[3]];
        }
    }
}
$fh = fopen($basePath . '/raw/ref.csv', 'r');
fgetcsv($fh, 2048);
while ($line = fgetcsv($fh, 2048)) {
    if (!empty($line[3])) {
        $ref[$line[0]] = [$line[2], $line[3]];
    }
}
$pairs = [
    '　' => '',
];
$oFh = fopen($basePath . '/raw/missing.csv', 'w');
fputcsv($oFh, ['city', 'area', 'address']);
$pool = [];
$docs = [];
foreach (glob($basePath . '/raw/*/*.json') as $jsonFile) {
    $json = json_decode(file_get_contents($jsonFile), true);
    if (!empty($json)) {
        foreach ($json as $point) {
            $point['market_address'] = trim(strtr($point['market_address'], $pairs));
            $pos = strpos($point['market_address'], '號');
            if (false !== $pos) {
                $address = substr($point['market_address'], 0, $pos) . '號';
                $address = str_replace($point['city'], '', $address);
                $address = trim(str_replace($point['area'], '', $address));
                $address = $point['city'] . $point['area'] . $address;
                if (isset($ref[$address])) {
                    if (!isset($docs[$point['city']])) {
                        $docs[$point['city']] = [];
                    }
                    $point['longitude'] = $ref[$address][0];
                    $point['latitude'] = $ref[$address][1];
                    $docs[$point['city']][] = $point;
                } elseif (!isset($ref[$address]) && !isset($pool[$address])) {
                    $pool[$address] = true;
                    fputcsv($oFh, [$point['city'], $point['area'], $address]);
                }
            }
        }
    }
}

$docsPath = $basePath . '/docs';
if(!file_exists($docsPath)) {
    mkdir($docsPath, 0777, true);
}
$paylist = [];
foreach ($docs as $city => $points) {
    $fc = [
        'type' => 'FeatureCollection',
        'features' => [],
    ];
    foreach ($points as $point) {
        $coordinates = [
            $point['longitude'],
            $point['latitude'],
        ];
        unset($point['longitude']);
        unset($point['latitude']);
        foreach($point['pay_list'] AS $pay) {
            if(!isset($paylist[$pay])) {
                $paylist[$pay] = 0;
            }
            ++$paylist[$pay];
        }
        foreach($point AS $k => $v) {
            if(is_array($v)) {
                $point[$k] = implode('/', $v);
            }
        }
        $fc['features'][] = [
            'type' => 'Feature',
            'properties' => $point,
            'geometry' => [
                'type' => 'Point',
                'coordinates' => $coordinates,
            ],
        ];
    }
    file_put_contents($docsPath . '/' . $city . '.json', json_encode($fc, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}