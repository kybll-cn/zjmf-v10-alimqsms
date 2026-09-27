<?php
/*
 * Copyright (C) 2026 空雨不流泪 <https://www.kybll.cn/>
 *
 * This program is free software: you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation, either version 3 of the License, or (at your option) any later
 * version.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT
 * ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS
 * FOR A PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program. If not, see <https://www.gnu.org/licenses/>.
 */

/**
 * 阿里云号码认证服务（PNVS / dypnsapi）短信验证码插件
 *
 * 说明：
 * - 接口域名 dypnsapi.aliyuncs.com，Action 为 SendSmsVerifyCode，Version 2017-05-25；
 * - 只能使用号码认证控制台的「赠送签名 + 赠送模板」，不支持自定义签名/模板；
 * - 验证码由 V10 生成（6位数字，缓存5分钟）并由 V10 校验，插件用 TemplateParam
 *   传真实验证码 {"code":"123456","min":"5"}；PNVS 仅支持国内号码。
 *
 * @author 空雨不流泪
 * @link   https://www.kybll.cn/
 * @see    https://help.aliyun.com/zh/pnvs/developer-reference/api-dypnsapi-2017-05-25-sendsmsverifycode
 * @see    https://help.aliyun.com/zh/pnvs/developer-reference/api-dypnsapi-2017-05-25-checksmsverifycode
 */

namespace sms\aliyun_pnvs;

use app\common\lib\Plugin;

class AliyunPnvs extends Plugin
{
    /**
     * 基础信息
     * 注意：name 必须与目录名 aliyun_pnvs 的驼峰形式一致（parse_name($dir, 1)）
     */
    public $info = [
        'name'        => 'AliyunPnvs',
        'title'       => '阿里云号码认证短信',
        'description' => '阿里云号码认证服务 PNVS（dypnsapi）短信验证码接口',
        'status'      => 1,
        'author'      => '空雨不流泪',
        'version'     => '1.0.0',
        'help_url'    => 'https://www.kybll.cn/',
    ];

    /** 接口域名 */
    private $endpoint = 'https://dypnsapi.aliyuncs.com/';

    /** 接口版本 */
    private $version = '2017-05-25';

    /** 超时秒数 */
    private $timeout = 10;

    # ------------------------------------------------------------------
    # 生命周期
    # ------------------------------------------------------------------

    /**
     * 插件安装：导入默认短信模板
     * @return array
     */
    public function install()
    {
        $smsTemplate = [];
        if (file_exists(__DIR__ . '/config/smsTemplate.php')) {
            $smsTemplate = require __DIR__ . '/config/smsTemplate.php';
        }
        return $smsTemplate;
    }

    /**
     * 插件卸载
     * @return bool
     */
    public function uninstall()
    {
        return true;
    }

    # ------------------------------------------------------------------
    # 国内模板：PNVS 使用控制台赠送模板，无需在平台创建/审核，直接视为「已通过」
    # ------------------------------------------------------------------

    /**
     * 获取国内模板状态
     * @param array $params
     * @return array
     */
    public function getCnTemplate($params)
    {
        $templateId = trim($params['template_id'] ?? '');
        if ($templateId === '') {
            return ['status' => 'error', 'msg' => '模板CODE不能为空'];
        }
        return [
            'status'   => 'success',
            'template' => [
                'template_id'     => $templateId,
                'template_status' => 2, // 赠送模板免审核，直接标记为审核通过
            ],
        ];
    }

    /**
     * 创建国内模板
     * PNVS 不支持接口创建模板，此处将配置中的「默认赠送模板CODE」回填给该模板并标记为已通过
     * @param array $params
     * @return array
     */
    public function createCnTemplate($params)
    {
        $templateCode = trim($params['template_id'] ?? '');
        if ($templateCode === '') {
            $templateCode = trim($params['config']['template_code'] ?? '');
        }
        if ($templateCode === '') {
            return [
                'status' => 'error',
                'msg'    => '请在插件配置中填写「模板CODE」（阿里云赠送模板CODE）',
            ];
        }
        return [
            'status'   => 'success',
            'template' => [
                'template_id'     => $templateCode,
                'template_status' => 2,
            ],
        ];
    }

    /**
     * 修改国内模板：赠送模板不可修改，仅回填状态
     * @param array $params
     * @return array
     */
    public function putCnTemplate($params)
    {
        return [
            'status'   => 'success',
            'template' => [
                'template_status' => 2,
            ],
        ];
    }

    /**
     * 删除国内模板：赠送模板不可删除，直接返回成功
     * @param array $params
     * @return array
     */
    public function deleteCnTemplate($params)
    {
        return ['status' => 'success'];
    }

    # ------------------------------------------------------------------
    # 国内发送
    # ------------------------------------------------------------------

    /**
     * 发送国内短信（验证码）
     * @param array $params mobile/content/config/template_id/templateParam/phone_code
     * @return array status/content/msg
     */
    public function sendCnSms($params)
    {
        $config = $params['config'] ?? [];
        // 组装模板变量 {"code":"验证码","min":"5"}
        $apiParam = $this->buildTemplateParamArray($params['templateParam'] ?? []);
        $content  = $this->templateParam($params['content'] ?? '', $apiParam);

        // ---- 参数校验 ----
        if (empty($config['access_key_id']) || empty($config['access_key_secret'])) {
            return ['status' => 'error', 'content' => $content, 'msg' => '阿里云 AccessKey ID / Secret 未配置'];
        }
        $signName = $this->resolveSignName($config);
        if ($signName === '') {
            return ['status' => 'error', 'content' => $content, 'msg' => '短信签名不能为空，请选择赠送签名或填写自定义签名'];
        }
        $templateCode = trim($params['template_id'] ?? '');
        if ($templateCode === '') {
            $templateCode = trim($config['template_code'] ?? '');
        }
        if ($templateCode === '') {
            return ['status' => 'error', 'content' => $content, 'msg' => '模板CODE不能为空，请填写阿里云赠送模板CODE'];
        }

        $mobile = $this->formatMobile($params['mobile'] ?? '');
        if ($mobile === '') {
            return ['status' => 'error', 'content' => $content, 'msg' => '手机号格式错误'];
        }

        // ---- 调用接口 ----
        $data = [
            'PhoneNumber'   => $mobile,
            'SignName'      => $signName,
            'TemplateCode'  => $templateCode,
            'TemplateParam' => json_encode($apiParam, JSON_UNESCAPED_UNICODE),
            'CountryCode'   => '86',
        ];

        $result = $this->request('SendSmsVerifyCode', $data, $config);
        if (!is_array($result)) {
            return ['status' => 'error', 'content' => $content, 'msg' => '请求阿里云接口失败，请检查服务器能否访问 dypnsapi.aliyuncs.com'];
        }

        if (($result['Code'] ?? '') === 'OK' && !empty($result['Success'])) {
            $data2 = ['status' => 'success', 'content' => $content];
            if (!empty($result['Model']['BizId'])) {
                $data2['biz_id'] = $result['Model']['BizId'];
            }
            return $data2;
        }

        return ['status' => 'error', 'content' => $content, 'msg' => $this->errorMsg($result)];
    }

    # ------------------------------------------------------------------
    # 额外能力（非 V10 短信插件标准方法）：核验验证码
    # ------------------------------------------------------------------

    /**
     * 核验验证码（CheckSmsVerifyCode）
     * V10 的验证码由系统自行校验，本方法供二次开发调用。
     *
     * @param array $params mobile / verify_code / config
     * @return array status verify_result(PASS|UNKNOWN) msg
     */
    public function checkSmsVerifyCode($params)
    {
        $config = $params['config'] ?? $this->getConfig();
        if (empty($config['access_key_id']) || empty($config['access_key_secret'])) {
            return ['status' => 'error', 'verify_result' => 'UNKNOWN', 'msg' => '阿里云 AccessKey 未配置'];
        }
        $mobile = $this->formatMobile($params['mobile'] ?? '');
        if ($mobile === '') {
            return ['status' => 'error', 'verify_result' => 'UNKNOWN', 'msg' => '手机号格式错误'];
        }
        if (empty($params['verify_code'])) {
            return ['status' => 'error', 'verify_result' => 'UNKNOWN', 'msg' => '验证码不能为空'];
        }

        $result = $this->request('CheckSmsVerifyCode', [
            'PhoneNumber' => $mobile,
            'VerifyCode'  => (string)$params['verify_code'],
            'CountryCode' => '86',
        ], $config);
        if (!is_array($result)) {
            return ['status' => 'error', 'verify_result' => 'UNKNOWN', 'msg' => '请求阿里云接口失败'];
        }
        // 接口请求成功 != 核验成功，结果仅以 Model.VerifyResult 为准
        if (($result['Code'] ?? '') === 'OK' && !empty($result['Success'])) {
            $verifyResult = $result['Model']['VerifyResult'] ?? 'UNKNOWN';
            return [
                'status'        => $verifyResult === 'PASS' ? 'success' : 'error',
                'verify_result' => $verifyResult,
                'msg'           => $verifyResult === 'PASS' ? '核验成功' : '验证码错误或已失效',
            ];
        }
        return ['status' => 'error', 'verify_result' => 'UNKNOWN', 'msg' => $this->errorMsg($result)];
    }

    # ------------------------------------------------------------------
    # 内部方法
    # ------------------------------------------------------------------

    /**
     * 组装模板参数：{"code":"验证码","min":"5"}
     * @param array $templateParam V10 传入的模板参数（含 code）
     * @return array
     */
    private function buildTemplateParamArray($templateParam)
    {
        $params = ['code' => '', 'min' => '5'];
        if (is_array($templateParam)) {
            foreach ($templateParam as $key => $value) {
                if (is_array($value) || is_object($value)) {
                    continue;
                }
                $params[$key] = (string)$value;
            }
        }
        return $params;
    }

    /**
     * 解析生效的签名
     * 配置为「下拉选择 + 自定义」：选 custom 时取自定义字段，否则取下拉值
     * @param array $config
     * @return string
     */
    private function resolveSignName($config)
    {
        $selected = isset($config['sign_name']) ? trim($config['sign_name']) : '';
        $custom   = isset($config['sign_name_custom']) ? trim($config['sign_name_custom']) : '';

        // 选「自定义」或未选择时，优先使用自定义输入
        if ($selected === '自定义' || $selected === 'custom' || $selected === '') {
            return $custom;
        }
        return $selected;
    }

    /**
     * 手机号规整：去空格/加号，去86国家码
     * @param string $mobile
     * @return string
     */
    private function formatMobile($mobile)
    {
        $mobile = str_replace([' ', '-', '+'], '', trim((string)$mobile));
        if ($mobile === '') {
            return '';
        }
        if (strpos($mobile, '86') === 0 && strlen($mobile) > 11) {
            $mobile = substr($mobile, 2);
        }
        $mobile = ltrim($mobile, '0');
        if (!preg_match('/^1[3-9]\d{9}$/', $mobile)) {
            return '';
        }
        return $mobile;
    }

    /**
     * 发起 RPC 风格签名请求
     * @param string $action SendSmsVerifyCode|CheckSmsVerifyCode
     * @param array  $apiParams 业务参数
     * @param array  $config 插件配置
     * @return array|false
     */
    private function request($action, $apiParams, $config)
    {
        if (empty($config['access_key_id']) || empty($config['access_key_secret'])) {
            return false;
        }

        $params = array_merge([
            'Action'           => $action,
            'Format'           => 'JSON',
            'Version'          => $this->version,
            'AccessKeyId'      => $config['access_key_id'],
            'SignatureMethod'  => 'HMAC-SHA1',
            'SignatureVersion' => '1.0',
            'SignatureNonce'   => uniqid(mt_rand(0, 65535), true),
            'Timestamp'        => gmdate('Y-m-d\TH:i:s\Z'),
        ], $apiParams);

        ksort($params);

        $query = '';
        foreach ($params as $key => $value) {
            $query .= '&' . $this->percentEncode($key) . '=' . $this->percentEncode($value);
        }
        $query = ltrim($query, '&');

        // StringToSign = POST & %2F & urlencode(规范化查询串)
        $stringToSign = 'POST&%2F&' . $this->percentEncode($query);
        $signature    = base64_encode(hash_hmac('sha1', $stringToSign, $config['access_key_secret'] . '&', true));
        $body         = 'Signature=' . $this->percentEncode($signature) . '&' . $query;

        return $this->curlPost($this->endpoint, $body);
    }

    /**
     * RFC 3986 编码（阿里云签名要求）
     * @param string $str
     * @return string
     */
    private function percentEncode($str)
    {
        $res = urlencode($str);
        $res = preg_replace('/\+/', '%20', $res);
        $res = preg_replace('/\*/', '%2A', $res);
        $res = preg_replace('/%7E/', '~', $res);
        return $res;
    }

    /**
     * POST 请求
     * @param string $url
     * @param string $body
     * @return array|false
     */
    private function curlPost($url, $body)
    {
        try {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $body);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
            curl_setopt($ch, CURLOPT_TIMEOUT, $this->timeout);
            curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/x-www-form-urlencoded;charset=utf-8',
            ]);
            $result = curl_exec($ch);
            $errno  = curl_errno($ch);
            if ($errno) {
                curl_close($ch);
                return false;
            }
            curl_close($ch);
            $result = trim((string)$result, "\xEF\xBB\xBF");
            $data   = json_decode($result, true);
            return is_array($data) ? $data : false;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * V10 模板占位符替换（@var(key) / ${key}），用于生成展示与日志内容
     * @param string $content
     * @param array  $templateParam
     * @return string
     */
    private function templateParam($content, $templateParam)
    {
        if (!is_array($templateParam)) {
            $templateParam = [];
        }
        foreach ($templateParam as $key => $value) {
            if (is_array($value)) {
                continue;
            }
            $content = str_replace('@var(' . $key . ')', $value, $content);
            $content = str_replace('${' . $key . '}', $value, $content);
        }
        $content = preg_replace('/@var\(.*?\)/is', '', $content);
        $content = preg_replace('/\$\{.*?\}/is', '', $content);
        return $content;
    }

    /**
     * 错误信息翻译
     * @param array $result
     * @return string
     */
    private function errorMsg($result)
    {
        $code    = $result['Code'] ?? '';
        $message = $result['Message'] ?? '';
        $map     = $this->errorCode();
        if ($code !== '' && isset($map[$code])) {
            $message = $map[$code];
        }
        if ($message === '') {
            $message = '未知错误';
        }
        return $code !== '' ? ($message . ' (码:' . $code . ')') : $message;
    }

    /**
     * 错误码对照
     * @return array
     */
    private function errorCode()
    {
        return [
            'MOBILE_NUMBER_ILLEGAL'      => '手机号码格式错误',
            'BUSINESS_LIMIT_CONTROL'     => '触发号码天级流控，请稍后再试',
            'INVALID_PARAMETERS'         => '请求参数不合法',
            'FUNCTION_NOT_OPENED'        => '尚未开通号码认证短信认证功能，请在阿里云控制台开通',
            'AMOUNT_NOT_ENOUGH'          => '账户余额不足，请充值',
            'SMS_SIGNATURE_ILLEGAL'      => '短信签名不合法，请使用控制台赠送签名',
            'SMS_TEMPLATE_ILLEGAL'       => '短信模板不合法，请使用控制台赠送模板',
            'SMS_CONTENT_ILLEGAL'        => '短信内容含违法违规内容',
            'OUT_OF_SERVICE'             => '业务已停机',
            'ACCOUNT_ABNORMAL'           => '账户异常',
            'InvalidAccessKeyId.NotFound' => 'AccessKeyId 不存在，请检查配置',
            'SignatureDoesNotMatch'      => '签名校验失败，请检查 AccessKeySecret 是否正确',
            'Forbidden.RAM'              => 'RAM 权限不足，需授予 dypns:SendSmsVerifyCode 权限',
            'Forbidden'                  => '无权限调用该接口',
            'Throttling'                 => '请求过于频繁，请稍后再试',
            'Throttling.User'            => '请求过于频繁，请稍后再试',
            'Product.Unsubscribe'        => '未开通号码认证服务',
            'ServiceUnavailable'         => '服务不可用，请稍后重试',
        ];
    }
}
