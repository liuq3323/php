<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-09-04
 * Time: 0:25
 */
namespace Pay\Controller;

/**
 * 第三方接口开发示例控制器
 * Class DemoController
 * @package Pay\Controller
 *
 * 三方通道接口开发说明：
 * 1. 管理员登录网站后台，供应商管理添加通道，通道英文代码即接口类名称
 * 2. 用户管理-》通道-》指定该通道（独立或轮询）
 * 3. 用户费率优先通道费率
 * 4. 用户通道指定优先系统默认支持产品通道指定
 * 5. 三方回调地址URL写法，如本接口 ：
 *    异步地址：http://www.yourdomain.com/Pay_Demo_notifyurl.html
 *    跳转地址：http://www.yourdomain.com/Pay_Demo_callbackurl.html
 *
 *    注：下游对接请查看商户API对接文档部分.
 */

class ShanKjController extends PayController
{

    private $gateway = 'https://cashier.sandpay.com.cn/fastPay/quickPay/index';

    /**
     *  发起支付
     */
    public function Pay($array)
    {
        $orderid = I("request.pay_orderid");
        $body    = I('request.pay_productname');
        $data    = $this->getParameter('杉德(快捷)', $array, __CLASS__, 100);

        // step1: 拼接data
        $jsonData = [
            'head' => [
                'version'     => '1.0',
                'method'      => 'sandPay.fastPay.quickPay.index',
                'productId'   => '00000016',
                'accessType'  => '1',
                'mid'         => $data['mch_id'],
                'channelType' => '07',
                'reqTime'     => date('YmdHis'),
            ],

            'body' => [
                'userId'       => str_pad(date('ymd').rand(0,9999), 10, '0', STR_PAD_LEFT),
                'orderCode'    => $data['orderid'],
                'orderTime'    => date('YmdHis'),
                'totalAmount'  => str_pad($data['amount'], 12, '0', STR_PAD_LEFT),
                'subject'      => 'pay',
                'body'         => 'pay',
                'currencyCode' => '156',
                'notifyUrl'    => $data['notifyurl'],
                'frontUrl'     => $data['callbackurl'],
                'clearCycle'   => '0',
                'extend'       => '',
            ],
        ];

        // step2: 私钥签名
        $prikey = $this->loadPk12Cert($data['appid'], $data['appsecret']);
        $sign   = $this->sign($jsonData, $prikey);

        $params = [
            'charset'  => 'utf-8',
            'signType' => '01',
            'data'     => json_encode($jsonData),
            'sign'     => $sign,
        ];
        echo $this->createHtml($params, $this->gateway);
    }

    /**
     * 页面通知
     */
    public function callbackurl()
    {
        $Order = M("Order");

        $pay_status = $Order->where("pay_orderid = '" . $_REQUEST["orderid"] . "'")->getField("pay_status");
        if ($pay_status != 0) {
            $this->EditMoney($_REQUEST["orderid"], '', 1);
        } else {
            exit("error");
        }
    }

    /**
     *  服务器通知
     */
    public function notifyurl()
    {

        $post = $_POST;
        file_put_contents('./Data/notify2.txt', "【" . date('Y-m-d H:i:s') . "】\r\n" . serialize($_POST) . "\r\n\r\n", FILE_APPEND);

        if ($post) {
            $sign   = base64_decode($post['sign']); //签名
            $data   = stripslashes($post['data']); //支付数据
            $result = json_decode($data, true); //data数据

            $publicCertPath = getKey($result['body']["orderCode"]);
            $pubkey         = $this->loadX509Cert($publicCertPath);
            if (rsaEncryptVerify($data, $pubkey, $sign) && $result['body']['orderStatus'] == 1) {
                //签名验证成功
                $this->EditMoney($result['body']["orderCode"], '', 0);
                echo "respCode=000000";
                exit;
            } else {
                //签名验证失败
                exit;
            }

        }

    }

    /********************************************辅助方法**************************************************/

    public function cut($begin, $end, $str)
    {
        $b = mb_strpos($str, $begin) + mb_strlen($begin);
        $e = mb_strpos($str, $end) - $b;

        return mb_substr($str, $b, $e);
    }

    /**
     *获取公钥
     *@param  [$path]
     *@return [mixed]
     *@throws [\Exception]
     */
    protected function loadX509Cert($path)
    {
        $file   = file_get_contents($path);
        $cert   = chunk_split(base64_encode($file), 64, "\n");
        $cert   = "-----BEGIN CERTIFICATE-----\n" . $cert . "-----END CERTIFICATE-----\n";
        $res    = openssl_pkey_get_public($cert);
        $detail = openssl_pkey_get_details($res);
        openssl_free_key($res);
        return $detail['key'];
    }

    /**
     * 获取私钥
     * @param  [$path]
     * @param  [$pwd]
     * @return [mixed]
     * @throws [\Exception]
     */
    protected function loadPk12Cert($path, $pwd)
    {
        $file = file_get_contents($path);
        openssl_pkcs12_read($file, $cert, $pwd);
        return $cert['pkey'];
    }
/**
 * 私钥签名
 * @param $plainText
 * @param $path
 * @return string
 * @throws Exception
 */
    public function sign($plainText, $path)
    {
        $plainText = json_encode($plainText);
        try {
            $resource = openssl_pkey_get_private($path);
            $result   = openssl_sign($plainText, $sign, $resource);
            openssl_free_key($resource);

            if (!$result) {
                throw new \Exception('签名出错' . $plainText);
            }

            return base64_encode($sign);
        } catch (\Exception $e) {
            throw $e;
        }
    }
    /**
     * 对数组变量进行JSON编码，为了（本系统的PHP版本为5.3.0）解决PHP5.4.0以上才支持的JSON_UNESCAPED_UNICODE参数
     *@param mixed array 待编码的 array （除了resource 类型之外，可以为任何数据类型，改函数只能接受 UTF-8 编码的数据）
     *@return  string （返回 array 值的 JSON 形式）
     *@author
     * @d/t     2017-07-17
     */
    protected function json_encodes($array)
    {

        if (version_compare(PHP_VERSION, '5.4.0', '<')) {
            $str = json_encode($array);
            $str = preg_replace_callback("#\\\u([0-9a-f]{4})#i", function ($matchs) {
                return iconv('UCS-2BE', 'UTF-8', pack('H4', $matchs[1]));
            }, $str);
            return $str;
        } else {
            return json_encode($array, 320);
        }
    }

    /**
     * 分割字符串
     * @param String $str  要分割的字符串
     * @param int $length  指定的长度
     * @param String $end  在分割后的字符串块追加的内容
     */
    protected function mb_chunk_split($string, $length, $end, $once = false)
    {
        $string = iconv('gb2312', 'utf-8//ignore', $string);
        $array  = array();
        $strlen = mb_strlen($string);
        while ($strlen) {
            $array[] = mb_substr($string, 0, $length, "utf-8");
            if ($once) {
                return $array[0] . $end;
            }

            $string = mb_substr($string, $length, $strlen, "utf-8");
            $strlen = mb_strlen($string);
        }
        $str = implode($end, $array);
        return $str . '%0A';
    }

    protected function mb_array_chunk($arr)
    {

        $credential                   = json_decode($arr['body']['credential'], true);
        $credential['params']['orig'] = $this->mb_chunk_split($credential['params']['orig'], 76, '%0A');
        $credential['params']['sign'] = $this->mb_chunk_split($credential['params']['sign'], 76, '%0A');
        $arr['body']['credential']    = str_replace(array('==', '+', '='), array('%3D%3D', '%2B', '%3D'), $this->json_encodes($credential));

        return $this->json_encodes($arr);

    }
    public function createHtml($params, $url)
    {
        $encodeType = isset($params['encoding']) ? $params['encoding'] : 'UTF-8';
        $html       = '<html><head><meta http-equiv="Content-Type" content="text/html; charset={$encodeType}"/></head><body onload="javascript:document.pay_form.submit();">
            <form id="pay_form" name="pay_form" action="' . $url . '" method="post">';
        foreach ($params as $key => $value) {
            $html .= "<input type=\"hidden\" name=\"{$key}\" id=\"{$key}\" value='{$value}' />\n";
        }
        $html .= '<!-- <input type="submit" type="hidden">--></form></body></html>';
        return $html;
    }

}
