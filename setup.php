<?php
// setup.php - 初始化配置文件

$configFile = __DIR__ . '/config.json';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $adminUser = trim($_POST['admin_user']);
    $adminPass = $_POST['admin_pass'];
    $dbHost    = trim($_POST['db_host']);
    $dbName    = trim($_POST['db_name']);
    $dbUser    = trim($_POST['db_user']);
    $dbPass    = $_POST['db_pass'];
    $dbPort    = $_POST['db_port'];
    $dbCharset = $_POST['db_charset'];

    $config = [
        "query_user" => [
            "username" => $adminUser,
            "password" => password_hash($adminPass, PASSWORD_DEFAULT) // 存 hash
        ],
        "db" => [
            "host" => $dbHost,
            "dbname" => $dbName,
            "user" => $dbUser,
            "pass" => $dbPass,
            "port" => $dbPort,
            "charset" => $dbCharset
        ],
        "sql_templates" => [
            [
                "name" => "查询所有用户",
                "query" => "SELECT user_id as '用户ID',phone as '手机号',real_name as '姓名',FROM_UNIXTIME(created_at) as '创建时间' from ch_users where deleted_at is null",
                "query_params" => new stdClass()
            ],
            [
                "name" => "根据手机号查询用户",
                "query" => "SELECT user_id as '用户ID',phone as '手机号',real_name as '姓名',FROM_UNIXTIME(created_at) as '创建时间' from ch_users where phone = {{phone}} and deleted_at is null",
                "query_params" => [
                    "phone" => [
                        "type" => "input",
                        "description" => "手机号"
                    ]
                ]
            ],
            [
                "name" => "根据注册时间查询用户",
                "query" => "SELECT user_id as '用户ID',phone as '手机号',real_name as '姓名',FROM_UNIXTIME(created_at) as '创建时间' from ch_users where created_at >= UNIX_TIMESTAMP({{start_time}}) and created_at <= UNIX_TIMESTAMP({{end_time}}) and deleted_at is null",
                "query_params" => [
                    "start_time" => [
                        "type" => "date",
                        "description" => "开始时间"
                    ],
                    "end_time" => [
                        "type" => "date",
                        "description" => "结束时间"
                    ]
                ]
            ],
            [
                "name" => "根据订单状态搜索订单信息",
                "query" => "SELECT borrow_sn as '订单号',real_name as '姓名',borrow_amount as '合同金额',FROM_UNIXTIME(created_at) as '签约时间' from ch_borrows where `status` = {{status}} and deleted_at is null",
                "query_params" => [
                    "status" => [
                        "type" => "select",
                        "options" => [
                            ["value" => "5", "label" => "拒绝签约"],
                            ["value" => "6", "label" => "取消签约"],
                            ["value" => "9", "label" => "签约失败"],
                            ["value" => "11", "label" => "已签约"],
                            ["value" => "12", "label" => "已完成"]
                        ],
                        "description" => "订单状态"
                    ]
                ]
            ]
        ]
    ];

    file_put_contents($configFile, json_encode($config, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    header("Location: index.php");
    exit;
}

?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>初始化配置</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-lg">
        <h2 class="text-2xl font-bold mb-6 text-center">初始化配置</h2>
        <form method="post" class="space-y-4">
            <div>
                <label class="block mb-1 font-medium">管理用户名</label>
                <input type="text" name="admin_user" required class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-blue-300">
            </div>
            <div>
                <label class="block mb-1 font-medium">管理密码</label>
                <input type="password" name="admin_pass" required class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-blue-300">
            </div>
            <div>
                <label class="block mb-1 font-medium">数据库主机</label>
                <input type="text" name="db_host" required placeholder="例如: 127.0.0.1" class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block mb-1 font-medium">数据库名</label>
                <input type="text" name="db_name" required class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block mb-1 font-medium">数据库用户</label>
                <input type="text" name="db_user" required class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block mb-1 font-medium">数据库密码</label>
                <input type="password" name="db_pass" required class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block mb-1 font-medium">数据库端口</label>
                <input type="text" name="db_port" required value="3306" class="w-full border rounded-lg px-3 py-2">
            </div>
            <div>
                <label class="block mb-1 font-medium">数据库字符集</label>
                <input type="text" name="db_charset" required value="utf8mb4" class="w-full border rounded-lg px-3 py-2">
            </div>
            <button type="submit"
                class="w-full bg-green-500 hover:bg-green-600 text-white py-2 rounded-lg">生成配置文件</button>
        </form>
    </div>
</body>

</html>