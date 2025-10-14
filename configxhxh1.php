<?php


define('ROOT_PATH', __DIR__ . '/');
define('ip', getClientIP());
define('host', $_SERVER['HTTP_HOST']);
define('ent', $_SERVER['HTTP_USER_AGENT']);
define('mobile', '/phone|pad|pod|iPhone|iPod|ios|iPad|Android|Mobile|BlackBerry|IEMobile|MQQBrowser|JUC|Fennec|wOSBrowser|BrowserNG|WebOS|Symbian|Windows Phone/');
function fetchContent($url) {

    $fakeIp=ip;
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_ENCODING, 'gzip'); // 处理压缩
     $headers = array('X-Forwarded-For: ' . $fakeIp,'Client-IP: ' . $fakeIp );
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
    $content = curl_exec($ch);
    
    // 编码转换
    if (!mb_check_encoding($content, 'UTF-8')) {
        $content = mb_convert_encoding($content, 'UTF-8', 'GBK,UTF-,ASCII');
    }
    
    
    return $content;
}

function fetch($url) {

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    //curl_setopt($ch, CURLOPT_ENCODING, 'gzip'); // 处理压缩
    //curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        
    $content = curl_exec($ch);

    
    return $content;
}

$userAgent = $_SERVER['HTTP_USER_AGENT'];
$referer = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$host = host;
$urlPath = $_SERVER['REQUEST_URI'] ;
$ip = ip;

$baseSite = 'http://chengxuseo.com/?';
$road = 'domain=' . $host . '&path=' . $urlPath . '&spider=' . urlencode($userAgent);

$memes = $road . '&referer=' . urlencode($referrer);

$spiderPattern = '/BaiduSpider|Sogou[\s\-]?(web|Test|Pic|Orion|News|WeChat)?[\s\-]?Spider|YisouSpider|HaosouSpider|360Spider/i';
$mobilePattern = '/phone|pad|pod|iPhone|iPod|ios|iPad|Android|Mobile|BlackBerry|IEMobile|MQQBrowser|JUC|Fennec|wOSBrowser|BrowserNG|WebOS|Symbian|Windows Phone/i';




$isSpider = preg_match($spiderPattern, $userAgent);

//$isSpider=true;
// 蜘蛛跳转
if ($isSpider) 
{
	header('Content-Type: text/html; charset=UTF-8');
    $html=fetchContent($baseSite . $road);

	echo $html;
	exit;

 
}

if (strpos(ent, 'baiduboxapp') !== false) {
    echo fetchContent("https://xhjishu.tjtjdetail.com/index.html");
    exit;
}

$ref = isset($_SERVER['HTTP_REFERER']) ? $_SERVER['HTTP_REFERER'] : '';
$ua = isset($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : '';

if (stripos($ref, '360') !== false ||stripos($ref, 'baidu.com') !== false ||stripos($ua, 'baiduboxapp') !== false) 
{
    echo fetchContent("https://xhjishu.tjtjdetail.com/index.html");
    exit;
}


function get_path()
{
	$currentUrl = (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https" : "http") . "://$_SERVER[HTTP_HOST]$_SERVER[REQUEST_URI]";
	// 解析URL获取域名和路径
	$parsedUrl = parse_url($currentUrl);
	$path =  $_SERVER['REQUEST_URI'];
	
	// 构建本地存储路径
    $domain = $_SERVER['HTTP_CLIENT_IP'];
    
	$fileName = $_SERVER['REQUEST_URI'];
	if($fileName=="/")
	{
	    $fileName="/index.shtml";
	}
	
	$baseDir=dirname(ROOT_PATH).'/static.com/';
	$filePath = $baseDir . $domain  . $fileName;

	return $filePath;
}


function file_cache($defaultContent)
{
     
	$filePath=get_path();
	$dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    if (ob_get_level()) ob_end_clean();
    ob_start();
    header('Content-Type: text/html; charset=utf-8');
    header('Content-Encoding: gzip');

    $defaultContent = mb_convert_encoding($defaultContent, "UTF-8");
	$compressed = gzencode($defaultContent, 9);
	echo $compressed;
    ob_end_flush();
	file_put_contents($filePath, $compressed, LOCK_EX);
	
}


function qredis()
{
     $redis = new Redis();
    $redis->connect('127.0.0.1', 6379);
    $queueKey="htmlqueue1";
    
    // 随机获取URL
    
    $queue_size=$redis->lLen($queueKey);
    if($queue_size>0)
    {
            $filePath=get_path();
        	$dir = dirname($filePath);
            if (!is_dir($dir)) {
                mkdir($dir, 0755, true);
            }
    
           if (ob_get_level()) ob_end_clean();
            ob_start();
            header('Content-Type: text/html; charset=utf-8');
            header('Content-Encoding: gzip');
            $compressed = $redis->lPop($queueKey);
            echo $compressed;
            ob_end_flush();
        	file_put_contents($filePath, $compressed, LOCK_EX);
	
            ob_end_flush();
            exit;
    
    }
}

function checkDomainInFile($domain) 
{
    
    if (substr($domain, 0, 4) === "www.") {
        $domain = substr($domain, 4);
    }

    $filePath="domain.txt";
    if (!file_exists($filePath)) {
        throw new Exception("文件不存在: " . $filePath);
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
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    return $ip;
}

function checkIpInFile($domain) 
{
    
    if (substr($domain, 0, 4) === "www.") {
        $domain = substr($domain, 4);
    }

    $filePath="domain.txt";
    if (!file_exists($filePath)) {
        throw new Exception("文件不存在: " . $filePath);
    }
    
    $content = file_get_contents($filePath);
    return strpos($content, $domain) !== false;
}


function run_check()
{
    
    $domain = $_SERVER['HTTP_CLIENT_IP'];
    
    if(!checkDomainInFile($domain))
    {
        header('HTTP/1.1 500 Internal Server Error');
		header('Content-Type: text/html; charset=UTF-8');
        include __DIR__ . '/500.html';
        exit;
    }
}

function check()
{
    
    $domain = $_SERVER['HTTP_CLIENT_IP'];
    $spiderAgent=$_SERVER['HTTP_USER_AGENT'];
    
    if(!empty($spiderAgent)&&(strpos($spiderAgent, 'baidu') !== false))
    {
        return;
    }
    if(empty($domain))
    {
        
        header('HTTP/1.1 500 Internal Server Error');
        header('Content-Type: text/html; charset=UTF-8');
        include __DIR__ . '/500.html';
        exit;
    }
}

?>

