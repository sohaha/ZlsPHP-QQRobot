<?php
/**
 * QQ机器人
 * @author        影浅
 * @email         seekwe@gmail.com
 * @copyright     Copyright (c) 2015 - 2017, 影浅, Inc.
 * @link          ---
 * @since         v0.0.1
 * @updatetime    2018-11-12 17:18
 */

namespace Zls\QQ;

use Z;
use Zls\Action\Http;

class Main
{
    /** 二维码状态，未失效 */
    const STATUS_UNEXPIRED = 1;
    /** 二维码状态，已失效 */
    const STATUS_EXPIRED = 2;
    /** 二维码状态，认证中 */
    const STATUS_ACCREDITATION = 3;
    /** 二维码状态，认证成功 */
    const STATUS_CERTIFICATION = 4;
    /**
     * URLS
     */
    const URL_FRIEND_SEND         = 'http://d1.web2.qq.com/channel/send_buddy_msg2';
    const URL_QUN_SEND            = 'http://d1.web2.qq.com/channel/send_qun_msg2';
    const GET_FRIENDS             = 'http://s.web2.qq.com/api/get_user_friends2';
    const URL_GET_GROUP           = 'http://s.web2.qq.com/api/get_group_name_list_mask2';
    const URL_PTQR_LOGIN          = 'https://ssl.ptlogin2.qq.com/ptqrlogin?ptqrtoken=%s&webqq_type=10&remember_uin=1&login2qq=1&aid=501004106&u1=http%%3A%%2F%%2Fw.qq.com%%2Fproxy.html%%3Flogin2qq%%3D1%%26webqq_type%%3D10&ptredirect=0&ptlang=2052&daid=164&from_ui=1&pttype=1&dumy=&fp=loginerroralert&action=0-0-4303&mibao_css=m_webqq&t=undefined&g=1&js_type=0&js_ver=10203&login_sig=&pt_randsalt=0';
    const URL_GET_ONLINE_BUDDIES2 = 'http://d1.web2.qq.com/channel/get_online_buddies2?vfwebqq=%s&clientid=53999199&psessionid=%s&t=0.1';
    const URL_PTQRSHOW            = 'https://ssl.ptlogin2.qq.com/ptqrshow?appid=501004106&e=0&l=M&s=5&d=72&v=4&t=0.%s61858167465%s';
    const URL_LOGIN               = 'http://d1.web2.qq.com/channel/login2';
    const URL_GETVFWEBQQ          = 'http://s.web2.qq.com/api/getvfwebqq?ptwebqq=%s&clientid=53999199&psessionid=&t=0.1';
    const URL_REFERER_CFPROXY     = 'http://d1.web2.qq.com/cfproxy.html?v=20151105001&callback=1';
    const URL_REFERER_PROXY       = 'http://d1.web2.qq.com/proxy.html?v=20151105001&callback=1&id=2';
    const CLIENTID                = 53999199;
    const GET_DISCUSSES           = 'http://s.web2.qq.com/api/get_discus_list?clientid=%s&psessionid=%s&vfwebqq=%s&t=0.1';
    const URL_DISCUSSES_SEND      = 'http://d1.web2.qq.com/channel/send_discu_msg2';
    const URL_PULLMESSAGES        = 'http://d1.web2.qq.com/channel/poll2';
    const URL_FRIEND_INFO         = 'http://s.web2.qq.com/api/get_friend_info2?tuin=%s&vfwebqq=%s&clientid=%s&psessionid=%s&t=0.1';
    const URL_GET_GROUP_INFO      = 'http://s.web2.qq.com/api/get_group_info_ext2?gcode=%s&vfwebqq=%s&t=0.1';
    const URL_GET_DISCUSSES_INFO  = 'http://d1.web2.qq.com/channel/get_discu_info?did=%s&psessionid=%s&vfwebqq=%s&clientid=%s&t=0.1';
    const URL_GET_GROUP2          = 'http://qun.qq.com/cgi-bin/qun_mgr/get_group_list';
    const URL_REFERER_CLT_MEMBER  = 'http://qun.qq.com/member.html';
    const URL_LOGIN_QUN           = 'https://ptlogin2.qun.qq.com/check_sig?pttype=2&uin=%s&service=jump&nodirect=0&ptsigx=%s&s_url=https%%3A%%2F%%2Fqun.qq.com%%2Fmember.html&f_url=&ptlang=2052&ptredirect=100&aid=1000101&daid=73&j_later=0&low_login_hour=0&regmaster=0&pt_login_type=2&pt_aid=715030901&pt_aaid=0&pt_light=0&pt_3rd_aid=0?';
    /** @var Http $httpInstance */
    private $httpInstance;
    private $key;

    public function __construct()
    {
        /** @var Http $ActionHttp */
        $this->httpInstance = new Http();
    }

    /**
     * 获取登录二维码
     * @return array
     */
    public function getLoginQr()
    {
        $rand = rand(100, 960);
        $http = $this->http(true);
        $encode = $http->get(vsprintf(self::URL_PTQRSHOW, [$rand, $rand]));
        z::throwIf(!$encode, new QQException('Login QR code failed to get'));
        $encode = chunk_split(base64_encode($encode));
        $encode = 'data:image/png;base64,' . $encode . '';

        return ['base64' => $encode, 'key' => $this->getKey(), 'header' => $http->header(), 'cookie' => $http->cookie()];
    }

    public function http($referer = false)
    {
        if ($referer) {
            $this->httpInstance->setReferer(is_string($referer) ? $referer : self::URL_REFERER_PROXY);
        }
        $this->httpInstance->setCookieFilePath(z::realPathMkdir(z::config()->getStorageDirPath() . 'httpCookie/QQ/' . $this->getKey() . '.txt', false, true, false));

        return $this->httpInstance;
    }

    public function getKey()
    {
        if (!$this->key) {
            $this->key = uniqid();
        }

        return $this->key;
    }

    public function setKey($key)
    {
        $this->key = $key;
    }

    /**
     * 验证登录状态
     * @return array
     */
    public function verifyLoginStatus()
    {
        $http = $this->http(true);
        $qrsig = z::arrayGet($http->cookie(), 'qrsig');
        $data = $this->http(true)->get(vsprintf(self::URL_PTQR_LOGIN, [Utils::hash33($qrsig)]));
        switch (true) {
            case (false !== strpos($data, '登录成功')):
                if (!preg_match("#'(http.+)'#U", strval($data), $matches)) {
                    $result = ['status' => 0, 'status_text' => 'No certification link found'];
                    break;
                }
                $result = ['status' => self::STATUS_CERTIFICATION, 'status_text' => '认证成功'];
                $certificationUrl = trim($matches[1]);
                $this->http(true)->get($certificationUrl);
                $result['ptwebqq'] = z::arrayGet($this->http()->cookie(), 'ptwebqq');
                $this->http(true)->get(vsprintf(self::URL_GETVFWEBQQ, [$result['ptwebqq']]));
                $result['vfwebqq'] = z::arrayGet($this->http()->data(true), 'result.vfwebqq');
                $result['clientid'] = self::CLIENTID;
                $user = $this->loginQQ($result['ptwebqq'], $result['clientid']);
                $result['uin'] = z::arrayGet($user, 'uin');
                $result['psessionid'] = z::arrayGet($user, 'psessionid');
                $result['certificationUrl'] = $certificationUrl;
                parse_str(z::arrayGet(parse_url($certificationUrl), 'query'), $arg);
                $result['ptsigx'] = z::arrayGet($arg, 'ptsigx');
                //$this->loginQun($result['uin'], $result['ptsigx']);
                $cookie = $this->http()->cookie();
                //$result['pt_login_sig'] = z::arrayGet($cookie, 'pt_login_sig');
                $result['bkn'] = Utils::csrfToken(z::arrayGet($cookie, 'skey'));
                $result['cookie'] = $this->http()->cookie();
                $result['time'] = 60 * 60 + time();
                $result['online'] = $this->http(true)->get(vsprintf(self::URL_GET_ONLINE_BUDDIES2, [$result['vfwebqq'], $result['psessionid']]));
                $result['key'] = $this->getKey();
                $this->setCredential($result);
                break;
            case (false !== strpos($data, '未失效')):
                $result = ['status' => self::STATUS_UNEXPIRED, 'status_text' => '等待扫描'];
                break;
            case (false !== strpos($data, '已失效')):
                $result = ['status' => self::STATUS_EXPIRED, 'status_text' => '二维码已失效'];
                break;
            case (false !== strpos($data, '认证中')):
                $result = ['status' => self::STATUS_ACCREDITATION, 'status_text' => '认证中'];
                break;
            default:
                $result = [];
        }
        z::throwIf(!$result, new QQException('No certification link found, please log in again! ' . $data));

        return $result;
    }

    private function loginQQ($ptwebqq, $clientid)
    {
        $data = [
            'ptwebqq' => $ptwebqq,
            'clientid' => $clientid,
            'psessionid' => '',
            'status' => 'online',
        ];
        $http = $this->http(true);
        $http->post(self::URL_LOGIN, ['r' => json_encode($data)]);
        $jsonData = $http->data(true);
        $uin = z::arrayGet($jsonData, 'result.uin');
        z::throwIf(!$uin, new QQException('Can not find argument [uin] and [psessionid], ' . json_encode($data)));

        return ['uin' => $uin, 'psessionid' => $jsonData['result']['psessionid']];
    }

    public function setCredential($data)
    {
        z::cache()->set('QQ_Credential_' . $this->getKey(), $data, 60 * 60);
    }

    /**
     * 获取好友列表
     * @return array|string
     */
    public function getFriends()
    {
        $lists = $this->getLists(self::GET_FRIENDS);
        $friends = &$lists['friends'];
        $vipinfo = array_column($lists['vipinfo'], 'u');
        $info = array_column($lists['info'], 'uin');
        $marknames = array_column($lists['marknames'], 'uin');
        foreach ($friends as $k => &$v) {
            $mkey = array_search($v['uin'], $marknames);
            $v['markname'] = $mkey !== false ? z::arrayGet($lists['marknames'], $mkey . '.markname', '') : '';
            $vkey = array_search($v['uin'], $vipinfo);
            $v['vip_level'] = $vkey !== false ? z::arrayGet($lists['vipinfo'], $vkey . '.vip_level', 0) : 0;
            $ikey = array_search($v['uin'], $info);
            $v['face'] = $ikey !== false ? z::arrayGet($lists['info'], $ikey . '.face', '') : '';
            $v['nick'] = $ikey !== false ? z::arrayGet($lists['info'], $ikey . '.nick', '') : '';
            unset($lists['info'][$ikey], $lists['marknames'][$mkey], $lists['vipinfo'][$vkey]);
        }

        return z::arrayFilter($lists, function ($v) {
            return !!$v;
        });
    }

    /**
     * 获取列表
     * @param $url
     * @return array|string
     */
    private function getLists($url)
    {
        $token = $this->getCredential();
        $vfwebqq = z::arrayGet($token, 'vfwebqq');
        $post = ['hash' => Utils::hash($token['uin'], $token['ptwebqq']), 'vfwebqq' => $vfwebqq];
        $postData = ['r' => json_encode($post)];
        $http = $this->http(true);
        $json = $http->post($url, $postData);

        return $this->verifyTheData($json);
    }

    public function getCredential()
    {
        return z::tap(z::cache()->get('QQ_Credential_' . $this->getKey()), function ($result) {
            if (!!$result && z::arrayGet($result, 'time', 0) <= time()) {
                $result['time'] = z::arrayGet($result, 'time', time()) + 60 * 60;
                $this->setCredential($result);
            }
        });
    }

    /**
     * 验证数据
     * @param $data
     * @return mixed
     */
    private function verifyTheData($data)
    {
        $json = @json_decode($data, true);
        $result = z::arrayGet($json, 'result', []);
        $success = (0 === z::arrayGet($json, 'retcode') || 15 === z::arrayGet($json, 'retcode'));
        z::throwIf(!$success, new QQException('Access error: ' . $data));

        return $result;
    }

    /**
     * 发送好友消息
     * @param $friend
     * @param $content
     * @return bool
     */
    public function sendFriend(array $friend, $content)
    {
        $credential = $this->getCredential();
        $postData = array_merge(['to' => $friend['uin']], $this->template(new Content($content), $credential, $friend));

        return $this->send(self::URL_FRIEND_SEND, $postData);
    }

    /**
     * 消息模板组合
     * @param $content
     * @param $credential
     * @param $data
     * @return array
     */
    private function template($content, $credential, $data)
    {
        $this->http()->setReferer(self::URL_REFERER_CFPROXY);

        return [
            'content' => (string)$content,
            'face' => z::arrayGet($data, 'face', ''),
            'clientid' => $credential['clientid'],
            'msg_id' => Utils::makeMsgId(),
            'psessionid' => $credential['psessionid'],
        ];
    }

    /**
     * 发送消息
     * @param $url
     * @param $postData
     * @return bool
     */
    private function send($url, $postData)
    {
        $postData = ['r' => json_encode($postData)];
        $this->http()->post($url, $postData);
        $data = $this->http()->data(true);
        z::throwIf(z::arrayGet($data, 'retcode') !== 0, new QQException('failed To Send: ' . $this->http()->data()));

        return true;
    }

    /**
     * 接收消息
     */
    public function pullMessages()
    {
        $data = $this->pullNewMessages();
        $result = z::arrayGet($data, 'result', []);
        $isNotNewMessage = z::arrayGet($data, 'errmsg') === 'error';
        foreach ($result as $k => &$v) {
            $v['user_message'] = (int)in_array($v['poll_type'], ['message', 'group_message', 'discu_message']);
            if ($v['user_message']) {
                $content = z::arrayGet($v, 'value.content', []);
                $v['font'] = array_shift($content);
                if (is_string($content)) {
                    $v['content'] = $content;
                } else {
                    $last = '';
                    $v['content'] = join('', z::arrayMap($content, function ($v) use (&$last) {
                        $isText = is_string($v) && !preg_match('/^\[.*\]$/', $v);

                        return z::tap($isText ? ($last ? '[图片]' . $v : $v) : (is_array($v) ? '[' . Content::searchFaceText($v[1]) . ']' : $v), function () use (&$last, $isText) {
                            $last = $isText;
                        });
                    }));
                }
                if (!$v['content']) {
                    $v['content'] = '[图片]';
                }
            } else {
                z::log($result, 'wwwwwwwwwwwwwwwwww');
            }
        }

        return $result;
    }

    private function pullNewMessages()
    {
        $credential = $this->getCredential();
        $url = self::URL_PULLMESSAGES;
        $postData = [
            'ptwebqq' => '',
            'clientid' => $credential['clientid'],
            'psessionid' => $credential['psessionid'],
            'key' => '',
        ];
        $http = $this->http(true);
        $json = $http->post($url, json_encode($postData));
        $data = @json_decode($json, true);
        $errorCode = $http->errorCode();
        $timeout = $errorCode === 28;
        $fail = (0 !== z::arrayGet($data, 'retcode') && !$timeout);
        z::throwIf($fail, new QQException('Receive failure: ' . $json));
        z::log($http->cookie(), 'ccc');

        return $timeout ? [] : $data;
    }

    /**
     * 发送群消息
     * @param array $qun
     * @param       $content
     * @return bool
     */
    public function sendQun(array $qun, $content)
    {
        $credential = $this->getCredential();
        $postData = array_merge(['group_uin' => $qun['gid']], $this->template(new Content($content), $credential, $qun));

        return $this->send(self::URL_QUN_SEND, $postData);
    }

    /**
     * 发送讨论组消息
     * @param array $discusses
     * @param       $content
     * @return bool
     */
    public function sendDiscusses(array $discusses, $content)
    {
        $credential = $this->getCredential();
        $postData = array_merge(['did' => $discusses['did']], $this->template(new Content($content), $credential, $discusses));

        return $this->send(self::URL_DISCUSSES_SEND, $postData);
    }

    public function reSetKey($key)
    {
        if ($key) {
            $this->setKey($key);
        }
        $this->setCredential([]);
    }

    /**
     * 获取所有的群.
     */
    public function getGroups()
    {
        return $this->getLists(self::URL_GET_GROUP);
    }

    /**
     * 获取好友详情
     * @param int $uin
     * @return array|string
     */
    public function getFriendInfo(int $uin)
    {
        $credential = $this->getCredential();
        $clientid = $credential['clientid'];
        $vfwebqq = $credential['vfwebqq'];
        $psessionid = $credential['psessionid'];
        $url = vsprintf(self::URL_FRIEND_INFO, [$uin, $vfwebqq, $clientid, $psessionid]);
        $http = $this->http(true);
        $json = $http->get($url);

        return $this->verifyTheData($json);
    }

    /**
     * 获取讨论组
     */
    public function getDiscusses()
    {
        $credential = $this->getCredential();
        $psessionid = $credential['psessionid'];
        $clientid = $credential['clientid'];
        $vfwebqq = $credential['vfwebqq'];
        z::dump($credential, $this->getKey());

        return $this->getLists(vsprintf(self::GET_DISCUSSES, [$clientid, $psessionid, $vfwebqq]));
    }

    /**
     * 获取讨论组详情
     * @param $did
     * @return array
     */
    public function getDiscussesInfo($did)
    {
        $credential = $this->getCredential();
        $psessionid = $credential['psessionid'];
        $vfwebqq = $credential['vfwebqq'];
        $clientid = $credential['clientid'];
        $url = vsprintf(self::URL_GET_DISCUSSES_INFO, [$did, $psessionid, $vfwebqq, $clientid]);
        $http = $this->http(true);
        $json = $http->get($url);

        return $this->verifyTheData($json);
    }

    /**
     * 获取群详情
     * @param int $code
     * @return array
     */
    public function getGroupsInfo(int $code)
    {
        $credential = $this->getCredential();
        $vfwebqq = $credential['vfwebqq'];
        $url = vsprintf(self::URL_GET_GROUP_INFO, [$code, $vfwebqq]);
        $http = $this->http(true);
        $json = $http->get($url);
        if (!$result = $this->verifyTheData($json)) {
            return [];
        }
        $members = array_column(z::arrayGet($result, 'ginfo.members', []), 'muin');
        $vipinfo = array_column(z::arrayGet($result, 'vipinfo', []), 'u');
        $stats = array_column(z::arrayGet($result, 'stats', []), 'uin');
        $cards = array_column(z::arrayGet($result, 'cards', []), 'muin');
        $minfo = z::arrayGet($result, 'minfo', []);
        foreach ($minfo as &$v) {
            $skey = array_search($v['uin'], $stats);
            if ($skey !== false) {
                $statses = $result['stats'][$skey];
                $v['client_type'] = $statses['client_type'];
                $v['stat'] = $statses['stat'];
                unset($result['stats'][$skey]);
            } else {
                $v['client_type'] = 0;
                $v['stat'] = 0;
            }
            $ikey = array_search($v['uin'], $vipinfo);
            $v['vip_level'] = $ikey !== false ? z::arrayGet($result['vipinfo'], $ikey . '.vip_level', 0) : '';
            $mkey = array_search($v['uin'], $members);
            $v['mflag'] = $mkey !== false ? z::arrayGet($result['ginfo']['members'], $mkey . '.mflag', 0) : 0;
            $v['card'] = '';
            if ($cards) {
                $ckey = array_search($v['uin'], $cards);
                $v['card'] = $ckey !== false ? z::arrayGet($result['cards'], $ckey . '.card', '') : '';
                unset($result['cards'][$ckey]);
            }
            unset($result['ginfo']['members'][$mkey], $result['vipinfo'][$ikey]);
        }
        $result['minfo'] = $minfo;
        unset($result['vipinfo'], $result['ginfo']['members'], $result['cards'], $result['stats']);

        return $result;
    }

    public function test()
    {
        $http = $this->http('https://qun.qq.com');
        $skey = z::arrayGet($http->cookie(), 'skey', '');
        $http = $this->http('https://qun.qq.com/member.html');
        //$http->get('https://qun.qq.com/member.html');
        $http->setCookie([
                             'p_skey' => '0kuCCMeGSpVFqClNtZICyGDN6xQO046PdpnzGWdJdS4_',
                             'uin' => 'o0378223838',
                             'skey' => '@6WMPZdwxR',
                         ]);
        $url = "http://qinfo.clt.qq.com/cgi-bin/qun_info/get_group_members";
        $http = $this->http('http://qinfo.clt.qq.com/member.html');
        $skey = z::arrayGet($http->cookie(), 'skey', '');
        $postData = [
            'gc' => 568071523,
            'bkn' => Utils::csrfToken($skey),
        ];
    }

    public function getGroups2()
    {
        $credential = $this->getCredential();
        $uid = $credential['uin'];
        $ptsigx = $credential['ptsigx'];
        $http = $this->http(self::URL_REFERER_CLT_MEMBER);
        $qrsig = z::arrayGet($http->cookie(), 'qrsig');
        $ptLoginSigqrsig = z::arrayGet($http->cookie(), 'pt_login_sig');
        $getData = [
            'u1' => self::URL_REFERER_CFPROXY,
            'ptqrtoken' => Utils:: hash33($qrsig),
            'ptredirect' => 0,
            'h' => 1,
            't' => 1,
            'g' => 1,
            'from_ui' => 1,
            'ptlang' => 2052,
            'action' => '0-0-1516082717616',
            'js_ver' => 10233,
            'js_type' => 1,
            'login_sig' => $ptLoginSigqrsig,
            'pt_uistyle' => 40,
            'aid' => 501004106,
            'daid' => 164,
            'mibao_css' => 'm_webqq',
        ];
        $u = 'https://ptlogin2.qun.qq.com/check_sig?pttype=2&uin=' . $uid . '&service=jump&nodirect=0&ptsigx=' . $ptsigx . '&s_url=https%3A%2F%2Fqun.qq.com%2Fmember.html&f_url=&ptlang=2052&ptredirect=100&aid=1000101&daid=73&j_later=0&low_login_hour=0&regmaster=0&pt_login_type=2&pt_aid=715030901&pt_aaid=0&pt_light=0&pt_3rd_aid=0&';
        $a = $http->get('https://qun.qq.com/member.html');
        $a = $http->get($u, [], [], 0);
        $a = $http->header();
        $http = $this->http(self::URL_REFERER_CLT_MEMBER);
        $skey = z::arrayGet($http->cookie(), 'skey', '');

        return $http->post(self::URL_GET_GROUP2, ['bkn' => Utils::csrfToken($skey)]);
    }

    private function loginQun($uin, $ptsigx)
    {
        $http = $this->http(self::URL_REFERER_CLT_MEMBER);
        $cookie = $http->cookie();
        $http->get(vsprintf(self::URL_LOGIN_QUN, [$uin, $ptsigx]));
    }
}
