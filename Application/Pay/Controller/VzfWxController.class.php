<?php
namespace Pay\Controller;

class VzfWxController extends PayController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function Pay($array)
    {
        $orderid = I("request.pay_orderid");
        $body = I('request.pay_productname');

        $parameter = array(
            'code' => 'VzfWx', // 通道名称
            'title' => '微支付微信扫码',
            'exchange' => 1, // 金额比例
            'gateway' => '',
            'orderid' => '',
            'out_trade_id' => I("request.pay_orderid", ""),
            'body'=>$body,
            'channel'=>$array
        );
        $return = $this->orderadd($parameter);


        $opts = array(  
            'orderNo' => $return['orderid'],
            'total_fee' => $return['amount'],
            'channeltype' => 'WSWX_WEB',
            // 'CstmrNm' => '',
            // 'IDNo' => '350125198606244114',
            // 'BkAcctNo' => '4033920020873990',
            // 'MobNo' => '13788880753',           
            'version' => '1.0',            
            'merId' => $return['mch_id'],
            'appId' => $return['appid'],
            'title' => $return['subject'],
            'attach' => json_encode(['mch_id' => $return['mch_id']]),
        );

        // echo '加密后:'.strtoupper($sign) . '<br />';
        $opts['sign'] = $this->sign($opts, $return['appsecret']);


        $ch = curl_init();
        curl_setopt( $ch, CURLOPT_HTTP_VERSION , CURL_HTTP_VERSION_1_1 );
        curl_setopt( $ch, CURLOPT_USERAGENT , 'JuheData' );
        curl_setopt( $ch, CURLOPT_CONNECTTIMEOUT , 60 );
        curl_setopt( $ch, CURLOPT_TIMEOUT , 60);
        curl_setopt( $ch, CURLOPT_RETURNTRANSFER , true );
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt( $ch , CURLOPT_POST , true );
        curl_setopt( $ch , CURLOPT_POSTFIELDS , $opts );
        curl_setopt( $ch , CURLOPT_URL , $return['gateway'] );
        $response = curl_exec( $ch );
        // print_r($response);
        // exit;


        $response = json_decode($response, true);
        //print_r($dataxml);
        if ($response && $response['retCode'] == '100') {
            import("Vendor.phpqrcode.phpqrcode",'',".php");
            // $url = urldecode($response['qrcode']);
            $QR = "Uploads/codepay/". $return["orderid"] . ".png";//已经生成的原始二维码图
            //$delqr = $QR;
            \QRcode::png($response['qrcode'], $QR, "L", 20);
            
            $this->assign("imgurl", $this->_site.$QR);
            $this->assign('params',$return);
            $this->assign('orderid',$return['orderid']);
            $this->assign('money',$return['amount']);
            $this->display("WeiXin/weixin");
        } else {
            $this->showmessage($data);
        }
    }

    private function sign($params, $secret)
    {        
        $signArray = array();
        $tmpKey = array();
        foreach ($params as $key => $value) { 
            $tmpKey[] = $key;
        } 
        sort($tmpKey);
        foreach($tmpKey as $keyName){
            $signArray[$keyName] = $params[$keyName];
        }

        $tmpStr = '';
        foreach ($signArray as $key => $value) { 
            $tmpStr .= $key.'='.$value.'&';
        }  
        $tmpStr .= 'key=' . $secret;

        // echo '加密前:'.$tmpStr . '<br />';
        return strtoupper(md5($tmpStr));

    }

    // 页面通知返回
    public function callbackurl()
    {
        $Order = M("Order");
        $pay_status = $Order->where("pay_orderid = '".$_REQUEST["orderid"]."'")->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($_REQUEST["orderid"], 'WxGzh', 1);
        }else{
            exit("error");
        }
    }

    public function notifyurl()
    { // 服务器点对点返回

        $request_priv_sign = $_REQUEST['mysign'];

        unset($_REQUEST['sign']);
        unset($_REQUEST['mysign']);

        $orderid = $_REQUEST['orderNo']; //系统订单号
        $attach = json_decode($_REQUEST['attach'], true);

        $channel_id = M('channel')->where(['code' => 'VzfWx'])->getField('id');
        $account = M('channel_account')->where(['mch_id' => $attach['mch_id'], 'channel_id' => $channel_id])->find();

        //处理验签
        $priv_sign = $this->sign($_REQUEST, $account['appsecret']);

        //生成签文，开始验签
        if($priv_sign == $request_priv_sign){
            //业务逻辑开始、并下发通知.
            $this->EditMoney($orderid, 'VzfWx', 0);
            //回写消息
            exit("success");
        }
    }
}
?>

