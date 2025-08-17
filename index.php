<?php
session_start();

// 配置文件路径（建议放在 web 根目录外）
$configFile = __DIR__ . '/config.json';
if (!file_exists($configFile)) {
    header('Location: setup.php');
    exit;
}
$config = json_decode(file_get_contents($configFile), true);

// 登录处理
if (isset($_POST['login'])) {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $storedUser = $config['query_user']['username'] ?? '';
    $storedPass = $config['query_user']['password'] ?? '';
    $passInfo = password_get_info($storedPass);
    $ok = false;
    if (!empty($passInfo['algo'])) {
        $ok = ($username === $storedUser) && password_verify($password, $storedPass);
    } else {
        $ok = ($username === $storedUser) && hash_equals($storedPass, $password);
    }
    if ($ok) {
        $_SESSION['logged_in'] = true;
    } else {
        $error = '用户名或密码错误';
    }
}

// 退出登录
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}
?>
<!DOCTYPE html>
<html>

<head>
    <meta charset="UTF-8">
    <title>数据查询工具</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>

<body class="bg-gray-100 min-h-screen flex items-center justify-center">
    <?php if (!isset($_SESSION['logged_in'])): ?>
        <!-- 登录页面 -->
        <div class="bg-white p-8 rounded-xl shadow-lg w-full max-w-sm">
            <h2 class="text-2xl font-bold mb-6 text-center">登录</h2>
            <?php if (isset($error)) echo "<p class='text-red-500 mb-4'>" . htmlspecialchars($error) . "</p>"; ?>
            <form method="post" class="space-y-4">
                <div>
                    <label class="block mb-1 font-medium">用户名</label>
                    <input type="text" name="username" required
                        class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-blue-300">
                </div>
                <div>
                    <label class="block mb-1 font-medium">密码</label>
                    <input type="password" name="password" required
                        class="w-full border rounded-lg px-3 py-2 focus:ring focus:ring-blue-300">
                </div>
                <button type="submit" name="login"
                    class="w-full bg-blue-500 hover:bg-blue-600 text-white py-2 rounded-lg">登录</button>
            </form>
        </div>
    <?php else: ?>
        <!-- 查询页面 -->
        <div class="w-full max-w-6xl bg-white p-6 rounded-xl shadow-lg">
            <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-3 mb-6">
                <h2 class="text-2xl font-bold">SQL 查询</h2>
                <div class="flex items-center gap-3">
                    <label class="text-sm text-gray-600">每页</label>
                    <select id="page-size" class="border rounded px-2 py-1">
                        <option value="10" selected>10</option>
                        <option value="20">20</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <a href="?logout=1" class="bg-red-500 hover:bg-red-600 text-white px-4 py-2 rounded-lg">退出</a>
                </div>
            </div>

            <?php if (isset($error)) echo "<p class='text-red-500 mb-4'>" . htmlspecialchars($error) . "</p>"; ?>

            <!-- 查询表单（绑定 AJAX） -->
            <form id="query-form" class="space-y-4 mb-6">
                <div>
                    <label class="block mb-1 font-medium">选择 SQL 模板</label>
                    <select name="template" class="w-full border rounded-lg px-3 py-2"
                        onchange="updateQueryParams(this)" id="template-select">
                        <?php foreach ($config['sql_templates'] as $tpl): ?>
                            <option value="<?= htmlspecialchars($tpl['query']) ?>"
                                data-params="<?= htmlspecialchars(json_encode($tpl['query_params'])) ?>">
                                <?= htmlspecialchars($tpl['name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div>
                    <label class="block mb-1 font-medium">参数</label>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-2" id="query-params"></div>
                </div>
                <div class="flex space-x-2">
                    <button type="submit" name="run_query" id="query-btn"
                        class="bg-green-500 hover:bg-green-600 text-white px-4 py-2 rounded-lg flex items-center justify-center">
                        <span class="btn-text">执行查询</span>
                        <span class="btn-loading hidden ml-2 animate-spin border-2 border-white border-t-transparent rounded-full w-4 h-4"></span>
                    </button>

                    <button type="button" id="export-btn" onclick="exportCSV()"
                        class="bg-yellow-500 hover:bg-yellow-600 text-white px-4 py-2 rounded-lg flex items-center justify-center">
                        <span class="btn-text">导出 CSV</span>
                        <span class="btn-loading hidden ml-2 animate-spin border-2 border-white border-t-transparent rounded-full w-4 h-4"></span>
                    </button>
                </div>
            </form>

            <!-- 结果与分页 -->
            <div id="query-result" class="overflow-x-auto mt-4"></div>
            <div id="pagination" class="flex flex-wrap gap-2 mt-4"></div>
        </div>
    <?php endif; ?>
</body>

<script>
    /** ---------- 参数表单渲染 ---------- **/
    function updateQueryParams(select) {
        const selectedOption = select.options[select.selectedIndex];
        const queryParams = selectedOption.getAttribute('data-params');
        const container = document.getElementById("query-params");
        if (queryParams) {
            renderForm(JSON.parse(queryParams || '{}'), container);
        }
    }

    window.addEventListener("DOMContentLoaded", () => {
        const select = document.getElementById("template-select");
        if (select) updateQueryParams(select);

        // 绑定查询表单 AJAX
        const form = document.getElementById("query-form");
        if (form) {
            form.addEventListener("submit", function(e) {
                e.preventDefault();
                runQuery(1); // 每次点击“执行查询”从第1页开始
            });
        }
    });

    function renderForm(config, container) {
        container.innerHTML = '';
        Object.entries(config).forEach(([fieldName, fieldConfig]) => {
            let fieldElement;
            switch (fieldConfig.type) {
                case "input":
                    fieldElement = buildTextInput(fieldName, fieldConfig.description);
                    break;
                case "date":
                    fieldElement = buildDateInput(fieldName, fieldConfig.description);
                    break;
                case "select":
                    fieldElement = buildSelectInput(fieldName, fieldConfig.description, fieldConfig.options);
                    break;
                default:
                    console.warn(`未知字段类型: ${fieldConfig.type}`);
                    return;
            }
            container.appendChild(fieldElement);
        });
    }

    function buildTextInput(name, labelText) {
        const label = document.createElement("label");
        label.className = "flex items-center mb-4";
        const span = document.createElement("span");
        span.className = "mr-2";
        span.textContent = labelText;
        label.appendChild(span);
        const input = document.createElement("input");
        input.type = "text";
        input.name = `params[${name}]`;
        input.placeholder = labelText;
        input.className = "flex-1 border rounded-lg px-3 py-2";
        label.appendChild(input);
        return label;
    }

    function buildDateInput(name, labelText) {
        const label = document.createElement("label");
        label.className = "flex items-center mb-4";
        const span = document.createElement("span");
        span.className = "mr-2";
        span.textContent = labelText;
        label.appendChild(span);
        const input = document.createElement("input");
        input.type = "datetime-local";
        input.name = `params[${name}]`;
        input.className = "flex-1 border rounded-lg px-3 py-2";
        label.appendChild(input);
        return label;
    }

    function buildSelectInput(name, labelText, options) {
        const label = document.createElement("label");
        label.className = "flex items-center mb-4";
        const span = document.createElement("span");
        span.className = "mr-2";
        span.textContent = labelText;
        label.appendChild(span);
        const select = document.createElement("select");
        select.name = `params[${name}]`;
        select.className = "flex-1 border rounded-lg px-3 py-2";
        const placeholderOption = document.createElement("option");
        placeholderOption.value = "";
        placeholderOption.disabled = true;
        placeholderOption.selected = true;
        placeholderOption.textContent = labelText;
        select.appendChild(placeholderOption);
        (options || []).forEach(option => {
            const opt = document.createElement("option");
            opt.value = option.value;
            opt.textContent = option.label;
            select.appendChild(opt);
        });
        label.appendChild(select);
        return label;
    }

    /** ---------- AJAX 查询 + 分页 ---------- **/
    let currentPage = 1;

    function runQuery(page = 1) {
        const form = document.getElementById("query-form");
        const pageSizeSelect = document.getElementById("page-size");
        const pageSize = parseInt(pageSizeSelect?.value || "10", 10);

        const formData = new FormData(form);
        formData.append("page", page);
        formData.append("pageSize", pageSize);

        const resultEl = document.getElementById("query-result");
        const pagerEl = document.getElementById("pagination");
        resultEl.innerHTML = `<div class="text-gray-500">查询中...</div>`;
        pagerEl.innerHTML = "";

        fetch("query.php", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (!data || !data.success) {
                    resultEl.innerHTML = `<p class="text-red-500">${(data && data.error) || '查询失败'}</p>`;
                    return;
                }
                if (!data.data || data.data.length === 0) {
                    resultEl.innerHTML = `<p class="text-gray-500">没有查询到数据</p>`;
                    return;
                }

                // 表格
                let html = `<table class="min-w-full border border-gray-300 text-sm">
                <thead class="bg-gray-100"><tr>`;
                Object.keys(data.data[0]).forEach(col => {
                    html += `<th class="border px-4 py-2">${escapeHtml(col)}</th>`;
                });
                html += `</tr></thead><tbody>`;
                data.data.forEach(row => {
                    html += `<tr class="hover:bg-gray-50">`;
                    Object.values(row).forEach(cell => {
                        const val = (cell === null || cell === undefined) ? "" : String(cell);
                        html += `<td class="border px-4 py-2">${escapeHtml(val)}</td>`;
                    });
                    html += `</tr>`;
                });
                html += `</tbody></table>`;
                resultEl.innerHTML = html;

                // 分页
                renderPagination(data.total, data.page, data.pageSize);
                currentPage = data.page;
            })
            .catch(() => {
                resultEl.innerHTML = `<p class="text-red-500">网络错误，请稍后重试</p>`;
            });
    }

    // 分页渲染（含省略）
    function renderPagination(total, page, pageSize) {
        const pagerEl = document.getElementById("pagination");
        pagerEl.innerHTML = "";
        const totalPages = Math.max(1, Math.ceil((total || 0) / (pageSize || 10)));

        const btn = (p, label = null, disabled = false, active = false) => {
            const el = document.createElement("button");
            el.textContent = label || p;
            el.className = `px-3 py-1 border rounded ${active ? 'bg-gray-200' : ''} ${disabled ? 'opacity-50 cursor-not-allowed' : ''}`;
            if (!disabled && !active) el.onclick = () => runQuery(p);
            return el;
        };

        // 上一页
        pagerEl.appendChild(btn(Math.max(1, page - 1), "上一页", page <= 1));

        // 页码（带省略）
        const pages = calcPageList(totalPages, page);
        pages.forEach(token => {
            if (token === '...') {
                const span = document.createElement("span");
                span.textContent = '...';
                span.className = "px-2 text-gray-500";
                pagerEl.appendChild(span);
            } else {
                pagerEl.appendChild(btn(token, null, false, token === page));
            }
        });

        // 下一页
        pagerEl.appendChild(btn(Math.min(totalPages, page + 1), "下一页", page >= totalPages));
    }

    // 生成页码数组（如 1 ... 5 6 7 ... 20）
    function calcPageList(totalPages, current) {
        const range = [];
        const delta = 1; // 当前页左右各展示 1 页
        const left = Math.max(1, current - delta);
        const right = Math.min(totalPages, current + delta);

        range.push(1);
        if (left > 2) range.push('...');
        for (let i = left; i <= right; i++) {
            if (i !== 1 && i !== totalPages) range.push(i);
        }
        if (right < totalPages - 1) range.push('...');
        if (totalPages > 1) range.push(totalPages);

        // 去重并有序
        return [...new Set(range)].sort((a, b) => (a === '...' ? 0 : a) - (b === '...' ? 0 : b));
    }

    // 简单转义
    function escapeHtml(s) {
        return s.replace(/[&<>"']/g, c => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#039;'
        } [c]));
    }

    const exportBtn = document.getElementById("export-btn");

    function exportCSV() {
        setLoading(exportBtn, true);
        const form = document.querySelector("form");
        const formData = new FormData(form);

        // 把 formData 转成 URL 参数
        const params = new URLSearchParams();
        for (const [key, value] of formData.entries()) {
            params.append(key, value);
        }
        params.append("export", "csv");
        window.location.href = "query.php?" + params.toString();
        setTimeout(() => setLoading(exportBtn, false), 1500);
    }

    function setLoading(button, isLoading) {
        const text = button.querySelector(".btn-text");
        const spinner = button.querySelector(".btn-loading");
        if (isLoading) {
            button.disabled = true;
            text.classList.add("opacity-50");
            spinner.classList.remove("hidden");
        } else {
            button.disabled = false;
            text.classList.remove("opacity-50");
            spinner.classList.add("hidden");
        }
    }

    // 切换每页条数时重查第 1 页
    document.getElementById("page-size")?.addEventListener("change", () => runQuery(1));
</script>

</html>