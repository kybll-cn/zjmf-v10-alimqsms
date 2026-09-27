# 更新日志

本项目的所有重要变更都会记录在此文件。

格式参考 [Keep a Changelog](https://keepachangelog.com/zh-CN/1.1.0/)，
版本号遵循 [语义化版本](https://semver.org/lang/zh-CN/)。

## [1.0.0] - 2026-09-27

首个公开版本。

### 新增

- 实现智简魔方 V10 的 `sms` 插件契约：`install` / `uninstall` / `getCnTemplate` /
  `createCnTemplate` / `putCnTemplate` / `deleteCnTemplate` / `sendCnSms`
- 对接阿里云号码认证服务 PNVS（`dypnsapi`）的 `SendSmsVerifyCode` 接口
- 内置阿里云 RPC 风格 HMAC-SHA1 签名实现
- 附带 `checkSmsVerifyCode()` 方法（对应阿里云 `CheckSmsVerifyCode`），供二次开发使用
- 后台配置：AccessKey ID / Secret、短信签名（下拉 + 自定义兜底）、模板 CODE
- 安装时自动写入默认验证码模板；提交审核时自动回填赠送模板 CODE（`100001`）
- 阿里云错误码中文映射，便于定位发送失败原因

### 说明

- 采用「系统生成 + 系统校验」模式：验证码由 V10 内核 `VerificationCodeLogic` 生成并校验，
  插件只负责下发。未采用 PNVS 原生的 `##code##` 方案，因为那会导致 V10 侧无法校验。
- 仅支持国内号码（`CountryCode=86`），这是 PNVS 自身的限制。
- 仅实现 `sendCnSms`，插件被 V10 识别为国内短信接口（`sms_type = 0`）。

[1.0.0]: https://github.com/kybll-cn/zjmf-v10-alimqsms/releases/tag/v1.0.0
