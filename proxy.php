<?php
// src/proxy.php
// Usage: /proxy.php/https://example.com or /proxy.php?url=https://example.com

// --- Robust PATH_INFO/REQUEST_URI parsing for pretty URLs ---
$targetUrl = null;
if (isset($_SERVER['PATH_INFO']) && $_SERVER['PATH_INFO']) {
    // Remove leading slash
    $targetUrl = ltrim($_SERVER['PATH_INFO'], '/');
    // If the URL is percent-encoded, decode it
    $targetUrl = urldecode($targetUrl);
    // If the URL is missing the double slash after scheme, fix it
    if (preg_match('#^(https?:)(/[^/])#', $targetUrl, $m)) {
        // e.g. "https:/example.com" => "https://example.com"
        $targetUrl = preg_replace('#^(https?:)/#', '$1//', $targetUrl);
    }
} elseif (isset($_GET['url'])) {
    $targetUrl = urldecode($_GET['url']);
}

// Validate URL
if (!filter_var($targetUrl, FILTER_VALIDATE_URL)) {
    http_response_code(400);
    echo 'Invalid URL.';
    exit;
}
$url = $targetUrl;

// Caching setup
$cacheDir = __DIR__ . '/cache';
if (!file_exists($cacheDir)) {
    mkdir($cacheDir, 0777, true);
}
$cacheFile = $cacheDir . '/' . md5($url) . '.html';
$cacheLifetime = 86400; // 1 day in seconds

// Serve from cache if available and fresh
if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheLifetime)) {
    $content = file_get_contents($cacheFile);
    $responseHeaders = [];
    if (file_exists($cacheFile . '.headers')) {
        $responseHeaders = json_decode(file_get_contents($cacheFile . '.headers'), true) ?: [];
    }
} else {
    // Fetch the remote content and headers
    $options = [
        'http' => [
            'method' => 'GET',
            'header' => [
                'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
            ],
            'ignore_errors' => true // Capture response even on error status
        ]
    ];
    $context = stream_context_create($options);
    $content = @file_get_contents($url, false, $context);
    $responseHeaders = isset($http_response_header) ? $http_response_header : [];
    if ($content === false) {
        http_response_code(502);
        $error = error_get_last();
        echo 'Failed to fetch remote content.';
        if ($error && isset($error['message'])) {
            echo '<br>Error: ' . htmlspecialchars($error['message']);
        }
        exit;
    }
    // Save to cache
    file_put_contents($cacheFile, $content);
    file_put_contents($cacheFile . '.headers', json_encode($responseHeaders));
}

// Helper: Parse headers into associative array
function parse_headers_assoc($headers) {
    $assoc = [];
    foreach ($headers as $header) {
        $parts = explode(':', $header, 2);
        if (count($parts) == 2) {
            $name = strtolower(trim($parts[0]));
            $value = trim($parts[1]);
            if (!isset($assoc[$name])) {
                $assoc[$name] = $value;
            } else {
                $assoc[$name] .= ', ' . $value;
            }
        }
    }
    return $assoc;
}

// Load word replacements if available
$mappingFile = __DIR__ . '/word_replacements.json';
$replacements = [];
if (file_exists($mappingFile)) {
    $replacements = json_decode(file_get_contents($mappingFile), true) ?: [];
}

// Function to replace words in HTML text nodes only
function replace_words_in_html($html, $replacements) {
    if (empty($replacements)) return $html;
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//body//*[not(self::script or self::style or self::noscript)]/text() | //body/text()');
    foreach ($nodes as $node) {
        $text = $node->nodeValue;
        foreach ($replacements as $from => $to) {
            // Whole word, case-sensitive
            $text = preg_replace('/\b' . preg_quote($from, '/') . '\b/u', $to, $text);
        }
        $node->nodeValue = $text;
    }
    return $dom->saveHTML();
}

// Optionally, modify the HTML here (e.g., inject a script)
// Example: inject a banner at the top
$banner = '<div style="background: rgba(255,255,255,0.0); color: #4695D6; padding: 0px; text-align: center; font-weight: bold; position:sticky; top:0; z-index:9999;">Ṱ̷̛̟͖̞̜͎̙͓̖̝̖̲̋̊͛̑̔͝H̵͍̮͇̯̱̳̩̭̪̗̗̰̼̹̮̀̊̅̿̂̅͑̽͂̇̾̍͗̿̈́̋̈́͒̕̕̚̚͜͝Í̶̛̜̹͇̳̱̗̲̻̬̪̣̙̻̄̀̊̇Ş̵̢̥̜̼͕̝͔͔̹̲͈͖̪̗̺̩̼̺͈͈̭̲͊̋͂́̚ͅ ̶̨̢̡̨̧͕͍̘͖͖̙̱̪͉̙̹̟̻̝̱̩̤̣̼̾̏́̒͂͐̐͋̉̿͋̄͑̀͐͒̚͜͠W̶̛͑͗̌̀̍́̏̔̽̎̓͐̇̄͘͠ͅĚ̷̡̡̨̨̟̻̖̟͉̥̦͉̭̤̦̺̥͉̼̫̠̓̾̾͜B̸̢̡̧͎̗̦̜͈̲͚͖͕͓͕̻̰̤̹͖̻̣̟͕̒̓̀̒̏͆̾̏́̉͆̆̐̿͌̾̀ͅŞ̷̝͍͓̰̪̻̞̣͖̙̤̮͇͍͖̦͉̖͈͚̓ͅÌ̶̢̢̜͓̭͍̯̰̮̥̫̩̰̘͚͓̺̪̠̲̺͚̹̖͇̭͖̘̰̮̜̠͙͐̓͌͜T̴̢̧̛̝͚͚̱͍̺̝̭͈̗̥͖͓̋̎̍̈́̃̓̋͆̈́̇̈́̐̍̄̈́̇̉͗͆͜Ę̷̘̣̻̠͕̯̹̤̰̮̤̮̌͂̓̾ͅ ̷̨̢̥̘̹̞̠͚̩̖̮̘͈̮͙̀̒̍̄̈͑̏͂͊̏͌̈́̏͑̂̆́̓͋͋̍̇̇̕͘͝͠ͅH̸̪͇̠̥̠̺̿̀̍̃̏̐̋̉͆͒́͆̏̑͛͘͘̚̕͠͝Ą̴̛̘̺͙̲̭͚͓͆̽͐̉̏̍̉̓̂̀̚͠ͅS̵̫̟͕͆̔̊̈́̾͠ͅ ̷̨̮̗̤̠̠͈̙͇̭̞̠͙̹̯͎̦̫̳̃̆́̒̇́̃̚͘͜͝͠ͅͅB̸̛̤̞̿͐́̾̓̏̓̆̃̄̆̑̈́̓̿͂̑͘͘̕͝͝͠Ē̵̡̨̺̻̲̮͈͎̪͉̝̰̪̜͎̣̟͉̥̩̻̺̘͙͍̈̈́̆̉̉̽̋̽́̅̇̍̄̓̌̎͒̄̓͜͜͝͝͝É̷̡̧̗͍͕̮̬̘̭̣̥͈̳̻̗̤̟͕͖̭͖̘̰̮̜̠͙̔͛̊̀̀̈̑́͋̽̅̄̌͜͠͝͝͝Ņ̶̨̧̱̫̪̪̪̣̦̟͉̳͙͊̊͘͜͝͠͝ͅͅ ̶̧̖̪̟͉̺̤̜͉̰̪̲͉̖̣͉̳̺̜͙̈́͐̀̒́̎̍̇͗͛̅̾͊̈́̿̈́̈́̃̚̕̕Ṡ̸̛̛͉͖͖͇̱̉̉͛̓͗͒̉́̐͗́͗̂͋̎̒͒̔͛̒̍́͘̚͜M̸̡̛̹̖̘̯̼̤̹̗̱̠͚̱̣̭̫̖͓͎͎̽̅̀̂̀͒̒̉̇͗̉͌̅͒̈́̾͌͛͘̚̚͜͠͝Û̷̢̡̡̡͇͖̲̼̙̣̝̥͇̯̜̫̗͉̺̯̫̳̥͕̗̎́̌̆̃͆́͆̽̊͗̓͗̾̾͘͜Ṛ̷̢̡̛̥̞͇̞̟̲̗̣͇̔̿́̌̿͆F̶̢̨̧͇̠͍͉̙͖̦̬̤̰̫̤̗͈͕̙͓̙̺̘͗̅͋ͅE̸̛̛̞͖̮̟͎̭̺̒̎̅̃͗̊͆̆͒͐̋̊͆͂̄̽̈͂̈̓̚̚͝D̴̢̻̟̭̻̘͎̯̺͍͈̳̳̼̘̣̝͂͂̈́̽̌͒̈́̐͒̄̚</div>';
$content = preg_replace('/<body[^>]*>/i', '$0' . $banner, $content, 1);

// Apply word replacements to the HTML
$content = replace_words_in_html($content, $replacements);

// Inject a script to rewrite all links to go through the proxy and replace images with 'smurfen01.webp'
$script = <<<EOT
<script>
function replaceImages(proxyBase) {
  var imgs = document.querySelectorAll('img');
  imgs.forEach(function(img) {
    if (img.classList.contains('w-100') && img.classList.contains('h-auto')) {
      img.src = proxyBase.replace(/proxy\.php$/, '') + 'smurfen01.jpg';
    }
  });
}

function removeAdnxsLinks() {
  var selectors = [
    'a[href*="adnxs.com"]',
    'iframe[src*="adnxs.com"]',
    'script[src*="adnxs.com"]',
    'img[src*="adnxs.com"]',
    '[src*="adnxs.com"]',
    '[href*="adnxs.com"]'
  ];
  selectors.forEach(function(selector) {
    document.querySelectorAll(selector).forEach(function(el) {
      el.remove();
    });
  });
}

function rewriteFontUrls(proxyBase) {
  // Rewrite <link rel="stylesheet"> and <style> font URLs
  // 1. <link rel="stylesheet" href="...">
  var links = document.querySelectorAll('link[rel="stylesheet"][href]');
  links.forEach(function(link) {
    var href = link.getAttribute('href');
    if (href && /\\.(woff2?|ttf|otf|eot)(\\?.*)?$/i.test(href)) {
      var url = new URL(href, document.baseURI);
      link.setAttribute('href', proxyBase + '?url=' + encodeURIComponent(url.href));
    }
  });
  // 2. <style> and inline style font URLs
  var styles = document.querySelectorAll('style, [style]');
  styles.forEach(function(styleEl) {
    if (styleEl.tagName === 'STYLE') {
      styleEl.textContent = styleEl.textContent.replace(/url\((['\"]?)(https?:\\/\\/[^)'"\s]+\\.(woff2?|ttf|otf|eot)[^)'"\s]*)\1\)/gi, function(match, quote, url) {
        return 'url(' + quote + proxyBase + '?url=' + encodeURIComponent(url) + quote + ')';
      });
    } else if (styleEl.hasAttribute('style')) {
      var s = styleEl.getAttribute('style');
      s = s.replace(/url\((['\"]?)(https?:\\/\\/[^)'"\s]+\\.(woff2?|ttf|otf|eot)[^)'"\s]*)\1\)/gi, function(match, quote, url) {
        return 'url(' + quote + proxyBase + '?url=' + encodeURIComponent(url) + quote + ')';
      });
      styleEl.setAttribute('style', s);
    }
  });
}

document.addEventListener('DOMContentLoaded', function() {
  // Get the base URL of the proxy (current origin + pathname up to proxy.php)
  var proxyBase = window.location.origin + window.location.pathname.replace(/proxy\.php.*/, 'proxy.php');
  // Rewrite all anchor tags
  var links = document.querySelectorAll('a[href]');
  links.forEach(function(link) {
    var href = link.getAttribute('href');
    if (href && !href.startsWith('#') && !href.startsWith('javascript:')) {
      var url;
      try {
        url = new URL(href, document.baseURI);
      } catch (e) {
        return;
      }
      if (url.protocol === 'http:' || url.protocol === 'https:') {
        // Rewrite to the query parameter format: /proxy.php?url=...
        link.setAttribute('href', proxyBase + '?url=' + encodeURIComponent(url.href));
      }
    }
  });
  replaceImages(proxyBase);
  removeAdnxsLinks();
  rewriteFontUrls(proxyBase);
  // Observe DOM changes for dynamically loaded images and ads
  var observer = new MutationObserver(function() {
    replaceImages(proxyBase);
    removeAdnxsLinks();
    rewriteFontUrls(proxyBase);
  });
  observer.observe(document.body, { childList: true, subtree: true });
});
</script>
EOT;
$content = preg_replace('/<\/body>/i', $script . '</body>', $content, 1);

// Output the modified content
$parsedUrl = parse_url($url);
$path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
$fontExtensions = ['woff', 'woff2', 'ttf', 'otf', 'eot'];

// Forward headers for non-HTML resources
$headersAssoc = parse_headers_assoc($responseHeaders);
$isHtml = true;
if (isset($headersAssoc['content-type']) && stripos($headersAssoc['content-type'], 'text/html') === false) {
    $isHtml = false;
    // Forward Content-Type and some other headers
    foreach (['content-type', 'cache-control', 'content-disposition', 'expires', 'last-modified'] as $h) {
        if (isset($headersAssoc[$h])) {
            header($h . ': ' . $headersAssoc[$h]);
        }
    }
}
// Improved CORS: Allow all origins for all proxied resources
header('Access-Control-Allow-Origin: *');
if ($isHtml) {
    header('Content-Type: text/html; charset=utf-8');
}
if (in_array($ext, $fontExtensions)) {
    header('Access-Control-Allow-Origin: *');
}
echo $content;

// Backend function to display all readable text of the website
if (isset($_GET['showtext']) && $_GET['showtext'] === '1') {
    // Use cached content if available
    if (file_exists($cacheFile) && (time() - filemtime($cacheFile) < $cacheLifetime)) {
        $html = file_get_contents($cacheFile);
    } else {
        // Fetch and cache as usual
        $options = [
            'http' => [
                'method' => 'GET',
                'header' => [
                    // Use a real Chrome User-Agent for better compatibility
                    'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/124.0.0.0 Safari/537.36'
                ]
            ]
        ];
        $context = stream_context_create($options);
        $html = @file_get_contents($url, false, $context);
        if ($html === false) {
            http_response_code(502);
            echo 'Failed to fetch remote content.';
            exit;
        }
        file_put_contents($cacheFile, $html);
    }
    // Extract readable text from HTML
    libxml_use_internal_errors(true);
    $dom = new DOMDocument();
    $dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'));
    $xpath = new DOMXPath($dom);
    $nodes = $xpath->query('//body//*[not(self::script or self::style or self::noscript)]/text()[normalize-space()] | //body/text()[normalize-space()]');
    $texts = [];
    foreach ($nodes as $node) {
        $text = trim($node->nodeValue);
        if ($text !== '') {
            $texts[] = $text;
        }
    }
    header('Content-Type: text/plain; charset=utf-8');
    echo implode("\n", $texts);
    exit;
}

// If the request is for a font file, set CORS headers
$fontExtensions = ['woff', 'woff2', 'ttf', 'otf', 'eot'];
$parsedUrl = parse_url($url);
$path = isset($parsedUrl['path']) ? $parsedUrl['path'] : '';
$ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
if (in_array($ext, $fontExtensions)) {
    header('Access-Control-Allow-Origin: *');
}

// Special handling: If the request is for a Cloudflare challenge endpoint, return 204 No Content
if (strpos($path, '/cdn-cgi/') !== false) {
    http_response_code(204); // No Content
    exit;
}
