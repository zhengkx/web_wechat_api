<?php
require_once('./mysql.php');

class loginWX
{
    public $uuid;
    public $loginUrl;

    function getUUId()
    {
        $url    = 'https://login.wx2.qq.com/jslogin?appid=wx782c26e4c19acffb&fun=new&lang=zh_CN';
        $result = $this->get($url);

        $premg = "/\"(.*?)\"/";

        preg_match($premg, $result, $uuid);

        $this->uuid = substr($uuid[0], 1, 12);
        
        $_SESSION['uuid'] = $this->uuid;

        return true;
    }

    /**
     * 获取二维码
     *
     * @return void
     *
     * @author zhengkexin
     *
     * @created 2019-04-13 15:19:51
     */
    function getQrCodeImg()
    {
        $this->getUUId();

        $url = "https://login.wx2.qq.com/qrcode/$this->uuid?t=webwx";

        echo $url;
    }
    
    /**
     * 轮询检查是否扫码确认
     *
     * @return void
     *
     * @author zhengkexin
     *
     * @created 2019-04-13 15:19:25
     */
    function checkIsBin()
    {
        $this->uuid = $_SESSION['uuid'];

        $url = "https://login.wx2.qq.com/cgi-bin/mmwebwx-bin/login?tip=1&uuid=$this->uuid";

        $res = $this->get($url);

        $prem = '/=(.*?);/';

        $data = array('status' => 0);
        $data['data']   = '';

        preg_match($prem, $res, $match);
        if ($match[1] == '200') {
            $loginPrem = "/redirect_uri=\"(.*?)\";/";

            preg_match($loginPrem, $res, $loginMatch);

            $this->loginUrl = $loginMatch[1];

            $data['status'] = 1;
            $data['data']   = $this->loginUrl;
        }

        exit(json_encode($data));
    }

    /**
     * 获取公参
     *
     * @return void
     *
     * @author zhengkexin
     *
     * @created 2019-04-13 15:19:03
     */
    function getPublicpartic($url)
    {
        $res = $this->getAndCookices($url);

        $_SESSION['DeviceID'] = 'e545297464380306';

        $data['skey']        = $_SESSION['skey'];
        $data['wxsid']       = $_SESSION['sid'];
        $data['wxuin']       = $_SESSION['uin'];
        $data['pass_ticket'] = $_SESSION['pass_ticket'];
        $data['DeviceID']    = $_SESSION['DeviceID'];

        exit(json_encode($data));
    }

    /**
     * 微信初始化
     *
     * @return void
     *
     * @author zhengkexin
     *
     * @created 2019-04-13 15:35:01
     */
    function wxInit($params)
    {
        $cookie_jar = dirname(__FILE__) . "/" . $_SESSION['uuid'] . ".cookie";

        $passTicket = $params['pass_ticket'];

        $r = $this->getMillisecond();

        $url = "https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxinit?r=$r";

        $data['BaseRequest'] = [
            'Uin'      => $_SESSION['uin'],
            'Skey'     => $_SESSION['skey'],
            'Sid'      => $_SESSION['sid'],
            'DeviceID' => $_SESSION['DeviceID'],
        ];

        $res = $this->post($url, json_encode($data), $cookie_jar);
        $user = json_decode($res, true);
        $_SESSION['SyncKey'] = $user['SyncKey'];
        $_SESSION['username'] = $user['User']['UserName'];
        $_SESSION['nickname'] = $user['User']['NickName'];

        print_r($res);die;
    }

    /**
     * 获取好友列表
     *
     * @return void
     *
     * @author zhengkexin
     *
     * @created 2019-04-13 17:28:31
     */
    function getList()
    {
        $cookie_jar = dirname(__FILE__) . "/" . $_SESSION['uuid'] . ".cookie";
        $pass_ticket = $_SESSION['pass_ticket'];
        $skey = $_SESSION['skey'];
        $r = $this->getMillisecond();

        $url = "https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxgetcontact?lang=zh_CN&seq=0&r=$r&pass_ticket=$pass_ticket&skey=$skey";
        
        $data['BaseRequest'] = [
            'Uin'      => $_SESSION['uin'],
            'Skey'     => $_SESSION['skey'],
            'Sid'      => $_SESSION['sid'],
            'DeviceID' => $_SESSION['DeviceID'],
        ];

        $res = $this->post($url, json_encode($data), $cookie_jar);

        exit($res);
    }

    /**
     * 检查是否有消息
     *
     * @return void
     *
     * @author zhengkexin
     *
     * @created 2019-04-15 11:25:51
     */
    function syncCheck()
    {
        $pass_ticket = $_SESSION['pass_ticket'];

        $r = $this->getMillisecond();
        
        $syncKey = $this->formatSyncKey($_SESSION['SyncKey']["List"]);

        $sid = $_SESSION['sid'];
        $uin = $_SESSION['uin'];
        $deviceid = $_SESSION['DeviceID'];

        $url = "https://webpush.wx2.qq.com/cgi-bin/mmwebwx-bin/synccheck?r=$r&lang=zh_CN&pass_ticket=$pass_ticket&sid=$sid&uin=$uin&deviceid=$deviceid&synckey=$syncKey";
        
        $res = $this->get($url);
        
        preg_match('/1101/', $res, $errCode);

        if (isset($errCode) && $errCode) {
            $errMsg['selector'] = 0;
            $errMsg['retcode']  = 1101;

            exit(json_encode($errMsg));
        }

        preg_match('/retcode:"(.)"/', $res, $retcode);
        preg_match('/selector:"(.)"/', $res, $selector);
        
        $data['selector'] = $selector[1];
        $data['retcode']  = $retcode[1];

        exit(json_encode($data));
    }

    /**
     * 保持与服务器的信息同步，获取是否有推送消息等
     *
     * @return void
     *
     * @author zhengkexin
     *
     * @created 2019-04-13 17:33:11
     */
    function wxsync()
    {   
        $synckey     = $_SESSION['SyncKey'];
        $uin         = $_SESSION['uin'];
        $sid         = $_SESSION['sid'];
        $skey        = $_SESSION['skey'];
        $pass_ticket = $_SESSION['pass_ticket'];

        $r = $this->getMillisecond();

        $cookie_jar = dirname(__FILE__) . "/" . $_SESSION['uuid'] . ".cookie";
        $url = "https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxsync?sid=$sid&uin=$uin&skey=$skey&r=$r&lang=zh_CN&pass_ticket=$pass_ticket";

        $data['BaseRequest'] = [
            'Uin'      => $_SESSION['uin'],
            'Skey'     => $_SESSION['skey'],
            'Sid'      => $_SESSION['sid'],
            'DeviceID' => $_SESSION['DeviceID'],
        ];

        $data['SyncKey'] = $synckey;
        $data['rr'] = time();

        $res = $this->post($url, json_encode($data), $cookie_jar);

        $content = json_decode($res, true);

        if ($content['BaseResponse']['Ret'] == 0) {
            $this->insertMessageToDB($content['AddMsgList']);
        }

        $_SESSION['SyncKey'] = $content['SyncKey'];

        exit($res);
    }

    /**
     * 插入数据库
     *
     * @param [type] $addMsgList
     * @return void
     *
     * @author zhengkexin
     *
     * @created 2019-04-15 17:24:58
     */
    function insertMessageToDB($addMsgList)
    {
        if (count($addMsgList) <= 0) {
            return ;
        }

        $sql = '';
        foreach ($addMsgList as $key => $value) {
            $msgId      = $value['MsgId'];
            $uin        = $_SESSION['uin'];
            $from_user  = $value['FromUserName'];
            $to_user    = $value['ToUserName'];
            $content    = $value['Content'];
            $send_time  = $value['CreateTime'];
            $allcontent = json_encode($value);
            $createTime = date('Y-m-d H:i:s');

            $sql .= "INSERT INTO wechat_messages (uin, msg_id, from_user, to_user, content, all_content, send_time, created_at) VALUES ('$uin', '$msgId', '$from_user', '$to_user', '$content', '$allcontent', '$send_time', '$createTime');";

        }
        $mysqlPB = new mysqlClass();

        $res = $mysqlPB->queryInsert($sql);
    }

    /**
     * 发送消息
     *
     * @param [type] $toUserName
     * @param string $content
     * @return void
     *
     * @author zhengkexin
     *
     * @created 2019-04-13 17:28:51
     */
    function sendMessage($toUserName, $content = '')
    {
        $uin = $_SESSION['uin'];
        $sid = $_SESSION['sid'];
        $pass_ticket = $_SESSION['pass_ticket'];
        $cookie = dirname(__FILE__) . "/" . $_SESSION['uuid'] . ".cookie";

        $r = $this->getMillisecond();

        $url = "https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxsendmsg?r=$r&pass_ticket=$pass_ticket";

        $data['BaseRequest'] = [
            'Uin'      => $_SESSION['uin'],
            'Skey'     => $_SESSION['skey'],
            'Sid'      => $_SESSION['sid'],
            'DeviceID' => $_SESSION['DeviceID'],
        ];
        $data['Msg'] = [
            'Type' => 1,
            'Content' => $content,
            "FromUserName" => $_SESSION['username'],
            'ToUserName' => $toUserName,
            'LocalID' => $r,
            'ClientMsgId' => $r,
        ];
        $data['Scene'] = 0;

        $res = $this->post($url, json_encode($data, JSON_UNESCAPED_UNICODE), $cookie);

        exit($res);
    }

    /**
     * 获取联系人详情
     *
     * @return void
     *
     * @author zhengkexin
     *
     * @created 2019-04-16 10:52:02
     */
    function getContactDetail($userName)
    {
        $r = $this->getMillisecond();

        $pass_ticket = $_SESSION['pass_ticket'];

        $cookie = dirname(__FILE__) . "/" . $_SESSION['uuid'] . ".cookie";

        $url = "https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxbatchgetcontact?r=$r&pass_ticket=$pass_ticket&type=ex";

        $data['BaseRequest'] = [
            'Uin'      => $_SESSION['uin'],
            'Skey'     => $_SESSION['skey'],
            'Sid'      => $_SESSION['sid'],
            'DeviceID' => $_SESSION['DeviceID'],
        ];
        $data['Count'] = 1;
        $data['List'][] = [
            'UserName' => $userName,
            "EncryChatRoomId" => ''
        ];

        $res = $this->post($url, json_encode($data, JSON_UNESCAPED_UNICODE), $cookie);

        exit($res);
    }

    /**
     * 格式synckey
     *
     * @return void
     *
     * @author zhengkexin
     *
     * @created 2019-04-15 15:28:01
     */
    function formatSyncKey($key)
    {
        if ($key) {
            $num = count($key);
            $str = '';
            foreach ($key as $key => $value) {
                $str .= implode('_', $value);

                if ($num > $key + 1) {
                    $str .= '|';
                }
            }

            return $str;
        }

        return '';
    }

    function getAndCookices($url = '')
    { 
        $cookie_jar = dirname(__FILE__) . "/" . $_SESSION['uuid'] . ".cookie";

        $ch = curl_init($url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查  
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
        curl_setopt($ch, CURLOPT_HEADER, 1); //如果你想把一个头包含在输出中，
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //将 curl_exec()获取的信息以文件流的形式返回，而不是直接输出。设置为0是直接输出
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_COOKIEJAR, $cookie_jar); //获取的cookie 保存到指定的 文件路径
        $content = curl_exec($ch);
        if (curl_errno($ch)) {
            $info = array('status' => 0, 'msg' => 'Curl error: ' . curl_error($ch));
            return $info; //这里是设置个错误信息的反馈
        }

        if ($content == false) {
            $info = array('status' => 0, 'msg' => '无法获取cookies');
            return $info; //这里是设置个错误信息的反馈
        }

        //正则匹配出wxuin、wxsid
        preg_match('/wxuin=(.*);/iU', $content, $uin);
        preg_match('/wxsid=(.*);/iU', $content, $sid);
        preg_match('/webwx_data_ticket=(.*);/iU', $content, $webwx);
        preg_match('/<pass_ticket>(.*)<\/pass_ticket>/', $content, $passt);
        preg_match( '/<skey>(.*)<\/skey>/', $content, $skey);
        
        //将wxuin、wxsid、webwx_data_ticket存入session
        $_SESSION['uin'] = @$uin[1];
        $_SESSION['sid'] = @$sid[1];
        $_SESSION['pass_ticket'] = @$passt[1];
        $_SESSION['skey'] = @$skey[1];
        $wxinfo = array(
            'uin'         => @$uin[1],
            'sid'         => @$sid[1],
            'pass_ticket' => @$passt[1],
            'skey'        => @$skey[1]
        );
        curl_close($ch);

        return $wxinfo;
    }

    function get($url = '', $cookie = '')
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查  
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在 
        if ($cookie) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
            curl_setopt($ch, CURLOPT_REFERER, 'https://wx2.qq.com');
        }
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1); //将curl_exec()获取的信息以文件流的形式返回，而不是直接输出。
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        $output = curl_exec($ch);
        curl_close($ch);

        return $output;
    }

    /**
     * 发起POST请求
     *
     * @access public
     * @param string $url
     * @param array $data
     * @return string
     */
    public function post($url, $data = '', $cookie = '', $type = 0)
    {
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); // 对认证证书来源的检查  
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false); // 从证书中检查SSL加密算法是否存在
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_HEADER, 0);
        if ($cookie) {
            curl_setopt($ch, CURLOPT_COOKIEFILE, $cookie);
            curl_setopt($ch, CURLOPT_REFERER, 'https://wx.qq.com');
        }
        if ($type) {
            $header = array(
                'Content-Type: application/json',
            );
            curl_setopt($ch, CURLOPT_HTTPHEADER, $header);
        }

        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        $output = curl_exec($ch);
        curl_close($ch);
        return $output;
    }

    /**
     * 获取当前时间戳精确到毫秒级
     *
     * @return void
     *
     * @author zhengkexin
     *
     * @created 2019-04-13 16:41:11
     */
    private function getMillisecond()
    {
        list($usec, $sec) = explode(" ", microtime());

        return (float)sprintf('%.0f', (floatval($usec) + floatval($sec)) * 1000);
    }

    
}

session_start();
$act = isset($_GET['act']) ? $_GET['act'] : 'getQrCode';

$wx = new loginWX();
switch ($act) {
    case 'getQrCode':
        return $wx->getQrCodeImg();

    case 'status':
        return $wx->checkIsBin();

    case 'public':
        $url = $_GET['url'];
        return $wx->getPublicpartic($url);

    case 'init':
        $params['Uin'] = $_GET['Uin'];
        $params['skey'] = $_GET['skey'];
        $params['Sid'] = $_GET['Sid'];
        $params['pass_ticket'] = $_GET['pass_ticket'];
        $params['DeviceID'] = $_GET['DeviceID'];
        return $wx->wxInit($params);

    case 'list':
        return $wx->getList();

    case 'syncch':
        return $wx->syncCheck();

    case 'sync':
        return $wx->wxsync();

    case 'send':
        $content = $_GET['content'];
        $to = $_GET['fromUser'];
        return $wx->sendMessage($to, $content);

    case 'detail':
        $userName = $_GET['userName'];
        return $wx->getContactDetail($userName);

    default:
        break;
}
