<?php
namespace Payment\Controller;

class DwjController extends PaymentController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function PaymentExec($data, $config)
    {

        $arraystr = [
            'instId'             => $config['appid'],
            'mercId'             => $config['mch_id'],
            'mercOrderId'        => $data['orderid'],
            'stlDate'            => date('Ymd'),
            'stlTime'            => date('His'),
            'stlAmt'             => $data['money'] * 100,
            'stlAcctNo'          => $data['banknumber'],
            'stlAcctName'        => $data['bankfullname'],
            'stlAcctBankName'    => $data['bankname'],
            'stlAcctSubBankName' => $data['bankzhiname'],
            'stlAcctBankNo'      => $data['additional'][0],
            'stlAcctBankProv'    => $data['sheng'],
            'stlAcctBankCity'    => $data['shi'],
            'stlAcctIdNo'        => $data['additional'][1],
            'stlAcctMobile'      => $data['additional'][2],
        ];

        $md5String =
            $arraystr['instId'] .
            $arraystr['mercId'] .
            $arraystr['mercOrderId'] .
            $arraystr['stlDate'] .
            $arraystr['stlTime'] .
            $arraystr['stlAmt'] .
            $arraystr['stlAcctNo'] .
            $config['signkey'];
        $arraystr['md5value'] = strtoupper(md5($md5String));

        $json = '{';
        foreach ($arraystr as $k => $v) {
            $json .= '"' . $k . '":"' . $v . '",';
        }
        $json    = rtrim($json, ',') . '}';
        $gateway = $config['exec_gateway'] . $json;

        $result = curlPost($gateway);

        $result = json_decode($result, true);
        if ($result) {
            if ($result['RSPCODE'] == 000000) {
                $return = ['status' => 1, 'msg' => $result['RSPMSG']];
            } else {
                $return = ['status' => 3, 'msg' => $result['RSPMSG']];
            }
        } else {
            $return = ['status' => 3, 'msg' => '网络延迟，请稍后再试！'];
        }
        return $return;
    }

    public function PaymentQuery($data, $config)
    {
        $arraystr = [
            'mercId'      => $config['mch_id'],
            'mercOrderId' => $data['orderid'],
            'stlDate'     => date('Ymd'),
        ];

        $md5String =
            $arraystr['mercId'] .
            $arraystr['mercOrderId'] .
            $arraystr['stlDate'] .
            $config['signkey'];

        $arraystr['md5value'] = strtoupper(md5($md5String));

        $json = '{';
        foreach ($arraystr as $k => $v) {
            $json .= '"' . $k . '":"' . $v . '",';
        }
        $json    = rtrim($json, ',') . '}';
        $gateway = $config['query_gateway'] . $json;

        $result = curlPost($gateway);
        $result = json_decode($result, true);
        if ($result) {
            switch ($result['dfStatus']) {
                case 'S':
                    $return = ['status' => 2, 'msg' => '成功'];
                    break;
                case 'F':
                    $return = ['status' => 3, 'msg' => '支付失败'];
                    break;
                case 'P':
                    $return = ['status' => 1, 'msg' => '受理中！'];
                    break;
                case 'Q':
                    $return = ['status' => 1, 'msg' => '受理中！'];
                    break;
                case 'U':
                    $return = ['status' => 1, 'msg' => '受理中！'];
                    break;
                default:
                    $return = ['status' => 3, 'msg' => $result['RSPMSG']];
                    break;
            }
        } else {
            $return = ['status' => 3, 'msg' => '网络延迟，请稍后再试！'];
        }
        return $return;
    }

}
