# 智简魔方 V10 · 阿里云号码认证短信插件（AliyunPnvs）

[![License: GPL v3](https://img.shields.io/badge/License-GPLv3-blue.svg)](https://www.gnu.org/licenses/gpl-3.0)
![PHP](https://img.shields.io/badge/PHP-%3E%3D7.4-777BB4.svg)
![idcsmart](https://img.shields.io/badge/idcsmart-V10-1E88E5.svg)
![version](https://img.shields.io/badge/version-v1.0.0-success.svg)

面向**智简魔方（idcsmart）V10** 业务系统的短信接口插件，对接**阿里云号码认证服务 PNVS**（`dypnsapi`）的短信验证码接口。

> 作者：**空雨不流泪** · 官网 <https://www.kybll.cn/>

---

## 目录

- [一、这是什么](#一这是什么)
- [二、功能特性](#二功能特性)
- [三、环境要求](#三环境要求)
- [四、安装](#四安装)
- [五、后台配置](#五后台配置)
- [六、绑定到「发送设置」](#六绑定到发送设置)
- [七、工作原理](#七工作原理)
- [八、与官方-dysmsapi-插件的区别](#八与官方-dysmsapi-插件的区别)
- [九、常见报错-faq](#九常见报错-faq)
- [十、目录结构](#十目录结构)
- [十一、开发与发版](#十一开发与发版)
- [十二、更新日志](#十二更新日志)
- [十三、授权协议](#十三授权协议)
- [十四、免责声明](#十四免责声明)

---

## 一、这是什么

阿里云有两条短信产品线，能力与限制完全不同：

| | 普通短信 `dysmsapi` | 号码认证 `dypnsapi`（本插件） |
|---|---|---|
| 签名 | 自定义，需报备审核 | **只能用控制台「赠送签名」** |
| 模板 | 自定义，需审核 | **只能用控制台「赠送模板」**，无增删改接口 |
| 计费 | 按条计费 | 验证码类有免费赠送额度 |
| 参数 | `TemplateParam` 可选 | `TemplateParam` **必填** |

市面上 V10 的阿里云短信插件基本都是基于 `dysmsapi` 的，而本插件面向 `dypnsapi`，适合**只想跑验证码、用赠送额度、不想走签名模板报备流程**的场景。

因为两条产品线差异很大，本插件**不是**在官方 `Aliyun` 插件上改几个参数，而是按 PNVS 的接口契约重新实现的。

## 二、功能特性

- 完整实现智简魔方 V10 的 `sms` 插件契约（模板四方法 + 发送方法），装完即被系统识别
- 覆盖 `SendSmsVerifyCode`，并按 V10 内核机制适配验证码的生成与校验
- 内置阿里云 RPC 签名（HMAC-SHA1），与官方 `dysmsapi` 插件同一套签名实现
- 后台签名下拉选择 + 自定义兜底，赠送签名开箱可用
- 安装时自动写入默认验证码模板，自动回填赠送模板 CODE（`100001`）
- 附带阿里云错误码中文映射，发送失败时能直接看出原因
- 额外提供 `checkSmsVerifyCode()`（阿里云侧核验），供二次开发使用

## 三、环境要求

| 项 | 要求 |
|---|---|
| 业务系统 | 智简魔方 V10（已在 **10.7.2** 上核对源码契约） |
| PHP | >= 7.4，需 `curl`、`json`、`hash` 扩展 |
| 阿里云 | 已开通**号码认证服务**，控制台可见「赠送签名」「赠送模板」 |
| 账号 | 一个 RAM 用户 AccessKey，授权 `dypns:SendSmsVerifyCode` |

> 本插件只实现 `sendCnSms`，**仅支持国内号码**（`CountryCode=86`）。这是 PNVS 自身的限制，不是插件的。

## 四、安装

插件目录名必须是 `aliyun_pnvs`——V10 通过目录名 `parse_name($dir, 1)` 转大驼峰得到类名 `AliyunPnvs`，目录名改了类就加载不到。

### 方式一：下载 Release 包（推荐）

到 [Releases](https://github.com/kybll-cn/zjmf-v10-alimqsms/releases) 下载 `AliyunPnvs-1.0.0.zip`，解压到站点根目录的 `public/plugins/sms/` 下，得到：

```
public/plugins/sms/aliyun_pnvs/
├── AliyunPnvs.php
├── config.php
├── LICENSE
├── README.md
└── config/
    └── smsTemplate.php
```

### 方式二：从源码克隆

```bash
git clone https://github.com/kybll-cn/zjmf-v10-alimqsms.git aliyun_pnvs
```

注意 `git clone` **必须指定目标目录名为 `aliyun_pnvs`**（上面命令末尾的参数），否则目录会是仓库名，V10 加载不到插件。

### 装完后的三步

1. 后台「应用管理 → 短信接口」刷新列表 → 找到**阿里云号码认证短信** → **安装** → 启用；
2. 填写插件配置（见[第五节](#五后台配置)）并保存；
3. 到「短信模板」点一次**提交审核**，把验证码模板置为**已通过**。

> **为什么要点"提交审核"？**
> V10 要求短信模板 `status == 2`（已通过）才允许发送，而安装时写入的模板是 `status = 0`，必须提交一次。
> 本插件的 `createCnTemplate()` 走的是免审核流程——调用后把赠送模板 CODE（`100001`）回填到插件配置，并直接返回 `template_status = 2`。因为 PNVS 的赠送模板本身已在阿里云侧审核通过，不需要再走一遍报备。

## 五、后台配置

共 5 项配置 + 1 条说明：

| 配置项 | 类型 | 说明 |
|---|---|---|
| AccessKey ID | text | RAM 用户 AccessKeyId，**需授权 `dypns:SendSmsVerifyCode`** |
| AccessKey Secret | password | RAM 用户 AccessKeySecret |
| 短信签名 | select | 下拉选择已通过的赠送签名，列表外可选「自定义」（默认「恒创联众」） |
| 自定义签名 | text | 仅当上方选「自定义」时生效 |
| 模板CODE | text | 赠送模板 CODE，已预填 `100001` |

**写死在代码里、无需配置的项**：`CountryCode=86`、验证码变量名 `code`、有效期变量名 `min`、有效期 5 分钟、6 位纯数字。这些要么是 PNVS 的固定约束，要么由 V10 内核决定，暴露成配置项只会增加误配风险。

### 赠送签名

恒创联众 / 恒创联众科技 / 北京恒创联众 / 恒锐创岳 / 恒锐创岳科技 / 北京恒锐创岳 / 自定义

> **维护提醒（重要）**：V10 后台的 `select` 控件是 `:value="数组值" :label="数组键"`，
> 而服务端 `PluginModel::settingPost()` 校验的是「提交值 ∈ `array_keys(options)`」。
> 两者叠加的结果是——**`config.php` 里 select 的 `options` 键必须与值相同**，
> 否则保存时报 `xxx取值范围:...`。所以写法是 `'恒创联众' => '恒创联众'`；改选项时键和值要一起改。

### 赠送模板

| CODE | 场景 |
|---|---|
| 100001 | 登录 / 注册 |
| 100002 | 修改绑定手机号 |
| 100003 | 重置密码 |
| 100004 | 绑定新手机号 |
| 100005 | 验证绑定手机号 |

五个模板的文案一致：

```
您的验证码为${code}。尊敬的客户，以上验证码${min}分钟内有效，请注意保密，切勿告知他人。
```

**同时只能启用 1 个**：V10 的验证码动作只有一个（`name = code`），登录 / 注册 / 改手机 / 重置密码共用同一个模板位，所以 100001~100005 只能选其一。默认 `100001` 文案最通用。

## 六、绑定到「发送设置」

装完插件还不够，必须到后台显式绑定，否则发送时会报**短信发送动作未开启**。

路径：后台左侧菜单 **「系统 → 发送设置」**（页面文件 `notice_send.htm`）。

找到「验证码」这一行，配置三处：

1. **把「短信」开关打开** —— 不开就是 `sms_enable = 0`，直接抛「短信发送动作未开启」；
2. **「短信接口」改成「阿里云号码认证短信」** —— 出厂默认指向官方 `Idcsmart` 插件，不改永远走不到本插件；
3. **「短信模板」绑定**刚才置为「已通过」的那个验证码模板。

如果还要发产品类通知（开通 / 续费 / 暂停等），同样在这个页面逐个动作开短信开关。这类动作除了动作级开关，**还会被「产品通知分组」的开关再拦一次**，两处都要打开。

## 七、工作原理

### 关键约束：V10 的验证码由系统自己生成和校验

V10 的短信插件协议**只有发送、没有核验**。`SmsLogic::smsMethods()` 只会调用这五个方法：

```
getCnTemplate / createCnTemplate / putCnTemplate / deleteCnTemplate / sendCnSms
```

验证码由内核 `VerificationCodeLogic` 用 `mt_rand(100000, 999999)` 生成、存进 Cache（5 分钟），再由 V10 自己比对。插件拿不到也不需要"核验"这个入口。

### 于是产生了这个设计取舍

PNVS 的招牌玩法是传 `##code##` 占位符，让**阿里云生成并核验**验证码（核验免费）。但如果这么做，验证码就只存在于阿里云侧，V10 的 Cache 里没有这个值，**系统校验必然失败**。

所以本插件采用「系统生成 + 系统校验」模式：

```
V10 生成 code (6 位数字)
        │
        ▼
SmsLogic::send() → 插件 sendCnSms()
        │
        ▼
SendSmsVerifyCode(
    PhoneNumber   = 138xxxxxxxx,
    SignName      = 恒创联众,
    TemplateCode  = 100001,
    TemplateParam = {"code":"123456","min":"5"},   ← 把真实验证码传过去
    CountryCode   = 86
)
        │
        ▼
用户收到短信并输入验证码
        │
        ▼
V10 比对 Cache 中的值 → 通过 / 失败
```

`checkSmsVerifyCode()` 作为额外能力保留在插件里（对应阿里云的 `CheckSmsVerifyCode`），供二次开发使用；主流程不依赖它。

## 八、与官方 dysmsapi 插件的区别

| 维度 | 官方 `Aliyun` 插件 | 本插件 `AliyunPnvs` |
|---|---|---|
| 接口域名 | `dysmsapi.aliyuncs.com` | `dypnsapi.aliyuncs.com` |
| Action | `SendSms` | `SendSmsVerifyCode` |
| API 版本 | `2017-05-25` | `2017-05-25` |
| 签名 | 自定义，需报备 | 只能用赠送签名 |
| 模板管理 | 支持增删改查 | 无接口，只能用赠送模板 |
| `TemplateParam` | 可选 | 必填 |
| 号码范围 | 支持国际 | 仅国内 |
| 适用场景 | 全量业务通知 | 验证码，走免费赠送额度 |

## 九、常见报错 FAQ

| 报错 | 抛出位置 | 原因与处理 |
|---|---|---|
| 短信发送动作未开启 | `SmsLogic.php:92` | 该动作 `notice_setting.sms_enable = 0`。到「系统 → 发送设置」打开对应行的短信开关。注意：若该动作在 `notice_setting` 表里**没有行**，`indexSetting()` 会返回 `sms_enable = 0`，现象一样。 |
| 短信发送动作未开启（产品类动作） | `SmsLogic.php:114` | 动作级开关已开，但「产品通知分组」里未启用该动作，两处都要开。 |
| 国内短信发送接口未设置 | `SmsLogic.php` | 「发送设置」里没选短信接口。 |
| 国内短信发送模板未设置 | `SmsLogic.php` | 没绑定模板。 |
| 国内短信接口已禁用 / 未安装 | `NoticeSettingModel.php:216-220` | 插件被停用或目录缺失。 |
| 国内短信模板不存在 | `NoticeSettingModel.php:224` | 绑定的模板不属于当前接口，或状态不是「已通过」。 |
| 短信签名不能为空 | `AliyunPnvs.php` | 选了「自定义」但没填自定义签名。 |
| `xxx取值范围:...` | `PluginModel.php:755` | 见[第五节的 select 维护提醒](#赠送签名)。 |
| 模板CODE不能为空 | `AliyunPnvs.php` | 插件配置里没填模板 CODE。 |
| 手机号格式错误 | `AliyunPnvs.php` | 非 11 位国内号码，或带了无法识别的区号前缀。 |

阿里云侧返回的错误码（如 `BUSINESS_LIMIT_CONTROL`、`MOBILE_NUMBER_ILLEGAL`、`FUNCTION_NOT_OPENED`）会被翻译成中文提示，同时保留原始 `Code` / `Message` 便于排查。

## 十、目录结构

```
zjmf-v10-alimqsms/          # 本仓库根目录 = 插件本体
├── AliyunPnvs.php          # 插件主类
├── config.php              # 后台配置表单定义
├── config/
│   └── smsTemplate.php     # 安装时写入的默认短信模板
├── build.py                # 打包脚本，生成 Release 用的 zip
├── README.md               # 本文件
├── CHANGELOG.md            # 更新日志
├── LICENSE                 # GPL-3.0
├── .gitignore
└── .gitattributes
```

### 主类方法一览

| 方法 | 可见性 | 作用 |
|---|---|---|
| `install()` | public | 读取 `config/smsTemplate.php`，写入默认模板 |
| `uninstall()` | public | 卸载钩子（无数据需要清理） |
| `getCnTemplate()` | public | 返回国内模板，`template_status = 2` |
| `createCnTemplate()` | public | 回填 `template_code`，返回 `template_status = 2` |
| `putCnTemplate()` | public | 编辑模板，返回 `template_status = 2` |
| `deleteCnTemplate()` | public | 删除模板（空实现，赠送模板不可删） |
| `sendCnSms()` | public | 组装参数并调用 `SendSmsVerifyCode` |
| `checkSmsVerifyCode()` | public | 调用 `CheckSmsVerifyCode`，二次开发用 |
| `request()` | private | RPC 签名 + 发起请求 |
| `buildTemplateParamArray()` | private | 组装 `{"code":"","min":"5"}` |
| `resolveSignName()` | private | 解析签名（下拉值 / 自定义兜底） |
| `formatMobile()` | private | 手机号规整与校验 |
| `percentEncode()` / `curlPost()` / `templateParam()` / `errorMsg()` / `errorCode()` | private | 签名编码、HTTP 请求、内容替换、错误映射 |

## 十一、开发与发版

### 打包

`build.py` 会把插件打成可**直接解压到 `public/plugins/sms/`** 的 zip（内部根目录为 `aliyun_pnvs/`）：

```bash
python build.py            # 输出 dist/AliyunPnvs-1.0.0.zip
```

版本号取自 `AliyunPnvs.php` 里的 `$info['version']`，改版本只需改一处。

### 为什么 zip 里要多套一层 `aliyun_pnvs/`

因为仓库根目录就是插件本体（便于直接阅读和 diff），但 V10 要求安装目录名为 `aliyun_pnvs`。打包时补上这一层，用户解压即得正确目录名。

### 发版流程

```bash
# 1. 改 AliyunPnvs.php 的 $info['version'] 与 CHANGELOG.md
# 2. 提交
git add -A && git commit -m "chore: release v1.0.1"
# 3. 打 tag 并推送
git tag v1.0.1 && git push origin main --tags
# 4. 打包并创建 Release
python build.py
gh release create v1.0.1 dist/AliyunPnvs-1.0.1.zip --title "v1.0.1" --notes-file CHANGELOG.md
```

### 编码约定

- 所有文本文件使用 **UTF-8（无 BOM）**、**LF** 换行（见 `.gitattributes`）
- 代码风格与 V10 源码保持一致：4 空格缩进，命名空间 `sms\aliyun_pnvs`
- 修改 `config.php` 的 select 选项时，**务必保持键与值相同**（原因见第五节）

## 十二、更新日志

见 [CHANGELOG.md](CHANGELOG.md)。

## 十三、授权协议

本项目采用 **GNU General Public License v3.0** 发布，全文见 [LICENSE](LICENSE)。

```
Copyright (C) 2026 空雨不流泪  <https://www.kybll.cn/>

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 3 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
```

简单来说：你可以自由使用、修改、再分发本插件（含商业用途），但**必须保留原作者署名与版权声明**；且基于本插件产生的衍生作品，也必须以 GPL-3.0 开源。

## 十四、免责声明

- 本插件为第三方独立开发，与阿里云、智简魔方官方无隶属关系，未获得任何官方背书。
- 插件按「现状」提供，作者不对因使用本插件造成的业务中断、短信费用、数据丢失等承担任何责任。
- 短信发送涉及真实费用与运营商合规要求，请在上线前自行充分测试。
- 请遵守阿里云号码认证服务的使用条款，不得用于发送骚扰、营销或任何违法内容。
