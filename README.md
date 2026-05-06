# HZNUOJ

[![GitHub 发布版本][gh-release-badge]][gh-release]

**HZNUOJ 是基于 [HUSTOJ](https://github.com/zhblue/hustoj) 改造而来的，遵循 GPL 协议开源**

## 部署指南

### 构建镜像

在仓库根目录下：

```bash
docker build -t hznuoj:latest -f docker/Dockerfile ./
```

等待构建完成即可。

完成后 `docker image ls`，若有看到 hznuoj 的镜像即为成功。

如果不想自行构建，也可以直接拉取已经编译好的镜像：

```bash
docker pull hznuoj/hznuoj:latest
```

其中，`latest` 表示镜像标签，可以指定其他标签，比如 `0.0.3`。

### 启动容器

#### 数据库

首先需要启动一个数据库，用 MySQL 或者 MariaDB 都可以，这里以 MySQL 5.7 为例：

```bash
docker run \
    -d \
    --restart=always \
    --name="mysql" \
    --hostname="mysql" \
    -e MYSQL_ROOT_PASSWORD=root \
    -e TZ=Asia/Shanghai \
    -p 3306:3306 \
    -v /var/docker-data/mysql-5.7/data:/var/lib/mysql \
    mysql:5.7 \
    --character-set-server=utf8mb4 \
    --collation-server=utf8mb4_unicode_ci
```

然后可以使用本仓库里的 [SQL](./scripts/db.sql) 文件来创建库和表。

#### 网页服务

```bash
docker run \
    -d -it \
    --name=hznuoj \
    --restart=always \
    -p 80:80 \
    -v /var/hznuoj/static.php:/var/www/web/OJ/include/static.php \
    -v /var/hznuoj/upload:/var/www/web/OJ/upload \
    -v /var/hznuoj/data:/var/hznuoj/data \
    hznuoj/hznuoj:latest
```

- `-p 80:80` 表示把容器的 80 端口映射到宿主机的 80 端口，可自行修改
- `--name=hznuoj` 表示指定容器的名字为 `hznuoj`
- 路径挂载：
  - 因为有些文件或者目录在容器运行过程中可能会有变动，所以需要把它们放在外部，然后挂载到容器里面。不然容器一重启，容器里面的文件都会恢复成初始状态
  - `-v /var/hznuoj/static.php:/var/www/web/OJ/include/static.php` 表示将宿主机上的 `/var/hznuoj/static.php` 文件挂载到容器内的 `/var/www/web/OJ/include/static.php`
    - 本仓库下有一个 [`static.example.php`](./web/OJ/include/static.example.php)，应该只需要改一下数据库相关的变量，然后把文件挂载到容器中，就可以用了
    - 需要注意的是，宿主机的部分是可以改动的
      - 比如，如果把 `static.php` 放在 `/opt` 路径下，那么可以写成 `-v /opt/static.php:/var/www/web/OJ/include/static.php`
    - 容器内的路径不要变动，而且也没有变动的必要
  - `upload` 目录是用户上传的文件内容，比如题面里面的图片
  - `data` 目录是题目数据的目录
  - 如果是想开发的话，可以把仓库克隆下来之后，把 `web` 目录挂载进去，容器里的路径应该是 `/var/www/web`，然后就可以在容器外部修改 `web` 目录下的文件，改动会在容器中的实例实时生效

然后访问 `localhost:80` 即可。

### 进入容器

```bash
docker exec -it hznuoj bash
```

## 使用教程

默认管理员账号为 admin/@Hznu666。

出题手册见 https://www.yuque.com/weilixinlianxin/zcf10d/yfk05w

## 优势

* 更华丽的界面
* 更灵活的权限管理
* 支持多组样例
* 有封装好的 Docker 镜像，一键部署

## 界面截图

### 首页

支持提交量和访问量的统计

![首页](images/index.jpg)

### 榜单

重写过的的榜单

![榜单](images/board.jpg)

能点开查看每题的提交状况

![每题提交状况](images/board2.jpg)

### 题目编辑界面

![题目编辑界面](images/problem-edit.jpg)

多样例支持

![多样例支持](images/problem-edit2.jpg)

### 权限管理界面

细分的权限分配

![权限管理界面](images/privilege.jpg)

[gh-release-badge]: https://img.shields.io/github/release/hznuoj-dev/hznuoj.svg
[gh-release]: https://GitHub.com/hznuoj-dev/hznuoj/releases/

## 发布

* 将改动提交一个拉取请求
* 在拉取请求中，修改 [VERSION](./VERSION) 中的版本号，版本号要满足 `vx.x.x` 的格式，如果能满足 [语义化版本](https://semver.org/lang/zh-CN/) 的话更好
* 当拉取请求合并到 `master` 的时候，如果 [VERSION](./VERSION) 里的版本号不存在于 git 标签中，那么会触发版本发布，并自动构建一个 Docker 镜像

## 提示词题

### 1. 更新数据库结构

初始化新库可以直接使用 `scripts/db.sql`。
已有库可以执行 `scripts/db_change.sql` 中的增量 SQL。

- `ALTER TABLE problem ADD COLUMN problem_type ...`
- `CREATE TABLE IF NOT EXISTS prompt_submission ...`

### 2. 配置环境变量

在网页工程环境中配置：

```bash
export DEEPSEEK_API_KEY="sk-xxxx"
export DEEPSEEK_BASE_URL="https://api.deepseek.com"
export DEEPSEEK_MODEL="deepseek-v4-flash"
export DEEPSEEK_MOCK="0"
```

说明：

- `DEEPSEEK_API_KEY`：必填，未启用模拟模式时使用
- `DEEPSEEK_BASE_URL`：默认 `https://api.deepseek.com`
- `DEEPSEEK_MODEL`：默认 `deepseek-v4-flash`
- `DEEPSEEK_MOCK`：设置为 `1` 时启用模拟模式，不请求真实 DeepSeek

### 3. 创建提示词题

后台添加题目或编辑题目页面设置题目类型：

- `普通题`：默认
- `提示词题`

题目设置为提示词题后，学生提交页面会切换为提示词输入框，并隐藏语言选择和代码编辑器。

### 4. 学生提交流程

提示词题提交时：

1. 学生提交提示词
2. 后端调用 DeepSeek，或使用模拟模式
3. 提取生成的 Python 3 代码
4. 写入 `solution` + `source_code`，语言固定为 Python 3
5. 复用原有评测流程
6. 在提交详情页展示提示词、生成代码、DeepSeek 状态和提示词得分

### 5. 评分规则

- `prompt_length = mb_strlen(prompt, 'UTF-8')`
- 仅当评测通过（AC）时：`score = max(0, 100 - intdiv(prompt_length, 5))`
- 未通过评测：`score = 0`

### 6. DeepSeek 失败排查

页面提示“DeepSeek 生成代码失败，请稍后重试。”时：

1. 检查 `DEEPSEEK_API_KEY` 是否配置并有效
2. 检查 `DEEPSEEK_BASE_URL` 是否可访问
3. 检查服务器是否可以正常连接 DeepSeek
4. 在 `prompt_submission` 表中查看 `deepseek_error`，管理员可见详细错误

### 7. 本地调试（模拟模式）

将 `DEEPSEEK_MOCK` 设置为 `1` 后，会返回固定 Python 代码，不请求 DeepSeek。
可以用来验证完整流程：

- 提示词提交
- generated_code 保存
- solution/source_code 保存
- 评测状态页展示
