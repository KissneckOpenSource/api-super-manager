# ApiSuperManager

## 前端页面
ApiSuperManager1.0是一个前后端完全分离的项目，前端采用Vue构建，如需要可视化配置的请移步：[ApiSuperManager-WEB](https://gitee.com/kissneck-open-source-group/api-super-manager-web)


## 快速安装

> 第一步：获取项目代码

```
获取基础代码 git clone https://gitee.com/kissneck-open-source-group/api-super-manager.git   再使用composer安装 composer install
```

> 第二步：检测环境以及配置数据库

```
创建数据，导入根目录admin.sql，在config/database.php配置数据库连接信息
```

> 第三步：完成数据迁移

```
先搭建接口项目运行目录选择public，再配置前端代码接口请求域名并搭建，访问前端域名即可
```

## 灵 感

我是一个后台开发程序员，每天都在跟各种API打交道，之前对于API的管理包括和前端的交互等都是通过文档来进行，慢慢的就有点力不从心，于是就有了接触API管理工具的契机。

最初我们使用了apiadmin，也是apiadmin给了我们灵感和方向，随着后续需求的增多，我们越发的想要自己创建一个可以多功能更简洁的管理API的工具，想法越来越多，于是**ApiSuperManager**就诞生了～

## 愿 景

> 希望ApiSuperManager能给大家开发带来便利，能帮助到大家。也希望ApiSuperManager在大家共同的努力下会越来越优化，成为更好更便捷的开发工具。

## 项目简介

**系统需求**

- PHP >= 7.3.0
- MySQL >= 5.5.3
- Redis

**项目构成**

- ThinkPHP v6.0.*
- Vue 2.*
- ...

**功能简介**
 1. 模拟接口请求
 2. 自动生成接口文档
 3. 接口权限管理
 4. 接口输入参数自动检查
 5. 接口输出参数数据类型自动规整
 6. 灵活的参数规则设定
 7. 支持三方Api无缝融合
 8. ...

 ```
 ApiSuperManager（PHP部分）
 ├─ 系统管理
 |  ├─ 菜单维护 - 编辑访客权限，处理菜单父子关系，被权限系统依赖（极为重要）
 |  ├─ 用户管理 - 添加新用户，封号，删号以及给账号分配权限组
 |  ├─ 权限管理 - 权限组管理，给权限组添加权限，将用户提出权限组
 |  └─ 日志管理 - 记录管理员的操作，用于追责，回溯和备案
 ├─ 应用接入
 |  ├─...
 ├─ 接口管理
 |  ├─ 接口维护 - 新增接口、编辑接口和接口的参数管理等
 |  ├─ 接口分组 - 将所使用的接口分组，便于更好的管理和展示
 |  ├─ 接口文档
 |   |  ├─ 接口文档生成
 |   |  ├─ 模拟接口请求
 |  ...
 ```

**页面截图**

![主页面](https://images.gitee.com/uploads/images/2021/1118/182143_0a8f5db0_9992165.png "index.png")
![输入图片说明](https://images.gitee.com/uploads/images/2021/1118/182753_1151785c_9992165.png "auth.png")
![输入图片说明](https://images.gitee.com/uploads/images/2021/1118/182721_895a1af5_9992165.png "apimanage_index.png")
![输入图片说明](https://images.gitee.com/uploads/images/2021/1118/182839_5127067c_9992165.png "md_explain.png")
![输入图片说明](https://images.gitee.com/uploads/images/2021/1118/182646_56007203_9992165.png "md_post.png")
![输入图片说明](https://images.gitee.com/uploads/images/2021/1118/182822_b90926b4_9992165.png "md_code.png")
![输入图片说明](https://images.gitee.com/uploads/images/2021/1118/184140_1c487ec6_9992165.png "md.png")



**项目特性**

- 开放源码
- 保持生机
- 不断更新
- 响应市场

**开源，我们在努力！**

## 加入我们

ApiSuperManager目前才刚刚起步还有很多不足和需要改进的地方，在这里我们诚挚邀请大家一起加入我们，给我们更多的意见和建议，相信在大家的努力下，我们会做的更好，为开源贡献自己微薄的力量。

官方QQ群：589207665

