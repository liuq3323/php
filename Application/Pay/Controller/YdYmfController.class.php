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

class YdYmfController extends PayController
{

    /**
     *  发起支付
     */
    public function Pay($array)
    {
        $orderid = I("request.pay_orderid");
        $body    = I('request.pay_productname');
        $return  = $this->getParameter('云雕(支付宝H5)', $array, __CLASS__, 1);
        $params  = [
            "pay_memberid"    => $return['mch_id'],
            "pay_orderid"     => $return['orderid'],
            "pay_amount"      => $return['amount'],
            "pay_applydate"   => date('Y-m-d H:i:s'),
            "pay_bankcode"    => '913',
            "pay_notifyurl"   => $return['notifyurl'],
            "pay_callbackurl" => $return['callbackurl'],
        ];
        $params['pay_md5sign'] = strtoupper(md5Sign($params, $return['signkey'], '&key='));
        echo createForm($return['gateway'], $params);
    }

    /**
     * 页面通知
     */
    public function callbackurl()
    {
        $Order      = M("Order");
        $orderid    = I('post.orderid', '');
        $pay_status = $Order->where(['pay_orderid' => $orderid])->getField("pay_status");
        if ($pay_status != 0) {
            $this->EditMoney($orderid, '', 1);
        } else {
            exit("error");
        }
    }

    /**
     *  服务器通知
     */
    public function notifyurl()
    {
        $data = I('request.', '');
        if ($data['returncode'] == '00') {

            $key = getKey($data['orderid']);

            $signitem = [ // 返回字段
                "memberid"       => $data["memberid"], // 商户ID
                "orderid"        => $data["orderid"], // 订单号
                "amount"         => $data["amount"], // 交易金额
                "datetime"       => $data["datetime"], // 交易时间
                "transaction_id" => $data["transaction_id"], // 支付流水号
                "returncode"     => $data["returncode"],
            ];
            $newSign = strtoupper(md5Sign($signitem, $key, '&key='));
            if ($newSign == $data['sign']) {
                $this->EditMoney($data['orderid'], '', 0);
                exit('ok');
            }
        }
    }

}
