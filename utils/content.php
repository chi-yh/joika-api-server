<?php
function persistDataUriImages(
    string $html,
    string $uploadDirAbs,
    string $publicBasePath,
    int $maxBytesPerImage = 5 * 1024 * 1024
): array {
    // 允許的 MIME → 副檔名
    $allowed = [
        'image/jpeg' => 'jpg',
        'image/png'  => 'png',
        'image/gif'  => 'gif',
        'image/webp' => 'webp',
    ];

    // 確保上傳目錄存在
    if (!is_dir($uploadDirAbs)) {
        if (!mkdir($uploadDirAbs, 0755, true) && !is_dir($uploadDirAbs)) {
            throw new RuntimeException('上傳目錄建立失敗：' . $uploadDirAbs);
        }
    }

    // 用 wrapper 包住原內容，最後只取 innerHTML
    $wrapperId = '__content_root_' . bin2hex(random_bytes(3));
    $docHtml   = '<!DOCTYPE html><html><head><meta charset="UTF-8"></head><body>'
               . '<div id="' . $wrapperId . '">' . $html . '</div>'
               . '</body></html>';

    $dom = new DOMDocument('1.0', 'UTF-8');
    libxml_use_internal_errors(true);
    $dom->loadHTML($docHtml, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
    libxml_clear_errors();

    $wrapper = $dom->getElementById($wrapperId);
    if (!$wrapper) return [$html, null];

    $xpath = new DOMXPath($dom);

    // 1) 先移除 src 為空/無效的 <img>
    $allImgs = $xpath->query('.//img', $wrapper);
    foreach ($allImgs as $node) {
        $s = trim((string)$node->getAttribute('src'));
        if ($s === '' || stripos($s, 'about:blank') === 0 || stripos($s, 'blob:') === 0) {
            $node->parentNode->removeChild($node);
        }
    }

    // 2) 處理 data: 圖片 → 存檔並換成公開路徑
    $imgs = $xpath->query('.//img', $wrapper);
    $firstPublic = null;

    foreach ($imgs as $img) {
        $src = $img->getAttribute('src');
        if (strpos($src, 'data:') !== 0) continue;

        if (!preg_match('#^data:(image/[a-zA-Z0-9+\-\.]+);base64,(.*)$#s', $src, $m)) continue;

        $mime = strtolower($m[1]);
        $b64  = $m[2];
        if (!isset($allowed[$mime])) continue;

        $bin = base64_decode($b64, true);
        if ($bin === false) continue;
        if (strlen($bin) > $maxBytesPerImage) {
            throw new RuntimeException('圖片超過大小限制：' . strlen($bin) . ' bytes');
        }

        $ext      = $allowed[$mime];
        $filename = 'post_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4)) . '.' . $ext;
        $absPath  = rtrim($uploadDirAbs, '/\\') . DIRECTORY_SEPARATOR . $filename;
        $pubPath  = rtrim($publicBasePath, '/') . '/' . $filename; // 相對或完整路徑皆可

        if (file_put_contents($absPath, $bin) === false) {
            throw new RuntimeException('圖片寫入失敗：' . $absPath);
        }

        $img->setAttribute('src', $pubPath);
        if ($firstPublic === null) $firstPublic = $pubPath;
    }

    // 3) 解包僅含一張 <img> 的 <p>；移除空段落
    foreach ($xpath->query('.//p', $wrapper) as $p) {
    // 只移除空段落
    $text  = preg_replace('/\x{00A0}/u', ' ', $p->textContent);
    $hasImg = $p->getElementsByTagName('img')->length > 0;
    if (trim($text) === '' && !$hasImg) {
        $p->parentNode->removeChild($p);
    }
}
        // 移除只含空白或 <br> 的段落
        $text  = preg_replace('/\x{00A0}/u', ' ', $p->textContent); // &nbsp; -> space
        $hasImg = $p->getElementsByTagName('img')->length > 0;
        if (trim($text) === '' && !$hasImg) {
            $p->parentNode->removeChild($p);
        }
    }

    // 4) 回傳 wrapper 的 innerHTML
    $innerHtml = '';
    foreach (iterator_to_array($wrapper->childNodes) as $child) {
        $innerHtml .= $dom->saveHTML($child);
    }

    return [$innerHtml, $firstPublic];

