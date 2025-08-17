<?php
session_start();

// 检查是否登录
if (!isset($_SESSION['logged_in'])) {
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        die("未登录，无法导出");
    }
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => false, "error" => "未登录"]);
    exit;
}

// 配置文件
$configFile = __DIR__ . '/config.json';
if (!file_exists($configFile)) {
    die("配置文件缺失");
}
$config = json_decode(file_get_contents($configFile), true);

$template = $_POST['template'] ?? ($_GET['template'] ?? '');
$params   = $_POST['params'] ?? ($_GET['params'] ?? []);
$page     = max(1, intval($_POST['page'] ?? $_GET['page'] ?? 1));
$pageSize = max(1, intval($_POST['pageSize'] ?? $_GET['pageSize'] ?? 20));
$offset   = ($page - 1) * $pageSize;
$export   = $_POST['export'] ?? $_GET['export'] ?? '';

if (!$template) {
    if ($export === "csv") {
        die("缺少查询模板，无法导出");
    }
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => false, "error" => "未选择查询模板"]);
    exit;
}

try {
    $dsn = "mysql:host={$config['db']['host']};dbname={$config['db']['dbname']};port={$config['db']['port']};charset={$config['db']['charset']}";
    $pdo = new PDO($dsn, $config['db']['user'], $config['db']['pass']);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 替换 {{param}}
    $template = preg_replace("/'{{(\w+)}}'/", ":$1", $template);
    $template = preg_replace("/{{(\w+)}}/", ":$1", $template);

    // === 如果导出 CSV ===
    if ($export === "csv") {
        $stmt = $pdo->prepare($template);
        foreach ($params as $key => $value) {
            $stmt->bindValue(":$key", $value);
        }
        $stmt->execute();
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // 设置下载头
        header("Content-Type: text/csv; charset=utf-8");
        header("Content-Disposition: attachment; filename=\"export_" . date("Ymd_His") . ".csv\"");

        $output = fopen("php://output", "w");
        if (!empty($rows)) {
            // 写表头
            fputcsv($output, array_keys($rows[0]));
            // 写数据
            foreach ($rows as $row) {
                fputcsv($output, $row);
            }
        }
        fclose($output);
        exit;
    }

    // === 正常分页查询 ===
    // 总数
    $countSql = "SELECT COUNT(*) FROM (" . $template . ") AS subquery";
    $stmt = $pdo->prepare($countSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->execute();
    $total = (int)$stmt->fetchColumn();

    // 分页查询
    $pagedSql = $template . " LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($pagedSql);
    foreach ($params as $key => $value) {
        $stmt->bindValue(":$key", $value);
    }
    $stmt->bindValue(":limit", $pageSize, PDO::PARAM_INT);
    $stmt->bindValue(":offset", $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    header("Content-Type: application/json; charset=utf-8");
    echo json_encode([
        "success" => true,
        "data"    => $rows,
        "total"   => $total,
        "page"    => $page,
        "pageSize"=> $pageSize
    ], JSON_UNESCAPED_UNICODE);

} catch (Exception $e) {
    error_log($e->getMessage());
    var_dump($e->getMessage());
    if ($export === "csv") {
        die("导出失败，请联系管理员");
    }
    header("Content-Type: application/json; charset=utf-8");
    echo json_encode(["success" => false, "error" => "执行失败，请联系管理员"]);
}
