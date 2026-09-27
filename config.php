<?php
/*
 * Copyright (C) 2026 空雨不流泪 <https://www.kybll.cn/>
 *
 * @author  空雨不流泪
 * @link    https://www.kybll.cn/
 * @license GPL-3.0-or-later
 * @description 阿里云号码认证服务（PNVS / dypnsapi）短信验证码接口配置
 *
 * 模板使用号码认证控制台的「赠送签名 + 赠送模板」。
 * 验证码由 V10 系统生成（6位数字/5分钟）并校验，插件负责下发。
 */

return [
    'access_key_id' => [
        'title' => 'AccessKey ID',
        'type'  => 'text',
        'value' => '',
        'tip'   => 'RAM 用户 AccessKeyId，需授予 dypns:SendSmsVerifyCode 权限',
    ],
    'access_key_secret' => [
        'title' => 'AccessKey Secret',
        'type'  => 'password',
        'value' => '',
        'tip'   => 'RAM 用户 AccessKeySecret',
    ],
    'sign_name' => [
        'title' => '短信签名',
        'type'  => 'select',
        'value' => '恒创联众',
        'tip'   => '选择已通过的赠送签名；选「自定义」则在下方填写',
        // 注意：V10 后台 select 提交的是数组的「值」、显示的是「键」，故此处键与值必须写成相同
        'options' => [
            '恒创联众'     => '恒创联众',
            '恒创联众科技' => '恒创联众科技',
            '北京恒创联众' => '北京恒创联众',
            '恒锐创岳'     => '恒锐创岳',
            '恒锐创岳科技' => '恒锐创岳科技',
            '北京恒锐创岳' => '北京恒锐创岳',
            '自定义'       => '自定义',
        ],
    ],
    'sign_name_custom' => [
        'title' => '自定义签名',
        'type'  => 'text',
        'value' => '',
        'tip'   => '仅当上方选择「自定义」时生效',
    ],
    'template_code' => [
        'title' => '模板CODE',
        'type'  => 'text',
        'value' => '100001',
        'tip'   => '赠送模板CODE，默认 100001（登录/注册）',
    ],
    'tips' => [
        'title' => '说明',
        'type'  => 'tip',
        'value' => '装好后请到「短信模板」点「提交审核」，把验证码模板置为「已通过」，否则无法发送。',
        'tip'   => '',
        'attribute' => 'disabled',
    ],
];
