<?php
namespace User\Controller;

use Think\Page;

class UserNumController extends BaseController
{
    public function __construct()
    {
        parent::__construct();
        $this->assign("Public", MODULE_NAME); // 模块名称
        $this->assign('paytypes', C('PAYTYPES'));
    }


    public function userAccount()
    {
        $uid         = I('get.uid', '');
        $Channel     = M('Channel');
        $count       = $Channel->count();
        $Page        = new Page($count, 15);
        $channelList = $Channel->where(['status' => 1])->limit($Page->firstRow . ',' . $Page->listRows)->order('id DESC')->select();
        $this->assign([
            'uid'         => $uid,
            'channelList' => $channelList,
            'page'        => $Page->show(),
        ]);
        $this->display();
    }

    public function userNumList()
    {
        $uid            = I('get.uid', '');
        $UserChannelNum = M('UserChannelNum');
        $where          = ['uid' => $uid, 'channel_id' => $pid];
        $count          = $UserChannelNum->where($where)->count();
        $Page           = new Page($count, 15);
        $list           = $UserChannelNum->where($where)->limit($Page->firstRow . ',' . $Page->listRows)->order('id DESC')->select();

        $this->assign([
            'pid'  => $pid,
            'uid'  => $uid,
            'page' => $Page->show(),
            'list' => $list,
        ]);
        $this->display();
    }

    public function editNum()
    {

        if (IS_POST) {
            $data           = I('post.data', '');
            $UserChannelNum = M('UserChannelNum');
            $weight         = (int) $data['weight'];
            ($weight <= 0 || $weight >= 10) && ($data['weight'] = 1);
            if (!$data['id']) {
                $result = $UserChannelNum->add($data);
            } else {
                $result = $UserChannelNum->where(['id' => $data['id']])->save($data);
            }

            $this->ajaxReturn(['status' => $result]);
        } else {
            $getData        = I('get.', '');
            $UserChannelNum = M('UserChannelNum');
            $data           = $UserChannelNum->where(['uid' => $getData['uid']])->find();
            $this->assign([
                'data' => $data,
                'uid'  => $getData['uid'],
            ]);
            $this->display();
        }
    }

    public function delUserNum()
    {
        $aid            = I('post.aid', '');
        $where          = ['id' => $aid];
        $UserChannelNum = M('UserChannelNum');
        $count          = $UserChannelNum->where($where)->count();
        $result         = 0;
        if ($count) {
            $result = $UserChannelNum->where($where)->delete();
        }
        $this->ajaxReturn(['status' => $result]);

    }
}
