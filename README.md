# Data Query Tool

一款为公司内非技术同事设计的 **快速 MySQL 查询工具**，让他们不必学习 SQL 就能自助查询数据库，帮你节省重复查数据的时间。
| 构建配置 | 登录 | 查询 |
|-------|-------|-------|
| <img src="https://raw.githubusercontent.com/zxc7563598/data-query-tool/main/demo/step1.png"> | <img src="https://raw.githubusercontent.com/zxc7563598/data-query-tool/main/demo/step2.png"> | <img src="https://raw.githubusercontent.com/zxc7563598/data-query-tool/main/demo/step3.png"> |

## 初衷

在公司里，经常有运营、老板找你要数据，而很多时候一个 SQL 就能解决的问题，但同事们从来不愿意学 MySQL 或安装工具。  
于是我写了这个工具：**零框架、零依赖、直接访问网页即可**，让同事们自己查数据，你专心写代码。

## 特性

- 纯 PHP 实现，无需安装额外框架或前后端分离
- 直接访问 `index.php` 即可使用
- 可通过 `php -S 0.0.0.0:8000` 在局域网共享访问
- 支持将常用 SQL 配置到 `config.json`，同事点击就能查询
- 内网部署，安全可靠

## 安装与使用

1. 将仓库代码放到公司服务器或者本机内网环境
   ```bash
    git clone https://github.com/zxc7563598/data-query-tool
   ```

2. 启动 PHP 内置服务器：

    ```bash
    php -S 0.0.0.0:8000
    ```

打开浏览器访问 http://服务器IP:8000

> 首次访问会要求配置数据库等信息，并生成 `config.json`

> 后续可以通过直接修改 `config.json` 来调整 SQL 模板，或直接删掉 `config.json` 后重新访问进行配置

在 config.json 中配置常用 SQL，让同事直接选择执行

SQL 模板格式:

```
{
    "name": "模板名称",
    "query": "SQL语句，如果有参数，用 {{}} 包裹",
    "query_params": { ## 参数，用于替换SQL中的 {{}}
        "参数名称":{ ## 应该跟 {{}} 中的内容相等
            "type": "input", ## 参数类型：input-文本输入框；date-日期选择框；select-下拉列表选择
            "options": [ ## 下拉列表选择框，仅 type 为 select 时生效
                {
                    "value": "5", ## 数值
                    "label": "拒绝签约" ## 展示内容
                }
            ],
            "description": "参数描述（展示在页面上的参数名称）"
        }
    }
}
```

config.json 示例：

```json
"sql_templates": [
    {
        "name": "查询所有用户",
        "query": "SELECT user_id as '用户ID',phone as '手机号',real_name as '姓名',FROM_UNIXTIME(created_at) as '创建时间' from ch_users where deleted_at is null",
        "query_params": {}
    },
    {
        "name": "根据手机号查询用户",
        "query": "SELECT user_id as '用户ID',phone as '手机号',real_name as '姓名',FROM_UNIXTIME(created_at) as '创建时间' from ch_users where phone = {{phone}} and deleted_at is null",
        "query_params": {
            "phone": {
                "type": "input",
                "description": "手机号"
            }
        }
    },
    {
        "name": "根据注册时间查询用户",
        "query": "SELECT user_id as '用户ID',phone as '手机号',real_name as '姓名',FROM_UNIXTIME(created_at) as '创建时间' from ch_users where created_at >= UNIX_TIMESTAMP({{start_time}}) and created_at <= UNIX_TIMESTAMP({{end_time}}) and deleted_at is null",
        "query_params": {
            "start_time": {
                "type": "date",
                "description": "开始时间"
            },
            "end_time": {
                "type": "date",
                "description": "结束时间"
            }
        }
    },
    {
        "name": "根据订单状态搜索订单信息",
        "query": "SELECT borrow_sn as '订单号',real_name as '姓名',borrow_amount as '合同金额',FROM_UNIXTIME(created_at) as '签约时间' from ch_borrows where `status` = {{status}} and deleted_at is null",
        "query_params": {
            "status": {
                "type": "select",
                "options": [
                    {
                        "value": "5",
                        "label": "拒绝签约"
                    },
                    {
                        "value": "6",
                        "label": "取消签约"
                    },
                    {
                        "value": "9",
                        "label": "签约失败"
                    },
                    {
                        "value": "11",
                        "label": "已签约"
                    },
                    {
                        "value": "12",
                        "label": "已完成"
                    }
                ],
                "description": "订单状态"
            }
        }
    }
]
```


## 注意事项  

- 建议只在公司内网环境使用，不建议直接暴露到公网，以免不必要的数据泄露风险
- 所有配置都可以在 `config.json` 中修改
- 对 SQL 权限请谨慎控制，避免误操作，建议只执行查询语句，不要对数据进行变更
