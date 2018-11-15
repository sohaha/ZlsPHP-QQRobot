<?php

namespace Zls\QQ\Controller;

use Z;
use Zls\QQ\QQException;

/**
 * QQApi
 */
class QQApi extends \Zls_Controller
{
    /** @var \Zls\QQ\Main $Robot */
    private $Robot;

    public function before()
    {
        /** @var \Zls\QQ\Main $QQRobotMain */
        $QQRobotMain = new \Zls\QQ\Main();
        $QQRobotMain->setKey(z::get('key', z::cache()->get('key')));
        $this->Robot = $QQRobotMain;
    }

    /**
     * 获取登录二维码
     */
    public function zGetLoginQr()
    {
        $this->Robot->reSetKey('');
        $qr = $this->Robot->getLoginQr();
        echo '<img src="' . $qr['base64'] . '">';
        z::cache()->set('key', $qr['key']);
    }

    /**
     * 登录qq
     */
    public function zLogin()
    {
        $res = $this->Robot->verifyLoginStatus();

        return z::json(200, $res);
    }

    /**
     * 获取好友列表
     * @return mixed|string
     */
    public function zGetFriends()
    {
        $list = $this->Robot->getFriends();

        return z::json(200, '好友', $list);
    }

    /**
     * 获取群列表
     * @return mixed|string
     */
    public function zGetGroups()
    {
        $list = $this->Robot->getGroups();

        return z::json(200, '全部群列表', $list);
    }

    /**
     * 获取群列表
     * @return mixed|string
     */
    public function zGetGroups2()
    {
        $list = $this->Robot->getGroups2();

        return z::json(200, '群列表', $list);
    }

    /**
     * 获取讨论组列表
     */
    public function zGetDiscusses()
    {
        $list = $this->Robot->getDiscusses();

        return z::json(200, '讨论组', $list);
    }

    /**
     * 发送群消息
     */
    public function zSendQun()
    {
        try {
            $qun = [
                "face" => 588,
                "flag" => 293601280,
                "nick" => "花生仁",
                "gid" => 614231954,
            ];
            $this->Robot->sendQun($qun, '测试机器人[右车头](我是机器人,请别回复)');

            return z::json(200, '发送成功');
        } catch (QQException $e) {
            return z::json(211, $e->getMessage());
        }
    }

    /**
     * 发送讨论组消息
     */
    public function zSendDiscusses()
    {
        try {
            $qun = [
                "face" => 588,
                "flag" => 293601280,
                "nick" => "花生仁",
                "did" => 3200494776,
            ];
            $this->Robot->sendDiscusses($qun, '测试机器人[右车头](我是机器人,请别回复)');

            return z::json(200, '发送成功');
        } catch (QQException $e) {
            return z::json(211, $e->getMessage());
        }
    }

    /**
     * 接收消息
     */
    public function zPullMessages()
    {
        set_time_limit(0);//ignore_user_abort(true);\n$0
        while (true) {
            ob_flush();
            flush();
            try {
                $data = $this->Robot->pullMessages();
                z::dump(json_encode($data), count($data), z::arrayGet($data, '0.content'), '是否用户信息:' . z::arrayGet($data, '0.user_message'));
            } catch (QQException $e) {
                z::dump($e->getMessage());
            }
        }
        //return z::json(200, '接收消息', $data);
    }

    /**
     * 获取好友信息
     * @param int uin 好友uin
     * @return object
     */
    public function zGetFriendInfo()
    {
        $uin = z::get('uin', 1960102303);
        $data = $this->Robot->getFriendInfo($uin);

        return z::json(200, '获取好友信息', $data);
    }

    /**
     * 获取群信息
     * @param int code 群code
     * @return object
     */
    public function zGetQunInfo()
    {
        $code = z::get('code', 860738977);
        $data = $this->Robot->getGroupsInfo($code);

        return z::json(200, '获取群信息', $data);
    }

    /**
     * 获取讨论组详情
     * @param int code 群code
     * @return mixed|string
     */
    public function zGetDiscussesInfo()
    {
        $did = z::get('code', 3200494776);
        $data = $this->Robot->getDiscussesInfo($did);

        return z::json(200, '讨论组详情', $data);
    }

    /**
     * 发送好友消息
     * @return mixed|string
     */
    public function zSend()
    {
        try {
            $friend = [
                "face" => 588,
                "flag" => 293601280,
                "nick" => "花生仁",
                "uin" => 1960102303,
            ];
            $this->Robot->sendFriend($friend, '测试机器人[右车头](我是机器人,请别回复)');

            return z::json(200, '发送成功');
        } catch (QQException $e) {
            return z::json(211, $e->getMessage());
        }
    }
}
