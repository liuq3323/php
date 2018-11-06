<?php
/**
 * Created by PhpStorm.
 * User: mapeijian
 * Date: 2018-04-11
 * Time: 17:37
 */
namespace Pay\Controller;


class LmAliH5Controller extends PayController
{

    private $gateway = 'http://ncpay-api.lanmeipay.com';

    /**
     *  发起支付
     */
    public function Pay($array)
    {
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname', '商品');
        $notifyurl = $this->_site ."Pay_LmAliH5_notifyurl.html"; //异步通知
        $bank_code=I("request.bank_code");

        $parameter = array(
            'code' => 'LmAliH5',
            'title' => '澜湄支付(支付宝H5)',
            'exchange' => 1, // 金额比例
            'gateway' => '',
            'orderid'=>'',
            'out_trade_id' => $orderid, //外部订单号
            'channel'=>$array,
            'body'=>$body
        );
        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);
        $callbackurl = $this->_site . 'Pay_LmAliH5_callbackurl.html'; //跳转通知
        $params  = [
            "version"    => '1.0',
            "merNo"           => $return['mch_id'],
            "merOrderNo"     => $return['orderid'],
            "totalAmount"      => $return['amount'],
            "productName"   => $body,
            "backendUrl"    => $notifyurl,
            "clientIp"   => get_client_ip(),
            "tradeType" => 'wap',
            "platformId" => '201',
            "frontUrl" => $callbackurl
        ];
        $params['sign'] = md5Sign($params, $return['signkey'], '');
        $resultJson = curlPost($this->gateway.'/payment/api/preorder', $params);
        $result = json_decode($resultJson, true);
        if($result['retcode'] == '200') {
            if($result['data']['code'] == 1) {
                header('Location:'.$result['data']['pay_url']);
            } else {
                $this->showmessage($result['data']['msg']);
            }
        } else {
            $this->showmessage($result['msg']);
        }
    }

    /**
     * 页面通知
     */
    public function callbackurl()
    {
        $Order      = M("Order");
        $orderid    = I('merOrderNo', '');
        $pay_status = $Order->where(['pay_orderid' => $orderid])->getField("pay_status");
        if($pay_status == 0) {
            sleep(3);//等待3秒
            $pay_status = M('Order')->where(['pay_orderid' => $orderid])->getField("pay_status");
        }
        if ($pay_status <> 0) {
            $this->EditMoney($orderid, '', 1);
        } else {
            exit('页面已过期请刷新');
        }
    }

    /**
     *  服务器通知
     */
    public function notifyurl()
    {
        file_put_contents('./Data/lm_notify.txt', "【".date('Y-m-d H:i:s')."】\r\n".file_get_contents("php://input")."\r\n\r\n",FILE_APPEND);
        $data = I('post.', '');
        if (isset($data['payState']) && $data['payState'] == '1') {
            $key = getKey($data['merOrderNo']);
            $sign = $data['sign'];
            unset($data['sign']);
            $newSign = md5Sign($data, $key, '');
            if (strtoupper($newSign) == $sign) {
                $this->EditMoney($data['merOrderNo'], 'LmAliH5', 0);
                exit('success');
            } else {
                exit('check sign fail');
            }
        } else {
            exit('fail');
        }
    }
}
