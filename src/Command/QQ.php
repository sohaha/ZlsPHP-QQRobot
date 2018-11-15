<?php declare(ticks=1);

namespace Zls\QQ\Command;

use Z;
use Zls\QQ\QQException;

class QQ extends \Zls\Command\Command
{
    /** @var \Zls\QQ\Main $robot */
    private $robot;

    public function __construct()
    {
        parent::__construct();
        $this->robot = new \Zls\QQ\Main();
    }

    /**
     * 命令配置.
     * @return array
     */
    public function options()
    {
        return [
            'key' => 'QQ本地识别key，缓存存储30分钟',
        ];
    }

    /**
     * 命令介绍.
     * @return string
     */
    public function description()
    {
        return 'QQ机器人';
    }

    public function commands()
    {
        return true;
    }

    /**
     * 获取群列表
     * @param $args
     */
    public function getGroups($args)
    {
        $this->log('获取群列表');
        $lists = $this->robot->getGroups();
        $gnamelist = z::arrayGet($lists, 'gnamelist', []);
        $count = count($gnamelist);
        $this->log("当前共有 {$count} 个群");
        if ($gnamelist) {
            $this->printStr(date('[y-m-d H:i:s] '), 'dark_gray');
            $this->printStrN("[群列表] ", 'cyan');
            foreach ($gnamelist as $item) {
                $this->printStrN(" [{$item['code']}] {$item['name']} ");
            }
        }
    }

    private function log($msg, $type = '提示')
    {
        $this->printStr(date('[y-m-d H:i:s] '), 'dark_gray');
        $this->printStrN("[{$type}] $msg", 'dark_gray');
    }

    /**
     * 获取好友列表
     */
    public function getFriends()
    {
        $this->log('获取好友列表');
        $list = $this->robot->getFriends();
        $friends = z::arrayGet($list, 'friends', []);
        $count = count($friends);
        $this->log("当前共有 {$count} 个好友");
        if ($friends) {
            $this->printStr(date('[y-m-d H:i:s] '), 'dark_gray');
            $this->printStrN("[好友列表] ", 'cyan');
            foreach ($friends as $item) {
                $name = $item['markname'] ?: $item['nick'];
                $this->printStrN(" [{$item['uin']}] {$name} ");
            }
        }
    }

    /**
     * 发送好友信息
     * @param $args
     * @return mixed|string
     */
    public function send($args)
    {
        $uin = z::arrayGet($args, 3);
        $content = z::arrayGet($args, 4);
        if (!$uin || !$content) {
            $this->log('命令格式错误, send -u uin 内容');
        } else {
            $friend = [
                "face" => 0,
                "flag" => 0,
                "nick" => "",
                "uin" => (int)$uin,
            ];
            try {

                $this->robot->sendFriend($friend, $content);
                $this->log('发送成功');
            } catch (QQException $e) {
                $this->error($e->getMessage() . json_encode($friend));
            }
        }
    }

    /**
     * 命令默认执行.
     * @param $args
     */
    public function execute($args)
    {
        $key = $this->getKey($args);
        $reset = z::arrayGet($args, ['r', '-reset']);
        $this->robot->setKey($key);
        $method = z::arrayGet($args, 2);
        $hasMethod = $method && method_exists($this, $method);
        if ($reset) {
            $tip = $key ? '清除Key: ' . $key . '，重新登录' : '请先登录';
            $this->robot->reSetKey($key);
        } else {
            $tip = $key ? 'Key: ' . $key . '不存在，请重新登录' : '请先登录';
        }
        $credential = $this->robot->getCredential();
        if (!$key) {
            z::cache()->set(__METHOD__, $this->robot->getKey(), 3600);
            $key = $this->robot->getKey();
        }
        if (!$credential) {
            $this->log($tip);
        }
        if ($method) {
            if ($hasMethod) {
                $this->$method($args);
            } else {
                $this->error('未知方法: '.$method);
            }
        } else {
            $this->help($args);
        }
    }

    public function getKey($args)
    {
        $key = z::arrayGet($args, ['key', '-key'], z::cache()->get(__METHOD__));
        if (!$key) {
            $key = uniqid();
            z::cache()->set(__METHOD__, $key, 3600);
        }

        return $key;
    }

    public function show($args)
    {
        $key = $this->getKey($args);
        print_r($this->robot->getCredential());
    }

    public function login($args)
    {
        $key = $this->getKey($args);
        $file = '';
        $login = function () use (&$file) {
            $this->log('获取登录二维码');
            $qr = $this->robot->getLoginQr();
            $base64 = z::arrayGet($qr, 'base64');
            $bol = base64_decode(str_replace('data:image/png;base64,', '', $base64));
            /** @var \Zls\Action\Http $http */
            $http = z::extension('Action\Http');
            $file = z::realPath(z::tempPath(), true) . md5($base64) . '.png';
            file_put_contents($file, $bol);
            $this->log('二维码保存本地 [' . $this->color($file, 'cyan') . ']');
            $http->post('https://sm.ms/api/upload', ['smfile' => '@' . $file], [], 0, true);
            $result = $http->data(true);
            if (z::arrayGet($result, 'code') === 'success') {
                $this->log('二维码已上传云存储 [' . $this->color($result['data']['url'], 'cyan') . ']');
            }
            $this->log('请手动打开二维码扫描');
            sleep(2);
        };
        $login();
        while (true) {
            $status = $this->robot->verifyLoginStatus();
            $code = $status['status'];
            $text = $status['status_text'];
            if ($code === $this->robot::STATUS_UNEXPIRED || $code === $this->robot::STATUS_ACCREDITATION) {
                sleep(1);
                continue;
            }
            $this->log($text);
            if ($code === $this->robot::STATUS_EXPIRED) {
                unlink($file);
                $login();
            } elseif ($code === $this->robot::STATUS_CERTIFICATION) {
                unlink($file);
                $this->robot = new \Zls\QQ\Main();
                $this->robot->setKey($key);
                $this->log('设置Key: ' . $key);
                break;
            }
        }
        $this->pull();
    }

    public function pull()
    {
        $credential = $this->robot->getCredential();
        $uin = z::arrayGet($credential, 'uin');
        $this->log('账号: ' . $uin . '（' . $this->robot->getKey() . '），开始接收消息');
        while (true) {
            try {
                $messages = $this->robot->pullMessages();
                foreach ($messages as $msg) {
                    $content = $msg['content'];
                    $value = $msg['value'];
                    $fromUin = z::arrayGet($value, 'send_uin', z::arrayGet($value, 'from_uin'));
                    z::log([$msg, '--']);
                    $prefix = '';
                    $isGroupMessage = $msg['poll_type'] === 'group_message';
                    $isDiscuMessage = $msg['poll_type'] === 'discu_message';
                    $type = $isGroupMessage ? '群消息' : ($isDiscuMessage ? '讨论组消息' : '好友消息');
                    $isMe = $fromUin === $uin ? $this->color(" [自己]", 'red') : '';
                    if ($isGroupMessage && $group = $this->updateGroup(z::arrayGet($msg, 'value.group_code'))) {
                        $prefix = $group['ginfo']['name'];
                        $minfo = z::arrayGet($group, 'minfo', []);
                        $sendUin = z::arrayGet($value, 'send_uin');
                        $index = array_search($sendUin, array_column($minfo, 'uin'));
                        $friend = z::arrayGet($minfo, $index, []);
                        $prefix = ($friend ? $friend['nick'] : $sendUin) . ' | ' . $prefix;
                        $prefix = $this->color("[{$prefix}]", 'light_green') . $isMe;
                    } elseif ($isDiscuMessage && $deiscu = $this->updateDeiscu(z::arrayGet($msg, 'value.did'))) {
                        $prefix = $this->color("[{$deiscu['info']['discu_name']}]", 'light_green') . $isMe;
                    } elseif ($friend = $this->updateFriend(z::arrayGet($value, 'from_uin'))) {
                        $prefix = $this->color("[{$friend['nick']}]", 'light_green') . $isMe;
                    }
                    $this->printStr(date('[y-m-d H:i:s] '), 'dark_gray');
                    $this->printStr("[{$type}] ", 'cyan');
                    $this->printStrN($prefix . PHP_EOL . str_pad(' ', 1) . $content, 'green');
                }
            } catch (QQException $e) {
                $this->printStr('[ QQError ] ', 'red');
                $this->printStrN($e->getMessage());
            } catch (\Exception $e) {
                $this->printStr('[ Error ] ', 'red');
                $this->printStrN($e->getMessage());
            }
            sleep(1);
        }
    }

    public function updateGroup($gcode)
    {
        static $groupData, $groupLists, $groupListsIndex;
        if (is_null($groupLists)) {
            $groupLists = $this->robot->getGroups();
            $groupListsIndex = array_column(z::arrayGet($groupLists, 'gnamelist', []), 'gid');
        }
        $gnamelist = z::arrayGet($groupLists, 'gnamelist');
        $index = array_search($gcode, $groupListsIndex);
        $code = z::arrayGet($gnamelist, $index . '.code', 0);
        $result = z::arrayGet($groupData, $gcode);
        if (is_null($result)) {
            if ($code) {
                $result = $this->robot->getGroupsInfo($code);
                $name = z::arrayGet($result, 'ginfo.name', '');
                $this->printStr(date('[y-m-d H:i:s] '), 'dark_gray');
                if (!$name) {
                    $name = $result['ginfo']['name'] = '{' . $code . '}';
                } else {
                    $name = $name . ' - ' . $code;
                }
            } else {
                $result['ginfo']['name'] = $name = '{g' . $gcode . '}';
            }
            $this->printStrN("[更新群] {$name}", 'dark_gray');
            $groupData[$gcode] = $result;
        }

        return $result;
    }

    private function updateDeiscu($did)
    {
        static $data;
        $result = z::arrayGet($data, $did);
        if (is_null($result)) {
            $result = $this->robot->getDiscussesInfo($did);
            $friendData[$did] = $result;
        }

        return $result;
    }

    private function updateFriend($uin)
    {
        static $friendData;
        $result = z::arrayGet($friendData, $uin);
        if (is_null($result)) {
            $result = $this->robot->getFriendInfo($uin);
            $nick = z::arrayGet($result, 'nick', '');
            $this->printStr(date('[y-m-d H:i:s] '), 'dark_gray');
            if (!$nick) {
                $nick = $result['nick'] = '{' . $uin . '}';
            } else {
                $nick = $nick . ' - ' . $uin;
            }
            $this->printStrN("[更新好友] {$nick}", 'dark_gray');
            $friendData[$uin] = $result;
        }

        return $result;
    }
}
