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

class DwjYlController extends PayController
{

    /**
     *  发起支付
     */
    public function Pay($array)
    {
        $orderid = I("request.pay_orderid");
        $body    = I('request.pay_productname');
        $return  = $this->getParameter('大玩家(银联)', $array, __CLASS__, 100);
        $data    = [
            'instId'      => $return['appid'],
            'mercId'      => $return['mch_id'],
            'mercOrderId' => $return['orderid'],
            'txnDate'     => date('Ymd'),
            'txnTime'     => date('His'),
            'ccy'         => 'CNY',
            'txnAmt'      => $return['amount'],
            'notifyUrl'   => $return['notifyurl'],
            'frontUrl'    => urlencode($return['callbackurl'] . '?' . $return['orderid']),
            'productName' => 'pay',
        ];

        $md5String =
            $data['instId'] .
            $data['mercId'] .
            $data['mercOrderId'] .
            $data['txnDate'] .
            $data['txnTime'] .
            $data['ccy'] .
            $data['txnAmt'] .
            $return['signkey'];

        $data['md5value'] = strtoupper(md5($md5String));

        // var_dump($data);
        $json = '{';
        foreach ($data as $k => $v) {
            $json .= '"' . $k . '":"' . $v . '",';
        }
        $json    = rtrim($json, ',') . '}';
        $gateway = $return['gateway'] . $json;
        // var_dump($gateway);exit;
        $result = curlPost($gateway);
        $result = json_decode($result, true);
        if ($result['RSPCODE'] == '000000' && $result['RSPDATA']) {
            header('Location:' . $result['RSPDATA']);
        } else {
            $this->showmessage($result['RSPMSG']);
        }
    }

    /**
     * 页面通知
     */
    public function callbackurl()
    {
        $arr        = explode('?', $_SERVER['REQUEST_URI']);
        $orderid    = $arr['1']; //系统订单号
        $Order      = M("Order");
        $pay_status = $Order->where("pay_orderid = '" . $orderid . "'")->getField("pay_status");
        if ($pay_status != 0) {
            //业务逻辑开始、并下发通知.
            $this->EditMoney($orderid, '', 1);
        }
    }

    /**
     *  服务器通知
     */
    public function notifyurl()
    {
        $formData = file_get_contents('php://input');
        $formData = json_decode($formData, true);

        if ($formData['txnStatus'] == 'S') {
            $key      = getKey($formData['mercOrderId']);
            $md5value = strtoupper(md5(
                $formData['mercId'] .
                $formData['mercOrderId'] .
                $formData['txnDate'] .
                $formData['txnStatus'] .
                $key
            ));
            if ($md5value == $formData['md5value']) {
                $this->EditMoney($formData['mercOrderId'], '', 0);
                exit("success");
            }
        }
    }

}
