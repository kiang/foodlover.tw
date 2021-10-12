<?php
$basePath = dirname(__DIR__);
$config = require __DIR__ . '/config.php';
$geocodingPath = $basePath . '/raw/geocoding';
if (!file_exists($geocodingPath)) {
    mkdir($geocodingPath, 0777, true);
}
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
    if(false !== strpos($jsonFile, 'geocoding')) {
        continue;
    }
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
                } else {
                    $isMissing = true;
                    $geocodingFile = $geocodingPath . '/' . $address . '.json';
                    if (!file_exists($geocodingFile)) {
                        $apiUrl = $config['tgos']['url'] . '?' . http_build_query([
                            'oAPPId' => $config['tgos']['APPID'], //應用程式識別碼(APPId)
                            'oAPIKey' => $config['tgos']['APIKey'], // 應用程式介接驗證碼(APIKey)
                            'oAddress' => $address, //所要查詢的門牌位置
                            'oSRS' => 'EPSG:4326', //回傳的坐標系統
                            'oFuzzyType' => '2', //模糊比對的代碼
                            'oResultDataType' => 'JSON', //回傳的資料格式
                            'oFuzzyBuffer' => '0', //模糊比對回傳門牌號的許可誤差範圍
                            'oIsOnlyFullMatch' => 'false', //是否只進行完全比對
                            'oIsLockCounty' => 'true', //是否鎖定縣市
                            'oIsLockTown' => 'false', //是否鎖定鄉鎮市區
                            'oIsLockVillage' => 'false', //是否鎖定村里
                            'oIsLockRoadSection' => 'false', //是否鎖定路段
                            'oIsLockLane' => 'false', //是否鎖定巷
                            'oIsLockAlley' => 'false', //是否鎖定弄
                            'oIsLockArea' => 'false', //是否鎖定地區
                            'oIsSameNumber_SubNumber' => 'true', //號之、之號是否視為相同
                            'oCanIgnoreVillage' => 'true', //找不時是否可忽略村里
                            'oCanIgnoreNeighborhood' => 'true', //找不時是否可忽略鄰
                            'oReturnMaxCount' => '0', //如為多筆時，限制回傳最大筆數
                        ]);
                        $content = file_get_contents($apiUrl);
                        $pos = strpos($content, '{');
                        $posEnd = strrpos($content, '}') + 1;
                        $resultline = substr($content, $pos, $posEnd - $pos);
                        if (strlen($resultline) > 10) {
                            file_put_contents($geocodingFile, substr($content, $pos, $posEnd - $pos));
                        } else {
                            echo $content . "\n";
                        }
                    }
                    if (file_exists($geocodingFile)) {
                        $geocodingJson = json_decode(file_get_contents($geocodingFile), true);
                        if (!empty($geocodingJson['AddressList'][0]['X'])) {
                            $isMissing = false;
                            $point['longitude'] = floatval($geocodingJson['AddressList'][0]['X']);
                            $point['latitude'] = floatval($geocodingJson['AddressList'][0]['Y']);
                            $docs[$point['city']][] = $point;
                        }
                    }
                    if ($isMissing && !isset($pool[$address])) {
                        $pool[$address] = true;
                        fputcsv($oFh, [$point['city'], $point['area'], $address]);
                    }
                }
                
            }
        }
    }
}

$docsPath = $basePath . '/docs';
if(!file_exists($docsPath)) {
    mkdir($docsPath, 0777, true);
}
$paylist = $categories = [];
foreach ($docs as $city => $points) {
    $fc = [
        'type' => 'FeatureCollection',
        'features' => [],
    ];
    foreach ($points as $point) {
        $coordinates = [
            floatval($point['longitude']),
            floatval($point['latitude']),
        ];
        unset($point['index']);
        unset($point['zone']);
        unset($point['city']);
        unset($point['longitude']);
        unset($point['latitude']);
        foreach($point['pay_list'] AS $pay) {
            if(!isset($paylist[$pay])) {
                $paylist[$pay] = 0;
            }
            ++$paylist[$pay];
        }
        foreach($point['category'] AS $cat) {
            if(!isset($categories[$cat])) {
                $categories[$cat] = 0;
            }
            ++$categories[$cat];
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
print_r($categories);
print_r($paylist);