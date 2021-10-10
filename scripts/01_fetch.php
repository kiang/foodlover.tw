<?php
$basePath = dirname(__DIR__);
$categories = ['餐飲', '夜市/市場攤販', '糕餅', '觀光工廠', '百貨美食街',];
$areas = json_decode(file_get_contents(__DIR__ . '/city.json'), true);
$rawPath = $basePath . '/raw';

$pool = [];
$count = 0;

foreach($areas AS $city => $zones) {
    $cityPath = $rawPath . '/' . $city;
    if(!file_exists($cityPath)) {
        mkdir($cityPath, 0777, true);
    }
    foreach($zones AS $zone) {
        foreach($categories AS $category) {
            $zoneUrl = 'https://foodlover.tw/goodfood/query/shop/v2?shop=&zone=&';
            $zoneUrl .= 'category=' . urlencode($category) . '&';
            $zoneUrl .= 'city=' . urlencode($city) . '&';
            $zoneUrl .= 'area=' . urlencode($zone);
            $category = str_replace('/', '_', $category);
            $rawFile = $cityPath . '/' . $zone . '-' . $category . '.json';
            $json = json_decode(file_get_contents($zoneUrl), true);
            file_put_contents($rawFile, json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            foreach($json AS $item) {
                ++$count;
                $keys = array_keys($item);
                foreach($keys AS $key) {
                    if(false !== strpos($key, 'pay')) {
                        if(is_array($item[$key])) {
                            foreach($item[$key] AS $pay) {
                                if(!isset($pool[$pay])) {
                                    $pool[$pay] = 0;
                                }
                                ++$pool[$pay];
                            }
                        }
                    }
                }
            }
        }
    }
}
echo $count;