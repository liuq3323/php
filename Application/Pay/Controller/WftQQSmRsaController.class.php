<?php
namespace Pay\Controller;
use Org\Util\Wft\ClientResponseHandler;
use Org\Util\Wft\PayHttpClient;
use Org\Util\Wft\RequestHandler;
use Org\Util\Wft\Utils;

class WftQQSmRsaController extends PayController{

    private $resHandler = null;
    private $reqHandler = null;
    private $pay = null;

    public function __construct(){
        parent::__construct();
        $this->resHandler = new ClientResponseHandler();
        $this->reqHandler = new RequestHandler();
        $this->pay = new PayHttpClient();
    }


    public function Pay($array){

        $orderid = I("request.pay_orderid");

        $body = I('request.pay_productname');
        $notifyurl = $this->_site . 'Pay_WftQQSmRsa_notifyurl.html';

        $callbackurl = $this->_site . 'Pay_WftQQSmRsa_callbackurl.html';

        $parameter = array(
            'code' => 'WftQQSmRsa',
            'title' => '威富通支付（QQ钱包扫码）',
            'exchange' => 100, // 金额比例
            'gateway' => '',
            'orderid'=>'',
            'out_trade_id' => $orderid, //外部订单号
            'channel'=>$array,
            'body'=>$body
        );

        //支付金额
        $pay_amount = I("request.pay_amount", 0);


        // 订单号，可以为空，如果为空，由系统统一的生成
        $return = $this->orderadd($parameter);


        //如果生成错误，自动跳转错误页面
        $return["status"] == "error" && $this->showmessage($return["errorcontent"]);

        //跳转页面，优先取数据库中的跳转页面
        $return["notifyurl"] || $return["notifyurl"] = $notifyurl;

        //获取请求的url地址
        $this->reqHandler->setGateUrl($return["gateway"]);
        $public_rsa_key_path = './cert/Wft/'.$return['mch_id'].'/public_rsa_key.txt';
        $private_rsa_key_path = './cert/Wft/'.$return['mch_id'].'/private_rsa_key.txt';
        if(!file_exists($public_rsa_key_path) || !file_exists($private_rsa_key_path)) {
            $this->showmessage('证书文件不存在');
        }
        $ip = isset($_SERVER['SERVER_ADDR']) ? $_SERVER['SERVER_ADDR'] : '127.0.0.1';
        $private_rsa_key = file_get_contents($private_rsa_key_path);
        $public_rsa_key =  file_get_contents($public_rsa_key_path);
        $this->reqHandler->setRSAKey("-----BEGIN RSA PRIVATE KEY-----\n"
            . wordwrap($private_rsa_key, 64, "\n", true).
            "\n-----END RSA PRIVATE KEY-----");
        $this->resHandler->setRSAKey("-----BEGIN PUBLIC KEY-----\n"
            .wordwrap($public_rsa_key, 64, "\n", true).
            "\n-----END PUBLIC KEY-----");
        $this->reqHandler->setSignType('RSA_1_256');
        $this->reqHandler->setParameter('sign_type','RSA_1_256');
        $this->reqHandler->setParameter('service','pay.tenpay.native');
        $this->reqHandler->setParameter('version','1.0');
        $this->reqHandler->setParameter('mch_id',$return['mch_id']);
        $this->reqHandler->setParameter('out_trade_no',$return['orderid']);
        $this->reqHandler->setParameter('body','普通支付');
        $this->reqHandler->setParameter('total_fee',$return['amount']);
        $this->reqHandler->setParameter('mch_create_ip',$ip);
        $this->reqHandler->setParameter('notify_url',$notifyurl);
        $this->reqHandler->setParameter('nonce_str',mt_rand(time(),time()+rand()));
        $this->reqHandler->createSign();
        $data = Utils::toXml($this->reqHandler->getAllParameters());
        $this->pay->setReqContent($this->reqHandler->getGateURL(),$data);
        if($this->pay->call()){
            $this->resHandler->setContent($this->pay->getResContent());
            $this->resHandler->setKey($this->reqHandler->getKey());
            if($this->resHandler->isTenpaySign()){
                if($this->resHandler->getParameter('status') == 0 && $this->resHandler->getParameter('result_code') == 0){
                    import("Vendor.phpqrcode.phpqrcode",'',".php");
                    $url = $this->resHandler->getParameter('code_url');
                    $QR = "Uploads/codepay/". $return['orderid'] . ".png";//已经生成的原始二维码图
                    \QRcode::png($url, $QR, "L", 20);
                    $this->assign("imgurl", '/'.$QR);
                    $this->assign('params',$return);
                    $this->assign('orderid',$return['orderid']);
                    $this->assign('money',sprintf('%.2f',$return['amount']/100));
                    $this->display("WeiXin/qq");
                }else{
                    $this->showmessage($this->resHandler->getParameter('err_msg'));
                }
            } else {
                $this->showmessage($this->resHandler->getParameter('message'));
            }
        } else {
            $this->showmessage($this->resHandler->getParameter($this->pay->getErrInfo()));
        }
    }

    public function createRandomStr( $length = 32 ) {
        $chars = "abcdefghijklmnopqrstuvwxyz0123456789";
        $str ="";
        for ( $i = 0; $i < $length; $i++ ){
            $str.= substr($chars, mt_rand(0, strlen($chars)-1), 1);
        }
        return $str;
    }


    protected function _createSign($data, $key){
        $sign = '';
        ksort($data);
        foreach( $data as $k => $vo ){
            $sign .= $k . '=' . $vo . '&';
        }
        return md5($sign . 'key=' . $key);
    }




    public function callbackurl(){

        $Order = M("Order");
        $pay_status = $Order->where("pay_orderid = '".$_REQUEST["orderid"]."'")->getField("pay_status");
        if($pay_status <> 0){
            $this->EditMoney($_REQUEST["orderid"], 'WftQQSmRsa', 1);
        }else{
            exit("error");
        }
    }

    // 服务器点对点返回
    public function notifyurl(){

        //file_put_contents('./Data/notify.txt',"【".date('Y-m-d H:i:s')."】\r\n".file_get_contents("php://input")."\r\n\r\n",FILE_APPEND);
        $xml = file_get_contents('php://input');
        $this->resHandler->setContent($xml);
        $out_trade_no = $this->resHandler->getParameter('out_trade_no');
        if(!$out_trade_no) {
            echo ('failure1');
            exit;
        }
        $order = M("Order")->where(["pay_orderid"=>$out_trade_no])->find();
        if(empty($order)) {
            echo ('failure2');
            exit;
        }

        $public_rsa_key_path = './cert/Wft/'.$order['memberid'].'/public_rsa_key.txt';
        if(!file_exists($public_rsa_key_path)) {
            echo ('failure3');
            exit;
        }
        $public_rsa_key =  file_get_contents($public_rsa_key_path);
        $this->resHandler->setRSAKey("-----BEGIN PUBLIC KEY-----\n"
            .wordwrap($public_rsa_key, 64, "\n", true).
            "\n-----END PUBLIC KEY-----");
        if($this->resHandler->isTenpaySign()){
            if($this->resHandler->getParameter('status') == 0 && $this->resHandler->getParameter('result_code') == 0){
                $this->EditMoney($out_trade_no, 'WftQQSmRsa', 0);
                echo 'success';
                exit();
            } else {
                echo 'failure4';
                exit();
            }
        } else {
            echo 'failure5';
            exit();
        }
    }
}