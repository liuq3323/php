<?php
/**
 * Created by PhpStorm.
 * User: gaoxi
 * Date: 2017-05-18
 * Time: 11:33
 */
namespace Pay\Controller;

class YaoQianShuWxH5Controller extends PayController
{

    private $gateway = 'https://opay.arsomon.com:28443/vipay/reqctl.do';

    //支付
    public function Pay($array)
    {
        $return = $this->getParameter('摇钱树(微信H5)', $array, __CLASS__, 1);
        $params = [
            'service'    => 'h5.pay',
            'mch_id'     => $return['mch_id'],
            'goods'      => '在线支付',
            'order_no'   => $return['orderid'],
            'amount'     => $return['amount'],
            'notify_url' => $return['notifyurl'],
            'ip'         => getIP(),
        ];
        $params['sign'] = md5Sign($params, $return['signkey'], '&key=');        
        $postData = arrayToXml($params);
        $result = curlPost($this->gateway, $postData, ['Content-Type:text/xml']);
        $result = xmlToArray($result);
        if($result['res_code'] == '100' && $result['pay_url']){
            header('Location:'.$result['pay_url']);
        }else{
            $this->showmessage($result['res_msg']);
        }
    }

    /**
     * 页面通知
     */
    public function callbackurl()
    {
        $orderid    = I('request.orderid', ''); //系统订单号
        $Order      = M("Order");
        $pay_status = $Order->where(['pay_orderid' => $orderid])->getField("pay_status");
        if ($pay_status != 0) {
            //业务逻辑开始、并下发通知.
            $this->EditMoney($orderid, '', 1);
        }
    }

    //异步通知
    public function notifyurl()
    {
        $json = file_get_contents('php://input');
        $data = xmlToArray($json);
        if ($data['status'] == '100') {
            $key  = getKey($data['order_no']);
            $sign = $data['sign'];
            unset($data['sign']);
            $newSign = md5Sign($data, $key, '&key=');
            if ($newSign === $sign) {
                $this->EditMoney($data['order_no'], '', 0);
                echo 'success';
            }
        }
    }

}
