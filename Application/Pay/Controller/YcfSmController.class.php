<?php
namespace Pay\Controller;

class YcfSmController extends PayController
{

    public function __construct()
    {

        parent::__construct();
    }

    public function Pay($array)
    {

        $orderid     = I("request.pay_orderid");
        $body        = I('request.pay_productname');
        $notifyurl   = $this->_site . "Pay_YcfSm_notifyurl.html"; //异步通知
        $callbackurl = $this->_site . 'Pay_YcfSm_callbackurl.html'; //跳转通知

        $parameter = array(
            'code'         => 'YcfSm',
            'title'        => '微信扫码支付CFY',
            'exchange'     => 1, // 金额比例
            'gateway'      => '',
            'orderid'      => '',
            'out_trade_id' => $orderid, //外部订单号
            'channel'      => $array,
            'body'         => $body,
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
        $url   = $return["gateway"];

        $arraystr = array(
            'userid'  => $return['mch_id'], //用户ID（www.yzch.net获取）
            'orderid' => $return['orderid'], //用户订单号（必须唯一）
            'bankid'  => '2001',
        );
        $arraystr['sign']  = $this->_createSign($arraystr, $return['signkey']);
        $arraystr['money'] = $return["amount"]; //订单金额
        $arraystr['url']   = $return['notifyurl']; //用户接收返回URL连接
        $arraystr['aurl']  = $return['callbackurl'];
        $arraystr['sign2'] = $this->_createSign2($arraystr, $return['signkey']);
        $url_query         = '';

        foreach ($arraystr as $k => $vo) {
            $url_query .= $k . '=' . $vo . '&';
        }

        $url = $url . '?' . $url_query;

        header('Location:' . $url);
    }

    public function _createSign2($data, $key)
    {
        $sign2 = "money=" . $data['money'] . "&userid=" . $data['userid'] . "&orderid=" . $data['orderid'] . "&bankid=" . $data['bankid'] . "&keyvalue=" . $key;
        return md5($sign2);
    }

    protected function _createSign($data, $key)
    {
        $sign = "userid=" . $data['userid'] . "&orderid=" . $data['orderid'] . "&bankid=" . $data['bankid'] . "&keyvalue=" . $key;
        return md5($sign);
    }

    public function httpPostData($url, $data_string)
    {

        $cacert  = ''; //CA根证书  (目前暂不提供)
        $CA      = false; //HTTPS时是否进行严格认证
        $TIMEOUT = 30; //超时时间(秒)
        $SSL     = substr($url, 0, 8) == "https://" ? true : false;

        $ch = curl_init();
        if ($SSL && $CA) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true); //  只信任CA颁布的证书
            curl_setopt($ch, CURLOPT_CAINFO, $cacert); //  CA根证书（用来验证的网站证书是否是CA颁布）
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2); //  检查证书中是否设置域名，并且是否与提供的主机名匹配
        } else if ($SSL && !$CA) {
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); //  信任任何证书
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 1); //  检查证书中是否设置域名
        }

        curl_setopt($ch, CURLOPT_TIMEOUT, $TIMEOUT);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $TIMEOUT - 2);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
            'Content-Type: application/json;charset=utf-8',
        ));

        ob_start();
        curl_exec($ch);
        $return_content = ob_get_contents();
        ob_end_clean();

        $return_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

        curl_close($ch);
        return array(
            $return_code,
            $return_content,
        );
    }

    public function callbackurl()
    {
        $Order      = M("Order");
        $pay_status = $Order->where("pay_orderid = '" . $_REQUEST["orderid"] . "'")->getField("pay_status");
        sleep(2);
        if ($pay_status != 0) {
            $this->EditMoney($_REQUEST["orderid"], 'YcfSm', 1);

        } else {
            exit("Wait for。。。。");
        }
    }

    // 服务器点对点返回
    public function notifyurl()
    {

        ini_set('date.timezone', 'Asia/Shanghai');

        $returncode = $_GET["returncode"];
        $userid     = $_GET["userid"];
        $orderid    = $_GET["orderid"];
        $money      = $_GET["money"];
        $sign       = $_GET["sign"];
        $sign2      = $_GET["sign2"];
        if (empty($sign)) {
            echo 'param error';
            exit;
        }

        $orderInfo = M('Order')->where(['pay_orderid'=>$orderid])->find();
        $keyvalue      = $orderInfo['key'];
        $uid = $orderInfo['pay_memberid'] - 10000;
  

        $ext           = $_GET["ext"]; //order.Ext;

        $localsign = $this->format("returncode={0}&userid={1}&orderid={2}&keyvalue={3}"
            , $returncode
            , $userid
            , $orderid
            , $keyvalue
        );

        $localsign  = "returncode=" . $returncode . "&userid=" . $userid . "&orderid=" . $orderid . "&keyvalue=" . $keyvalue;
        $localsign2 = "money=" . $money . "&returncode=" . $returncode . "&userid=" . $userid . "&orderid=" . $orderid . "&keyvalue=" . $keyvalue;

        $localsign  = md5($localsign);
        $localsign2 = md5($localsign2);

        if ($sign2 != $localsign2) {
            echo 'sign2 error';
            exit; //加密错误
        }

        if ($sign != $localsign) {
            echo 'sign error';
            exit; 
        }

        switch ($returncode) {
            case "1": 
                $this->EditMoney($orderid, '', 0);
                echo 'ok';
                break;
            default:
                //Ê§°Ü
                break;
        }

    }

    public function format()
    {
        $args = func_get_args();
        if (count($args) == 0) {return;}
        if (count($args) == 1) {return $args[0];}
        $str = array_shift($args);
        $str = preg_replace_callback('/\\{(0|[1-9]\\d*)\\}/', create_function('$match', '$args = ' . var_export($args, true) . '; return isset($args[$match[1]]) ? $args[$match[1]] : $match[0];'), $str);
        return $str;
    }


}
