<?php
namespace Payment\Controller;

class AnsT12Controller extends PaymentController
{

    public function __construct()
    {
        parent::__construct();
    }

    public function PaymentExec($data, $config)
    {
        $arraystr = [
            'version'      => '1.0.0',
            'txnType'      => '12',
            'txnSubType'   => '01',
            'bizType'      => '000401',
            'accessType'   => '0',
            'accessMode'   => '01',
            'merId'        => $config['mch_id'],
            'merOrderId'   => $data['orderid'],
            'accNo'        => $data['banknumber'],
            'accType'      => '01',
            'customerInfo' => [
                'customerNm' => $data['bankfullname'],
                'issInsName' => $data['bankname'],
            ],
            'txnTime'      => date('YmdHis'),
            'txnAmt'       => $data['money'] * 100,
            'currency'     => 'CNY',
            'backUrl'      => $this->_site . 'Payment_AnsT12_notifyurl.html',
            'payType'      => '0401',
            'bankId'       => '',
            'subject'      => '',
            'body'         => '',
            'ppFlag'       => '01',
            'purpose'      => '',
            'merResv1'     => '',

        ];

        $arraystr['customerInfo'] = json_encode($arraystr['customerInfo']);
        $arraystr['signature']    = base64_encode($this->md5Sign($arraystr, $config['signkey']));
        $arraystr['signMethod']   = 'MD5';
        $arraystr['customerInfo'] = base64_encode($arraystr['customerInfo']);

        $result = curlPost($config['exec_gateway'], http_build_query($arraystr));
        if ($result) {

            parse_str($result, $result);
            $result['respMsg'] = base64_decode(str_replace(" ", "+", $result['respMsg']));

            switch ($result['respCode']) {

                case '1001':
                    $return = ['status' => 1, 'msg' => $result['respMsg']];
                    break;
                case '1002':
                    $return = ['status' => 3, 'msg' => $result['respMsg']];
                    break;
                case '1111':
                    $return = ['status' => 1, 'msg' => $result['respMsg']];
                    break;
                default:
                    $return = ['status' => 3, 'msg' => $result['respMsg']];
                    break;
            }

        } else {
            $return = ['status' => 3, 'msg' => '网络延迟，请稍后再试！'];
        }
        return $return;
    }

    public function PaymentQuery($data, $config)
    {
        $arraystr = [
            'version'    => '1.0.0',
            'txnType'    => '00',
            'txnSubType' => '01',
            'merId'      => $config['mch_id'],
            'merOrderId' => $data['orderid'],
        ];
        $arraystr['signature']  = base64_encode($this->md5Sign($arraystr, $config['signkey']));
        $arraystr['signMethod'] = 'MD5';
        $result                 = curlPost($config['query_gateway'], http_build_query($arraystr));
        if ($result) {
            parse_str($result, $result);
            $result['respMsg'] = base64_decode(str_replace(" ", "+", $result['respMsg']));
            switch ($result['respCode']) {

                case '1001':
                    $return = ['status' => 2, 'msg' => $result['respMsg']];
                    break;
                case '1002':
                    $return = ['status' => 3, 'msg' => $result['respMsg']];
                    break;
                case '1111':
                    $return = ['status' => 1, 'msg' => $result['respMsg']];
                    break;
                default:
                    $return = ['status' => 3, 'msg' => $result['respMsg']];
                    break;
            }

        } else {
            $return = ['status' => 3, 'msg' => '网络延迟，请稍后再试！'];
        }

        return $return;
    }

    public function md5Sign($data, $key, $connect = '', $is_md5 = true)
    {
        ksort($data);
        $string = '';
        foreach ($data as $k => $vo) {
            $string .= $k . '=' . $vo . '&';
        }
        $string = rtrim($string, '&');
        $result = $string . $connect . $key;

        return $is_md5 ? md5($result, true) : $result;

    }

    public function encryptDecrypt($string, $key = '', $decrypt = '0')
    {
        if ($decrypt) {
            $decrypted = rtrim(mcrypt_decrypt(MCRYPT_RIJNDAEL_256, md5($key), base64_decode($string), MCRYPT_MODE_CBC, md5(md5($key))), "12");
            return $decrypted;
        } else {
            $encrypted = base64_encode(mcrypt_encrypt(MCRYPT_RIJNDAEL_256, md5($key), $string, MCRYPT_MODE_CBC, md5(md5($key))));
            return $encrypted;
        }
    }
    public function notifyurl()
    {

    }

}
