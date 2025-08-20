<?php
session_start();

// First, check if this is a webhook request (not viewing logs)
$isWebhookRequest = !isset($_GET['view_logs']) && !isset($_POST['action']);

// If it's a webhook request, handle it immediately
if ($isWebhookRequest) {
    // Get the current date to create a log file
    $date = date('Y-m-d');
    $logDir = __DIR__ . '/log';
    $logFile = "$logDir/$date.json";

    // Ensure the log directory exists
    if (!is_dir($logDir)) {
        mkdir($logDir, 0777, true);
    }

    // Get the request data - capture all payload types
    $headers = [];
    if (function_exists('getallheaders')) {
        $headers = array_filter(getallheaders(), function ($key) {
            return stripos($key, 'wh_') === 0;
        }, ARRAY_FILTER_USE_KEY);
    } else {
        // Fallback for servers without getallheaders()
        foreach ($_SERVER as $key => $value) {
            if (strpos($key, 'HTTP_') === 0 && stripos(substr($key, 5), 'WH_') === 0) {
                $headerName = str_replace('_', '-', substr($key, 5));
                $headers[$headerName] = $value;
            }
        }
    }

    $cookies = array_filter($_COOKIE, function ($key) {
        return stripos($key, 'wh_') === 0;
    }, ARRAY_FILTER_USE_KEY);

    // Comprehensive payload capture
    $rawInput = file_get_contents('php://input');
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    // Parse different payload types
    $payload = [
        'query_params' => $_GET,
        'form_data' => $_POST,
        'files' => $_FILES,
        'raw_body' => $rawInput,
        'json_data' => null,
        'xml_data' => null,
        'multipart_data' => [],
        'url_encoded' => [],
        'binary_data' => null,
        'text_data' => null
    ];

    // Parse JSON payload
    if (strpos($contentType, 'application/json') !== false && $rawInput) {
        $payload['json_data'] = json_decode($rawInput, true);
    }

    // Parse XML payload
    if (strpos($contentType, 'application/xml') !== false || strpos($contentType, 'text/xml') !== false) {
        if ($rawInput) {
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($rawInput);
            if ($xml !== false) {
                $payload['xml_data'] = json_decode(json_encode($xml), true);
            }
        }
    }

    // Parse URL-encoded data
    if (strpos($contentType, 'application/x-www-form-urlencoded') !== false && $rawInput) {
        parse_str($rawInput, $payload['url_encoded']);
    }

    // Parse multipart form data (already handled by PHP in $_POST and $_FILES)
    if (strpos($contentType, 'multipart/form-data') !== false) {
        $payload['multipart_data'] = array_merge($_POST, $_FILES);
    }

    // Handle plain text
    if (strpos($contentType, 'text/plain') !== false && $rawInput) {
        $payload['text_data'] = $rawInput;
    }

    // Handle binary data
    if (strpos($contentType, 'application/octet-stream') !== false && $rawInput) {
        $payload['binary_data'] = [
            'size' => strlen($rawInput),
            'content_type' => $contentType,
            'base64' => base64_encode($rawInput)
        ];
    }

    $requestData = [
        'method' => $_SERVER['REQUEST_METHOD'],
        'payload' => $payload,
        'headers' => $headers,
        'cookies' => $cookies,
        'created_at' => time() * 1000,
        'path' => $_SERVER['REQUEST_URI'],
        'content_type' => $contentType,
        'content_length' => $_SERVER['CONTENT_LENGTH'] ?? 0,
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? '',
        'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? ''
    ];

    // Log the request data with error handling
    try {
        $jsonData = json_encode($requestData, JSON_PRETTY_PRINT);
        if ($jsonData === false) {
            error_log("JSON encoding failed for webhook data");
            $jsonData = json_encode(['error' => 'JSON encoding failed', 'timestamp' => time()]);
        }
        
        if (file_exists($logFile)) {
            $existingData = json_decode(file_get_contents($logFile), true);
            if (!is_array($existingData)) {
                $existingData = [];
            }
            $existingData[] = $requestData;
            $result = file_put_contents($logFile, json_encode($existingData, JSON_PRETTY_PRINT));
        } else {
            $result = file_put_contents($logFile, json_encode([$requestData], JSON_PRETTY_PRINT));
        }
        
        if ($result === false) {
            error_log("Failed to write webhook log to: $logFile");
        }
    } catch (Exception $e) {
        error_log("Webhook logging error: " . $e->getMessage());
    }

    // Debug: Log that we reached this point (optional - remove after testing)
    error_log("Webhook received: " . $_SERVER['REQUEST_METHOD'] . " " . $_SERVER['REQUEST_URI']);

    // Return a 200 OK response
    http_response_code(200);
    echo 'OK';
    exit;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['username'], $_POST['password'])) {
    if ($_POST['username'] === 'admin' && $_POST['password'] === 'xyz') {
        $_SESSION['authenticated'] = true;
        header('Location: index.php?view_logs');
        exit;
    } else {
        $error = 'Invalid username or password';
    }
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// Tailwind CSS inclusion
$tailwindCSS = "https://cdn.jsdelivr.net/npm/tailwindcss@2.2.19/dist/tailwind.min.css";

// Show login form if not authenticated
if (!isset($_SESSION['authenticated']) || !$_SESSION['authenticated']) {
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Login</title>
        <link href="<?php echo $tailwindCSS; ?>" rel="stylesheet">
        <style>
            body {
                background-color: #000;
                color: #00ffdd;
            }

            .neon {
                text-shadow: 0 0 5px #00ffdd, 0 0 10px #00ffdd, 0 0 20px #00ffdd;
            }
        </style>
    </head>

    <body class="flex items-center justify-center h-screen">
        <div class="bg-gray-900 p-8 rounded-lg shadow-lg w-96">
            <h1 class="text-3xl font-bold neon text-center mb-4">Login</h1>
            <?php if (isset($error))
                echo "<p class='text-red-500 text-center'>$error</p>"; ?>
            <form method="POST" action="" class="space-y-4">
                <div>
                    <label for="username" class="block text-sm font-medium">Username:</label>
                    <input type="text" id="username" name="username" required
                        class="w-full p-2 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-teal-400">
                </div>
                <div>
                    <label for="password" class="block text-sm font-medium">Password:</label>
                    <input type="password" id="password" name="password" required
                        class="w-full p-2 rounded bg-gray-800 text-white focus:outline-none focus:ring-2 focus:ring-teal-400">
                </div>
                <button type="submit"
                    class="w-full py-2 bg-teal-500 text-black font-bold rounded hover:bg-teal-400">Login</button>
            </form>
        </div>
    </body>

    </html>
    <?php
    exit;
}

// Handle AJAX requests
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    $logDir = __DIR__ . '/log';

    switch ($_POST['action']) {
        case 'delete_file':
            $filename = basename($_POST['filename']);
            $filepath = "$logDir/$filename";
            if (file_exists($filepath)) {
                unlink($filepath);
                echo json_encode(['success' => true]);
            } else {
                echo json_encode(['success' => false, 'error' => 'File not found']);
            }
            exit;

        case 'delete_request':
            $filename = basename($_POST['filename']);
            $index = (int) $_POST['index'];
            $filepath = "$logDir/$filename";
            if (file_exists($filepath)) {
                $data = json_decode(file_get_contents($filepath), true);
                if (isset($data[$index])) {
                    unset($data[$index]);
                    $data = array_values($data); // Reindex array
                    file_put_contents($filepath, json_encode($data, JSON_PRETTY_PRINT));
                    echo json_encode(['success' => true]);
                } else {
                    echo json_encode(['success' => false, 'error' => 'Request not found']);
                }
            } else {
                echo json_encode(['success' => false, 'error' => 'File not found']);
            }
            exit;

        case 'get_requests':
            $filename = basename($_POST['filename']);
            $filepath = "$logDir/$filename";
            if (file_exists($filepath)) {
                $data = json_decode(file_get_contents($filepath), true);
                echo json_encode(['success' => true, 'data' => $data ?: []]);
            } else {
                echo json_encode(['success' => false, 'error' => 'File not found']);
            }
            exit;
    }
    exit;
}

// Helper functions for statistics
function getMethodStats($data)
{
    if (!is_array($data))
        return [];
    $methods = array_count_values(array_column($data, 'method'));
    return $methods;
}

function getContentTypeStats($data)
{
    if (!is_array($data))
        return [];
    $types = array_filter(array_column($data, 'content_type'));
    $typeStats = [];
    foreach ($types as $type) {
        $mainType = explode(';', $type)[0];
        $typeStats[$mainType] = ($typeStats[$mainType] ?? 0) + 1;
    }
    return $typeStats;
}

// Admin panel to view logs
if ($_SERVER['REQUEST_METHOD'] === 'GET' && isset($_GET['view_logs'])) {
    $logDir = __DIR__ . '/log';
    $logFiles = [];
    foreach (glob("$logDir/*.json") as $file) {
        $filename = basename($file);
        $filesize = filesize($file);
        $filemtime = filemtime($file);
        $fileContent = json_decode(file_get_contents($file), true);
        $requestCount = is_array($fileContent) ? count($fileContent) : 0;

        $logFiles[] = [
            'filename' => $filename,
            'size' => $filesize,
            'modified' => $filemtime,
            'requests' => $requestCount,
            'methods' => getMethodStats($fileContent),
            'content_types' => getContentTypeStats($fileContent),
            'last_request' => $requestCount > 0 ? max(array_column($fileContent, 'created_at')) : 0
        ];
    }
    ?>
    <!DOCTYPE html>
    <html lang="en">

    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>Webhook Testing Dashboard</title>
        <link href="<?php echo $tailwindCSS; ?>" rel="stylesheet">
        <script src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js" defer></script>
        <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
        <style>
            body {
                background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 50%, #0d1421 100%);
                color: #00ffdd;
                font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Courier New', monospace;
            }

            .glass {
                background: rgba(0, 255, 221, 0.03);
                backdrop-filter: blur(15px);
                border: 1px solid rgba(0, 255, 221, 0.1);
                box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            }

            .glass-navbar {
                background: rgba(0, 255, 221, 0.05);
                backdrop-filter: blur(20px);
                border-bottom: 1px solid rgba(0, 255, 221, 0.15);
                box-shadow: 0 4px 20px rgba(0, 0, 0, 0.4);
            }

            .glass-input {
                background: rgba(0, 0, 0, 0.4);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(0, 255, 221, 0.2);
                color: #00ffdd;
            }

            .glass-input:focus {
                border-color: rgba(0, 255, 221, 0.5);
                box-shadow: 0 0 20px rgba(0, 255, 221, 0.1);
            }

            .glass-input::placeholder {
                color: rgba(0, 255, 221, 0.4);
            }

            .glass-input option {
                background: rgba(0, 0, 0, 0.9);
                color: #00ffdd;
                border: none;
            }

            .glass-input select {
                background: rgba(0, 0, 0, 0.4);
                color: #00ffdd;
            }

            .neon-glow {
                box-shadow: 0 0 20px rgba(0, 255, 221, 0.3);
                border: 1px solid rgba(0, 255, 221, 0.5);
            }

            .method-get {
                color: #00ffdd;
                background: rgba(0, 255, 221, 0.1);
                font-weight: bold;
            }

            .method-post {
                color: #00d4ff;
                background: rgba(0, 212, 255, 0.1);
                font-weight: bold;
            }

            .method-put {
                color: #ffaa00;
                background: rgba(255, 170, 0, 0.1);
                font-weight: bold;
            }

            .method-delete {
                color: #ff4444;
                background: rgba(255, 68, 68, 0.1);
                font-weight: bold;
            }

            .method-patch {
                color: #dd00ff;
                background: rgba(221, 0, 255, 0.1);
                font-weight: bold;
            }

            .status-indicator {
                width: 8px;
                height: 8px;
                border-radius: 50%;
                display: inline-block;
                margin-right: 8px;
            }

            .status-success {
                background-color: #00ffdd;
                box-shadow: 0 0 10px rgba(0, 255, 221, 0.5);
            }

            .status-warning {
                background-color: #ffaa00;
                box-shadow: 0 0 10px rgba(255, 170, 0, 0.5);
            }

            .status-error {
                background-color: #ff4444;
                box-shadow: 0 0 10px rgba(255, 68, 68, 0.5);
            }

            .card-hover:hover {
                transform: translateY(-2px);
                transition: all 0.3s ease;
                box-shadow: 0 10px 40px rgba(0, 255, 221, 0.1);
            }

            .search-highlight {
                background-color: rgba(0, 255, 221, 0.3);
                padding: 1px 4px;
                border-radius: 2px;
            }

            .tree-container {
                display: flex;
                height: calc(100vh - 200px);
                gap: 1rem;
            }

            .file-browser {
                width: 300px;
                min-width: 300px;
                background: rgba(0, 255, 221, 0.03);
                backdrop-filter: blur(15px);
                border: 1px solid rgba(0, 255, 221, 0.1);
                border-radius: 12px;
                overflow-y: auto;
            }

            .request-viewer {
                flex: 1;
                background: rgba(0, 255, 221, 0.03);
                backdrop-filter: blur(15px);
                border: 1px solid rgba(0, 255, 221, 0.1);
                border-radius: 12px;
                overflow: hidden;
            }

            .tree-node {
                cursor: pointer;
                padding: 8px 12px;
                border-radius: 6px;
                margin: 2px 0;
                transition: all 0.2s ease;
                border: 1px solid transparent;
            }

            .tree-node:hover {
                background: rgba(0, 255, 221, 0.1);
                border-color: rgba(0, 255, 221, 0.2);
            }

            .tree-node.active {
                background: rgba(0, 255, 221, 0.15);
                border-left: 3px solid #00ffdd;
                border-color: rgba(0, 255, 221, 0.3);
                box-shadow: 0 0 15px rgba(0, 255, 221, 0.1);
            }

            .tree-indent {
                margin-left: 1rem;
            }

            .tree-icon {
                width: 16px;
                display: inline-block;
                margin-right: 8px;
                color: #00ffdd;
            }

            .payload-type {
                background: rgba(0, 255, 221, 0.1);
                color: #00ffdd;
                padding: 2px 6px;
                border-radius: 4px;
                font-size: 11px;
                margin-left: 8px;
                border: 1px solid rgba(0, 255, 221, 0.2);
            }

            .request-details {
                padding: 1.5rem;
                height: 100%;
                overflow-y: auto;
            }

            .detail-section {
                background: rgba(0, 0, 0, 0.3);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(0, 255, 221, 0.1);
                border-radius: 8px;
                padding: 1rem;
                margin-bottom: 1rem;
            }

            .json-tree {
                font-family: 'Monaco', 'Menlo', 'Ubuntu Mono', 'Courier New', monospace;
                font-size: 13px;
                line-height: 1.5;
            }

            .json-key {
                color: #dd00ff;
                font-weight: 500;
            }

            .json-string {
                color: #00ffdd;
            }

            .json-number {
                color: #ffaa00;
            }

            .json-boolean {
                color: #ff4444;
            }

            .expandable {
                cursor: pointer;
                user-select: none;
            }

            .expandable::before {
                content: '▶';
                margin-right: 6px;
                transition: transform 0.2s ease;
                color: #00ffdd;
            }

            .expandable.expanded::before {
                transform: rotate(90deg);
            }

            .pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 0.5rem;
                padding: 1rem;
                border-top: 1px solid rgba(0, 255, 221, 0.1);
            }

            .pagination button {
                background: rgba(0, 255, 221, 0.1);
                border: 1px solid rgba(0, 255, 221, 0.2);
                color: #00ffdd;
                padding: 0.5rem 1rem;
                border-radius: 0.5rem;
                transition: all 0.2s ease;
            }

            .pagination button:hover:not(:disabled) {
                background: rgba(0, 255, 221, 0.2);
                border-color: rgba(0, 255, 221, 0.4);
            }

            .pagination button:disabled {
                opacity: 0.3;
                cursor: not-allowed;
            }

            .pagination button.active {
                background: rgba(0, 255, 221, 0.3);
                border-color: rgba(0, 255, 221, 0.5);
            }

            .request-list {
                max-height: calc(100vh - 400px);
                overflow-y: auto;
            }

            .file-header-panel {
                background: rgba(0, 255, 221, 0.05);
                border-bottom: 1px solid rgba(0, 255, 221, 0.2);
                padding: 1rem;
            }

            .pagination-container {
                background: rgba(0, 255, 221, 0.08);
                backdrop-filter: blur(15px);
                border-top: 1px solid rgba(0, 255, 221, 0.2);
                padding: 1rem;
                position: sticky;
                bottom: 0;
                z-index: 10;
                box-shadow: 0 -4px 20px rgba(0, 0, 0, 0.3);
            }

            .footer {
                background: rgba(0, 255, 221, 0.05);
                backdrop-filter: blur(20px);
                border-top: 1px solid rgba(0, 255, 221, 0.15);
                padding: 1rem;
                text-align: center;
                color: rgba(0, 255, 221, 0.7);
                font-size: 0.875rem;
            }

            .footer a {
                color: #00ffdd;
                text-decoration: none;
                font-weight: 500;
            }

            .footer a:hover {
                color: #00d4ff;
                text-shadow: 0 0 5px rgba(0, 255, 221, 0.5);
            }

            .pagination {
                display: flex;
                justify-content: center;
                align-items: center;
                gap: 0.5rem;
                flex-wrap: wrap;
            }

            .pagination button {
                background: rgba(0, 255, 221, 0.15);
                backdrop-filter: blur(10px);
                border: 1px solid rgba(0, 255, 221, 0.3);
                color: #00ffdd;
                padding: 0.5rem 1rem;
                border-radius: 0.5rem;
                transition: all 0.2s ease;
                min-width: 44px; /* Touch-friendly size */
                min-height: 44px;
                display: flex;
                align-items: center;
                justify-content: center;
                box-shadow: 0 2px 8px rgba(0, 0, 0, 0.2);
            }

            .pagination button:hover:not(:disabled) {
                background: rgba(0, 255, 221, 0.25);
                border-color: rgba(0, 255, 221, 0.5);
                box-shadow: 0 4px 12px rgba(0, 255, 221, 0.2);
            }

            .pagination button:disabled {
                opacity: 0.4;
                cursor: not-allowed;
                background: rgba(0, 255, 221, 0.05);
            }

            .pagination button.active {
                background: rgba(0, 255, 221, 0.3);
                border-color: rgba(0, 255, 221, 0.6);
                box-shadow: 0 4px 16px rgba(0, 255, 221, 0.3);
                font-weight: bold;
            }

            .pagination-info {
                font-size: 0.875rem;
                color: rgba(0, 255, 221, 0.6);
                text-align: center;
                margin-top: 0.5rem;
            }

            /* Mobile Responsiveness */
            @media (max-width: 768px) {
                .tree-container {
                    flex-direction: column;
                    height: auto;
                    min-height: calc(100vh - 200px);
                }
                
                .file-browser {
                    width: 100%;
                    height: 250px;
                    min-width: auto;
                    margin-bottom: 1rem;
                }
                
                .request-viewer {
                    flex: 1;
                    height: auto;
                    min-height: 500px;
                }
                
                .request-list {
                    max-height: 400px;
                }
                
                .pagination {
                    gap: 0.25rem;
                    flex-wrap: wrap;
                    justify-content: center;
                }
                
                .pagination button {
                    padding: 0.4rem 0.8rem;
                    min-width: 40px;
                    min-height: 40px;
                    font-size: 0.875rem;
                }
                
                .pagination-info {
                    font-size: 0.75rem;
                    margin-top: 0.5rem;
                }
                
                .filters-section {
                    padding: 0.75rem;
                }
                
                .filters-section .grid {
                    grid-template-columns: 1fr;
                    gap: 0.75rem;
                }

                .file-header-panel {
                    padding: 1rem 0.75rem;
                }

                .file-header-panel .flex {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 1rem;
                }

                .container {
                    padding-left: 1rem;
                    padding-right: 1rem;
                }
            }

            @media (max-width: 480px) {
                .glass-navbar .container {
                    padding-left: 1rem;
                    padding-right: 1rem;
                }

                .glass-navbar h1 {
                    font-size: 1.25rem;
                }

                .glass-navbar .flex {
                    flex-direction: column;
                    align-items: flex-start;
                    gap: 0.5rem;
                }
                
                .file-browser {
                    height: 200px;
                }

                .request-viewer {
                    min-height: 400px;
                }
                
                .file-header-panel h3 {
                    font-size: 1rem;
                }
                
                .pagination button {
                    min-width: 36px;
                    min-height: 36px;
                    padding: 0.25rem 0.5rem;
                    font-size: 0.75rem;
                }
                
                .pagination-container {
                    padding: 0.75rem;
                }

                .tree-node {
                    padding: 0.75rem;
                }

                .detail-section {
                    padding: 0.75rem;
                    margin-bottom: 0.75rem;
                }
            }

            /* Custom Scrollbar Styling */
            ::-webkit-scrollbar {
                width: 8px;
                height: 8px;
            }

            ::-webkit-scrollbar-track {
                background: rgba(0, 0, 0, 0.3);
                border-radius: 4px;
            }

            ::-webkit-scrollbar-thumb {
                background: rgba(0, 255, 221, 0.3);
                border-radius: 4px;
                border: 1px solid rgba(0, 255, 221, 0.1);
            }

            ::-webkit-scrollbar-thumb:hover {
                background: rgba(0, 255, 221, 0.5);
            }

            ::-webkit-scrollbar-corner {
                background: rgba(0, 0, 0, 0.3);
            }

            /* Firefox scrollbar */
            * {
                scrollbar-width: thin;
                scrollbar-color: rgba(0, 255, 221, 0.3) rgba(0, 0, 0, 0.3);
            }

            .filters-section {
                background: rgba(0, 255, 221, 0.03);
                border: 1px solid rgba(0, 255, 221, 0.1);
                border-radius: 8px;
                padding: 1rem;
                margin-bottom: 1rem;
            }
        </style>
    </head>

    <body x-data="webhookDashboard()" class="min-h-screen">
        <!-- Header -->
        <div class="glass-navbar sticky top-0 z-50">
            <div class="container mx-auto px-6 py-4">
                <div class="flex items-center justify-between">
                    <div class="flex items-center space-x-4">
                        <i class="fas fa-webhook text-cyan-400 text-2xl" style="color: #00ffdd;"></i>
                        <h1 class="text-2xl font-bold" style="color: #00ffdd;">Webhook Testing Dashboard</h1>
                        <span class="bg-cyan-500/20 text-cyan-300 px-3 py-1 rounded-full text-sm border border-cyan-400/30" style="color: #00ffdd; border-color: rgba(0, 255, 221, 0.3);">Pro</span>
                    </div>
                    <div class="flex items-center space-x-4">
                        <div class="text-sm" style="color: rgba(0, 255, 221, 0.7);">
                            <span class="status-indicator status-success"></span>
                            <span x-text="totalRequests + ' Total Requests'"></span>
                        </div>
                        <a href="?logout"
                            class="bg-red-500/20 hover:bg-red-500/30 text-red-300 px-4 py-2 rounded-lg transition-colors border border-red-400/30">
                            <i class="fas fa-sign-out-alt mr-2"></i>Logout
                        </a>
                    </div>
                </div>
            </div>
        </div>

        <div class="container mx-auto px-6 py-8">
            <!-- Main Tree Container -->
            <div class="tree-container">
                <!-- File Browser -->
                <div class="file-browser">
                    <div class="p-4 border-b border-gray-700/50" style="border-color: rgba(0, 255, 221, 0.2);">
                        <h3 class="text-lg font-semibold flex items-center" style="color: #00ffdd;">
                            <i class="fas fa-folder-tree mr-2" style="color: #00ffdd;"></i>
                            Log Files
                        </h3>
                        <p class="text-sm mt-1" style="color: rgba(0, 255, 221, 0.6);" x-text="logFiles.length + ' files'"></p>
                    </div>

                    <div class="p-2">
                        <template x-for="file in logFiles" :key="file.filename">
                            <div class="mb-2">
                                <!-- File Header -->
                                <div @click="toggleFile(file.filename)"
                                    :class="selectedFile === file.filename ? 'tree-node active' : 'tree-node'"
                                    class="relative flex items-start justify-between">
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center">
                                            <i class="tree-icon fas fa-file-alt" style="color: #00ffdd;"></i>
                                            <span class="font-medium truncate" style="color: #00ffdd;" x-text="file.filename"></span>
                                        </div>
                                        <div class="text-xs mt-1 ml-6" style="color: rgba(0, 255, 221, 0.6);">
                                            <div x-text="file.requests + ' requests'"></div>
                                            <div x-text="formatFileSize(file.size)"></div>
                                            <div class="flex flex-wrap gap-1 mt-1">
                                                <template x-for="(count, method) in file.methods" :key="method">
                                                    <span :class="'method-' + method.toLowerCase() + ' text-xs px-1 rounded'"
                                                        x-text="method + ':' + count"></span>
                                                </template>
                                            </div>
                                        </div>
                                    </div>
                                    <button @click.stop="deleteFile(file.filename)"
                                        class="flex-shrink-0 ml-2 p-2 text-red-400 hover:text-red-300 hover:bg-red-500/10 rounded transition-colors">
                                        <i class="fas fa-trash-alt text-sm"></i>
                                    </button>
                                </div>
                            </div>
                        </template>
                    </div>
                </div>

                <!-- Request Viewer -->
                <div class="request-viewer">
                    <!-- File Header when file is selected -->
                    <div x-show="selectedFile && !selectedRequest" class="file-header-panel">
                        <div class="flex items-center justify-between">
                            <div>
                                <h3 class="text-lg font-semibold flex items-center" style="color: #00ffdd;">
                                    <i class="fas fa-file-alt mr-2" style="color: #00ffdd;"></i>
                                    <span x-text="selectedFile"></span>
                                    <span class="ml-2 text-sm" style="color: rgba(0, 255, 221, 0.6);" 
                                        x-text="fileRequests[selectedFile] ? '(' + filteredRequests.length + ' requests)' : ''"></span>
                                </h3>
                                <div class="mt-2 flex items-center gap-4 text-sm" style="color: rgba(0, 255, 221, 0.6);">
                                    <span x-text="'File size: ' + formatFileSize(getCurrentFileSize())"></span>
                                    <span x-text="'Last modified: ' + formatDate(getCurrentFileModified())"></span>
                                </div>
                            </div>
                            <button @click="refreshData()"
                                class="bg-cyan-500/20 hover:bg-cyan-500/30 px-4 py-2 rounded-lg transition-colors border border-cyan-400/30"
                                style="color: #00ffdd;">
                                <i class="fas fa-sync-alt mr-2"></i>Refresh
                            </button>
                        </div>
                    </div>

                    <!-- Request Details Header when request is selected -->
                    <div x-show="selectedRequest" class="p-4 border-b border-gray-700/50" style="border-color: rgba(0, 255, 221, 0.2);">
                        <h3 class="text-lg font-semibold flex items-center" style="color: #00ffdd;">
                            <i class="fas fa-eye mr-2" style="color: #00ffdd;"></i>
                            Request Details
                            <span class="ml-2 text-sm" style="color: rgba(0, 255, 221, 0.6);"
                                x-text="selectedRequest?.method + ' ' + selectedRequest?.path"></span>
                        </h3>
                        <button @click="backToFileList()" class="mt-2 text-sm text-cyan-400 hover:text-cyan-300">
                            <i class="fas fa-arrow-left mr-1"></i>Back to file list
                        </button>
                    </div>

                    <!-- Request List when file is selected but no specific request -->
                    <div x-show="selectedFile && !selectedRequest" class="request-list">
                        <div class="p-4">
                            <!-- Filters Section -->
                            <div class="filters-section">
                                <!-- Search within file -->
                                <div class="mb-4">
                                    <div class="relative">
                                        <i class="fas fa-search absolute left-3 top-3" style="color: rgba(0, 255, 221, 0.5);"></i>
                                        <input type="text" x-model="fileSearchQuery" placeholder="Search requests in this file..."
                                            class="w-full pl-10 pr-4 py-2 glass-input rounded-lg focus:outline-none">
                                    </div>
                                </div>

                                <!-- Method and Content Type Filters -->
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <!-- Method Filter -->
                                    <select x-model="methodFilter"
                                        class="glass-input rounded-lg px-4 py-2 focus:outline-none">
                                        <option value="">All Methods</option>
                                        <option value="GET">GET</option>
                                        <option value="POST">POST</option>
                                        <option value="PUT">PUT</option>
                                        <option value="DELETE">DELETE</option>
                                        <option value="PATCH">PATCH</option>
                                        <option value="OPTIONS">OPTIONS</option>
                                        <option value="HEAD">HEAD</option>
                                    </select>

                                    <!-- Content Type Filter -->
                                    <select x-model="contentTypeFilter"
                                        class="glass-input rounded-lg px-4 py-2 focus:outline-none">
                                        <option value="">All Content Types</option>
                                        <option value="application/json">JSON</option>
                                        <option value="application/x-www-form-urlencoded">Form Data</option>
                                        <option value="multipart/form-data">Multipart</option>
                                        <option value="application/xml">XML</option>
                                        <option value="text/plain">Plain Text</option>
                                        <option value="application/octet-stream">Binary</option>
                                    </select>
                                </div>

                                <!-- Results Summary -->
                                <div class="mt-3 flex items-center justify-between text-sm" style="color: rgba(0, 255, 221, 0.6);">
                                    <span x-text="'Showing ' + filteredRequests.length + ' of ' + (fileRequests[selectedFile] ? fileRequests[selectedFile].length : 0) + ' requests'"></span>
                                    <button @click="clearFilters()" x-show="methodFilter || contentTypeFilter || fileSearchQuery"
                                        class="text-cyan-400 hover:text-cyan-300 text-xs">
                                        <i class="fas fa-times mr-1"></i>Clear filters
                                    </button>
                                </div>
                            </div>

                            <!-- Request items with pagination -->
                            <template x-for="(request, index) in paginatedRequests" :key="index">
                                <div @click="selectRequest(selectedFile, request.originalIndex)"
                                    class="tree-node hover:bg-cyan-500/10 cursor-pointer mb-2 p-3 rounded-lg border border-transparent hover:border-cyan-400/30">
                                    <div class="flex items-center justify-between">
                                        <div class="flex items-center space-x-3">
                                            <span :class="'method-' + request.method.toLowerCase() + ' px-2 py-1 rounded text-xs font-bold'"
                                                x-text="request.method"></span>
                                            <span class="text-cyan-300" x-text="truncatePath(request.path, 30)"></span>
                                            <span class="payload-type text-xs" x-text="getMainContentType(request.content_type)"></span>
                                        </div>
                                        <div class="text-xs" style="color: rgba(0, 255, 221, 0.5);" x-text="formatTimeAgo(request.created_at)"></div>
                                    </div>
                                    <div class="mt-2 text-xs" style="color: rgba(0, 255, 221, 0.6);">
                                        <span x-text="'IP: ' + (request.remote_ip || 'N/A')"></span>
                                        <span class="ml-4" x-text="'Length: ' + (request.content_length || '0') + ' bytes'"></span>
                                    </div>
                                </div>
                            </template>

                            <div x-show="filteredRequests.length === 0" class="text-center py-8" style="color: rgba(0, 255, 221, 0.4);">
                                <i class="fas fa-search text-2xl mb-2"></i>
                                <p>No requests match your search criteria</p>
                            </div>
                        </div>
                    </div>

                    <div class="request-details" x-show="selectedRequest">
                        <!-- Request Overview -->
                        <div class="detail-section">
                            <h4 class="font-semibold mb-3 flex items-center" style="color: #00ffdd;">
                                <i class="fas fa-info-circle mr-2" style="color: #00ffdd;"></i>
                                Overview
                            </h4>
                            <div class="grid grid-cols-2 gap-4 text-sm">
                                <div>
                                    <span style="color: rgba(0, 255, 221, 0.6);">Method:</span>
                                    <span
                                        :class="'method-' + selectedRequest?.method?.toLowerCase() + ' ml-2 font-semibold'"
                                        x-text="selectedRequest?.method"></span>
                                </div>
                                <div>
                                    <span style="color: rgba(0, 255, 221, 0.6);">Content Type:</span>
                                    <span class="ml-2" style="color: #00ffdd;" x-text="selectedRequest?.content_type || 'N/A'"></span>
                                </div>
                                <div>
                                    <span style="color: rgba(0, 255, 221, 0.6);">Content Length:</span>
                                    <span class="ml-2" style="color: #00ffdd;" x-text="selectedRequest?.content_length || '0'"></span>
                                </div>
                                <div>
                                    <span style="color: rgba(0, 255, 221, 0.6);">Remote IP:</span>
                                    <span class="ml-2" style="color: #00ffdd;" x-text="selectedRequest?.remote_ip || 'N/A'"></span>
                                </div>
                                <div class="col-span-2">
                                    <span style="color: rgba(0, 255, 221, 0.6);">Path:</span>
                                    <code class="bg-gray-800/50 px-2 py-1 rounded ml-2" style="color: #00ffdd; background: rgba(0, 0, 0, 0.4);"
                                        x-text="selectedRequest?.path"></code>
                                </div>
                                <div class="col-span-2">
                                    <span style="color: rgba(0, 255, 221, 0.6);">User Agent:</span>
                                    <span class="ml-2 text-xs" style="color: #00ffdd;"
                                        x-text="selectedRequest?.user_agent || 'N/A'"></span>
                                </div>
                            </div>
                        </div>

                        <!-- Payload Tree View -->
                        <div class="detail-section">
                            <h4 class="font-semibold mb-3 flex items-center" style="color: #00ffdd;">
                                <i class="fas fa-code mr-2" style="color: #dd00ff;"></i>
                                Payload Data
                            </h4>
                            <div class="json-tree">
                                <template x-for="(value, key) in selectedRequest?.payload || {}" :key="key">
                                    <div x-show="hasData(value)" class="mb-4">
                                        <div class="expandable" @click="toggleExpanded(key)"
                                            :class="isExpanded(key) ? 'expanded' : ''">
                                            <span class="json-key" x-text="formatPayloadKey(key)"></span>
                                            <span class="text-sm ml-2" style="color: rgba(0, 255, 221, 0.6);" x-text="getDataSummary(value)"></span>
                                        </div>
                                        <div x-show="isExpanded(key)" class="ml-4 mt-2 p-3 rounded" style="background: rgba(0, 0, 0, 0.5);">
                                            <pre class="text-sm overflow-x-auto" x-text="formatJSON(value)"></pre>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <!-- Headers -->
                        <div class="detail-section" x-show="hasData(selectedRequest?.headers)">
                            <h4 class="font-semibold mb-3 flex items-center" style="color: #00ffdd;">
                                <i class="fas fa-list-ul mr-2" style="color: #ffaa00;"></i>
                                WH_ Headers
                            </h4>
                            <pre class="text-sm overflow-x-auto" x-text="formatJSON(selectedRequest?.headers)"></pre>
                        </div>

                        <!-- Cookies -->
                        <div class="detail-section" x-show="hasData(selectedRequest?.cookies)">
                            <h4 class="font-semibold mb-3 flex items-center" style="color: #00ffdd;">
                                <i class="fas fa-cookie-bite mr-2" style="color: #ff8800;"></i>
                                WH_ Cookies
                            </h4>
                            <pre class="text-sm overflow-x-auto" x-text="formatJSON(selectedRequest?.cookies)"></pre>
                        </div>
                    </div>

                    <div x-show="!selectedRequest && !selectedFile" class="flex items-center justify-center h-full" style="color: rgba(0, 255, 221, 0.4);">
                        <div class="text-center">
                            <i class="fas fa-mouse-pointer text-4xl mb-4"></i>
                            <p>Select a file to view requests</p>
                        </div>
                    </div>

                    <div x-show="!selectedRequest" class="flex items-center justify-center h-full" style="color: rgba(0, 255, 221, 0.4);">
                        <div class="text-center">
                            <i class="fas fa-mouse-pointer text-4xl mb-4"></i>
                            <p>Select a request to view details</p>
                        </div>
                    </div>

                    <!-- Pagination at the very end of request-viewer -->
                    <div x-show="selectedFile && !selectedRequest && totalPages > 1" class="pagination-container">
                        <div class="pagination">
                            <button @click="currentPage = 1" :disabled="currentPage === 1" title="First page">
                                <i class="fas fa-angle-double-left"></i>
                            </button>
                            <button @click="currentPage = Math.max(1, currentPage - 1)" :disabled="currentPage === 1" title="Previous page">
                                <i class="fas fa-angle-left"></i>
                            </button>
                            
                            <template x-for="page in visiblePages" :key="page">
                                <button @click="currentPage = page" 
                                    :class="currentPage === page ? 'active' : ''"
                                    x-text="page"></button>
                            </template>
                            
                            <button @click="currentPage = Math.min(totalPages, currentPage + 1)" :disabled="currentPage === totalPages" title="Next page">
                                <i class="fas fa-angle-right"></i>
                            </button>
                            <button @click="currentPage = totalPages" :disabled="currentPage === totalPages" title="Last page">
                                <i class="fas fa-angle-double-right"></i>
                            </button>
                        </div>
                        
                        <div class="pagination-info">
                            Page <span x-text="currentPage"></span> of <span x-text="totalPages"></span>
                            (<span x-text="filteredRequests.length"></span> requests)
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <script>
            function webhookDashboard() {
                return {
                    logFiles: <?php echo json_encode($logFiles); ?>,
                    fileRequests: {},
                    selectedFile: '',
                    selectedRequest: null,
                    selectedRequestIndex: -1,
                    searchQuery: '',
                    fileSearchQuery: '',
                    methodFilter: '',
                    contentTypeFilter: '',
                    totalRequests: 0,
                    expandedSections: {},
                    currentPage: 1,
                    itemsPerPage: 10,

                    init() {
                        this.calculateTotalRequests();
                        this.$watch('searchQuery', () => this.applyFilters());
                        this.$watch('fileSearchQuery', () => this.currentPage = 1);
                        this.$watch('methodFilter', () => this.applyFilters());
                        this.$watch('contentTypeFilter', () => this.applyFilters());
                    },

                    calculateTotalRequests() {
                        this.totalRequests = this.logFiles.reduce((sum, file) => sum + file.requests, 0);
                    },

                    get filteredRequests() {
                        if (!this.selectedFile || !this.fileRequests[this.selectedFile]) {
                            return [];
                        }
                        
                        let requests = this.fileRequests[this.selectedFile];
                        
                        // Apply file-specific search
                        if (this.fileSearchQuery) {
                            const query = this.fileSearchQuery.toLowerCase();
                            requests = requests.filter(req => 
                                req.method.toLowerCase().includes(query) ||
                                req.path.toLowerCase().includes(query) ||
                                (req.content_type && req.content_type.toLowerCase().includes(query)) ||
                                (req.remote_ip && req.remote_ip.includes(query))
                            );
                        }
                        
                        // Apply method filter
                        if (this.methodFilter) {
                            requests = requests.filter(req => req.method === this.methodFilter);
                        }
                        
                        // Apply content type filter
                        if (this.contentTypeFilter) {
                            requests = requests.filter(req => 
                                req.content_type && req.content_type.includes(this.contentTypeFilter)
                            );
                        }
                        
                        // Add original index for tracking
                        return requests.map((req, index) => ({
                            ...req,
                            originalIndex: this.fileRequests[this.selectedFile].indexOf(req)
                        }));
                    },

                    get totalPages() {
                        return Math.ceil(this.filteredRequests.length / this.itemsPerPage);
                    },

                    get paginatedRequests() {
                        const start = (this.currentPage - 1) * this.itemsPerPage;
                        const end = start + this.itemsPerPage;
                        return this.filteredRequests.slice(start, end);
                    },

                    get visiblePages() {
                        const pages = [];
                        const totalPages = this.totalPages;
                        const current = this.currentPage;
                        
                        // Always show first page
                        if (totalPages > 0) pages.push(1);
                        
                        // Show pages around current page
                        for (let i = Math.max(2, current - 2); i <= Math.min(totalPages - 1, current + 2); i++) {
                            if (!pages.includes(i)) pages.push(i);
                        }
                        
                        // Always show last page
                        if (totalPages > 1 && !pages.includes(totalPages)) {
                            pages.push(totalPages);
                        }
                        
                        return pages.sort((a, b) => a - b);
                    },

                    async toggleFile(filename) {
                        if (this.selectedFile === filename) {
                            this.selectedFile = '';
                            this.selectedRequest = null;
                            return;
                        }

                        this.selectedFile = filename;
                        this.selectedRequest = null;
                        this.selectedRequestIndex = -1;
                        this.fileSearchQuery = '';
                        this.currentPage = 1;

                        if (!this.fileRequests[filename]) {
                            await this.loadFileRequests(filename);
                        }
                    },

                    async loadFileRequests(filename) {
                        try {
                            const response = await fetch('', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: 'action=get_requests&filename=' + encodeURIComponent(filename)
                            });
                            const result = await response.json();
                            if (result.success) {
                                this.fileRequests[filename] = result.data || [];
                            }
                        } catch (error) {
                            console.error('Error loading requests from ' + filename, error);
                        }
                    },

                    selectRequest(filename, index) {
                        if (this.fileRequests[filename] && this.fileRequests[filename][index]) {
                            this.selectedRequest = this.fileRequests[filename][index];
                            this.selectedRequestIndex = index;
                            this.expandedSections = {}; // Reset expanded sections
                        }
                    },

                    backToFileList() {
                        this.selectedRequest = null;
                        this.selectedRequestIndex = -1;
                    },

                    getCurrentFileSize() {
                        const file = this.logFiles.find(f => f.filename === this.selectedFile);
                        return file ? file.size : 0;
                    },

                    getCurrentFileModified() {
                        const file = this.logFiles.find(f => f.filename === this.selectedFile);
                        return file ? file.modified : 0;
                    },

                    applyFilters() {
                        this.currentPage = 1;
                        // Filtering is handled by computed properties
                    },

                    clearFilters() {
                        this.fileSearchQuery = '';
                        this.methodFilter = '';
                        this.contentTypeFilter = '';
                        this.currentPage = 1;
                    },

                    async deleteFile(filename) {
                        if (!confirm('Are you sure you want to delete ' + filename + '?')) return;

                        try {
                            const response = await fetch('', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: 'action=delete_file&filename=' + encodeURIComponent(filename)
                            });
                            const result = await response.json();
                            if (result.success) {
                                this.refreshData();
                                if (this.selectedFile === filename) {
                                    this.selectedFile = '';
                                    this.selectedRequest = null;
                                }
                            } else {
                                alert('Error deleting file: ' + result.error);
                            }
                        } catch (error) {
                            alert('Error deleting file');
                        }
                    },

                    async deleteRequest(filename, index) {
                        if (!confirm('Are you sure you want to delete this request?')) return;

                        try {
                            const response = await fetch('', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
                                body: 'action=delete_request&filename=' + encodeURIComponent(filename) + '&index=' + index
                            });
                            const result = await response.json();
                            if (result.success) {
                                await this.loadFileRequests(filename);
                                this.refreshData();
                                if (this.selectedRequestIndex === index) {
                                    this.selectedRequest = null;
                                    this.selectedRequestIndex = -1;
                                }
                            } else {
                                alert('Error deleting request: ' + result.error);
                            }
                        } catch (error) {
                            alert('Error deleting request');
                        }
                    },

                    refreshData() {
                        location.reload();
                    },

                    toggleExpanded(key) {
                        this.expandedSections[key] = !this.expandedSections[key];
                    },

                    isExpanded(key) {
                        return this.expandedSections[key] || false;
                    },

                    hasData(obj) {
                        if (!obj) return false;
                        if (typeof obj === 'string') return obj.length > 0;
                        if (Array.isArray(obj)) return obj.length > 0;
                        if (typeof obj === 'object') return Object.keys(obj).length > 0;
                        return true;
                    },

                    formatPayloadKey(key) {
                        const keyMappings = {
                            'query_params': 'Query Parameters (GET)',
                            'form_data': 'Form Data (POST)',
                            'files': 'File Uploads',
                            'raw_body': 'Raw Body',
                            'json_data': 'JSON Data',
                            'xml_data': 'XML Data',
                            'multipart_data': 'Multipart Data',
                            'url_encoded': 'URL Encoded Data',
                            'binary_data': 'Binary Data',
                            'text_data': 'Plain Text Data'
                        };
                        return keyMappings[key] || key;
                    },

                    getDataSummary(data) {
                        if (!data) return '(empty)';
                        if (typeof data === 'string') return `(${data.length} chars)`;
                        if (Array.isArray(data)) return `(${data.length} items)`;
                        if (typeof data === 'object') {
                            const keys = Object.keys(data);
                            return `(${keys.length} ${keys.length === 1 ? 'key' : 'keys'})`;
                        }
                        return '';
                    },

                    formatJSON(obj) {
                        if (!obj) return '';
                        try {
                            return JSON.stringify(obj, null, 2);
                        } catch (e) {
                            return String(obj);
                        }
                    },

                    getMainContentType(contentType) {
                        if (!contentType) return 'unknown';
                        const main = contentType.split(';')[0].toLowerCase();
                        const typeMap = {
                            'application/json': 'JSON',
                            'application/x-www-form-urlencoded': 'Form',
                            'multipart/form-data': 'Multipart',
                            'application/xml': 'XML',
                            'text/xml': 'XML',
                            'text/plain': 'Text',
                            'application/octet-stream': 'Binary'
                        };
                        return typeMap[main] || main.split('/')[1] || 'unknown';
                    },

                    truncatePath(path, maxLength) {
                        if (!path || path.length <= maxLength) return path;
                        return path.substring(0, maxLength - 3) + '...';
                    },

                    formatTimestamp(timestamp) {
                        if (!timestamp) return 'N/A';
                        return new Date(timestamp).toLocaleString();
                    },

                    formatTimeAgo(timestamp) {
                        if (!timestamp) return '';
                        const diff = Date.now() - timestamp;
                        const minutes = Math.floor(diff / 60000);
                        const hours = Math.floor(diff / 3600000);
                        const days = Math.floor(diff / 86400000);

                        if (days > 0) return days + 'd ago';
                        if (hours > 0) return hours + 'h ago';
                        if (minutes > 0) return minutes + 'm ago';
                        return 'Just now';
                    },

                    formatDate(timestamp) {
                        return new Date(timestamp * 1000).toLocaleDateString();
                    },

                    formatFileSize(bytes) {
                        if (bytes === 0) return '0 B';
                        const k = 1024;
                        const sizes = ['B', 'KB', 'MB', 'GB'];
                        const i = Math.floor(Math.log(bytes) / Math.log(k));
                        return parseFloat((bytes / Math.pow(k, i)).toFixed(1)) + ' ' + sizes[i];
                    }
                }
            }
        </script>

        <!-- Footer with Credit -->
        <footer class="footer">
            <div class="container mx-auto">
                <p>© 2025 Webhook Testing Dashboard | Designed & Developed by <a href="https://0xAhmadYousufcom" target="_blank">AhmadYousuf</a></p>
            </div>
        </footer>
    </body>

    </html>
    <?php
    exit;
}
