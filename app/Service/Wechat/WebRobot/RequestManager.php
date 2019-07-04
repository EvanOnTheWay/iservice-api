<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-22 11:02:43
 */

namespace App\Service\Wechat\WebRobot;

use App\Service\Wechat\WebRobot\Entity\Response;
use App\Service\Wechat\WebRobot\Entity\Session;
use App\Service\Wechat\WebRobot\Exception\NetWorkException;
use App\Service\Wechat\WebRobot\Exception\ResponseException;
use App\Service\Wechat\WebRobot\Exception\SessionException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Kernel\Foundation\Logger;

class RequestManager
{
    /**
     * @var Session
     */
    protected $session;

    /**
     * RequestManager constructor.
     * @param Session $session
     */
    public function __construct(Session $session)
    {
        $this->session = $session;
    }

    /**
     * 获取登录 UUID
     *
     * UUID存在过期时间，约为300秒
     *
     * @return string
     * @throws ResponseException
     * @throws NetWorkException
     */
    protected function getUuid(): string
    {
        $apiUrl = 'https://login.wx.qq.com/jslogin';
        $params = [
            "appid" => "wx782c26e4c19acffb",
            "redirect_uri" => "https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxnewloginpage",
            "fun" => "new",
            "lang" => "zh_CN",
            "_" => $this->buildMilliseconds()
        ];

        $response = $this->httpGetRequest($apiUrl, $params, [
            'headers' => [
                'Accept' => '*/*'
            ]
        ]);

        // 调用上述接口后会返回:
        // window.QRLogin.code = 200; window.QRLogin.uuid = "gYYsW8GvRw==";
        $pattern = '/window.QRLogin.code = 200; window.QRLogin.uuid = \"(\S+?)\"/';
        preg_match($pattern, $response->getBody(), $matches);

        $uuid = $matches[1] ?? '';
        if ($uuid === '') {
            throw new ResponseException('InvalidLoginUuid', $response->getContext());
        }

        $this->session->setUuid($uuid);

        return $uuid;
    }

    /**
     * 获取登录二维码
     *
     * @return string base64编码后的图片
     * @throws ResponseException
     * @throws NetWorkException
     */
    public function getQrcode(): string
    {
        $uuid = $this->getUuid();
        $url = "https://login.weixin.qq.com/qrcode/{$uuid}";
        $options = [
            'headers' => [
                'Accept' => 'image/webp,image/apng,image/*,*/*;q=0.8',
            ]
        ];
        $response = $this->httpGetRequest($url, [], $options);

        if ($response->getBody() === '') {
            throw new ResponseException('InvalidQrcodeContent', $response->getContext());
        }

        $this->session->setState(Session::STATE_PENDING);

        $data = base64_encode($response->getBody());
        $type = $response->getHeader('Content-Type');

        return "data: {$type};base64,{$data}";
    }

    /**
     * 检查登录进度
     *
     * @throws ResponseException
     * @throws NetWorkException
     * @throws SessionException
     */
    public function checkLogin(): void
    {
        $url = 'https://login.wx.qq.com/cgi-bin/mmwebwx-bin/login';
        $params = [
            "loginicon" => true,
            'uuid' => $this->session->getUuid(),
            'tip' => $this->session->getState() === Session::STATE_PENDING ? 1 : 0,
            '_' => $this->buildMilliseconds()
        ];

        $response = $this->httpGetRequest($url, $params, [
            'timeout' => 30,
            'headers' => [
                'Accept' => '*/*',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'no-cache'
            ]
        ]);

        preg_match('/window.code=(\d+);/', $response->getBody(), $codeMatches);
        $code = isset($codeMatches[1]) ? (int)$codeMatches[1] : -1;

        switch ($code) {
            // 用户尚未扫描二维码
            // window.code=408;
            case 408:
                $this->session->setState(Session::STATE_PENDING);
                break;
            // 登录二维码已经过期(UUID过期，这个时间约 300 秒)
            // window.code=400;
            case 400:
                $this->session->setState(Session::STATE_EXPIRED);
                break;
            // 用户扫瞄登录二维码(window.userAvatar 为用户头像)
            // window.code=201;window.userAvatar = 'data:img/jpg;base64,0cvACaKKoqsYSTECaKKKSH/2Q==';
            case 201:
                $this->session->setState(Session::STATE_SCANNED);
                break;
            // 用户已经确认了登录(访问 redirect_uri 可获取 pass_ticket)
            // window.code=200;window.redirect_uri="https://wx2.qq.com/cgi-bin/mmwebwx-bin/webwxnewloginpage?ticket=A76-TgOt9e87mtW8G5VohTFo@qrticket_0&uuid=oY_LGOqlHw==&lang=zh_CN&scan=1555669287";
            case 200:
                preg_match('/window.redirect_uri="(\S+?)";/', $response->getBody(), $redirectUriMatches);

                $redirectUri = isset($redirectUriMatches[1]) ? (string)$redirectUriMatches[1] : '';
                if ($redirectUri === '') {
                    throw new ResponseException('InvalidLoginRedirectUri', $response->getContext());
                }

                $host = parse_url($redirectUri, PHP_URL_HOST);

                $this->session->setHost($host);
                $this->session->setState(Session::STATE_LOGGING);

                // 接下来请求 PassTicket
                $this->getTicket($redirectUri);

                $this->session->setState(Session::STATE_ONLINE);

                // 接下来进行"初始化客户端"
                $this->initClient();

                // 接下来进行"读取联系人"
                $this->getContacts();

                break;
            default:
                throw new ResponseException('检查登录进度:状态码未定义', $response->getContext());
        }
    }

    /**
     * 请求登录页
     *
     * @param string $redirectUri
     * @throws ResponseException
     * @throws NetWorkException
     * @throws SessionException
     */
    protected function getTicket(string $redirectUri)
    {
        $response = $this->httpGetRequest($redirectUri, [], [
            'headers' => [
                'Accept' => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'no-cache',
                'Upgrade-Insecure-Requests' => 1,
            ]
        ]);

        $respData = $this->parseXmlResponse($response);

        $retCode = (int)$respData['ret'];
        if ($retCode !== 0) {
            throw new SessionException('请求PassTicket', $retCode, $response->getContext());
        }

        $this->session->setUin($respData['wxuin']);
        $this->session->setSid($respData['wxsid']);
        $this->session->setSKey($respData['skey']);
        $this->session->setPassTicket($respData['pass_ticket']);
    }

    /**
     * 初始化客户端
     *
     * POST https://wx.qq.com/cgi-bin/mmwebwx-bin/webwxinit?r=xxx&pass_ticket=xxx
     *
     * @throws ResponseException
     * @throws NetWorkException
     * @throws SessionException
     */
    protected function initClient()
    {
        $url = $this->makeApiUrl('base', '/webwxinit', [
            'r' => time()
        ]);

        $response = $this->httpPostRequest($url, [
            'BaseRequest' => [
                'Uin' => $this->session->getUin(),
                'Sid' => $this->session->getSid(),
                'Skey' => $this->session->getSKey(),
                'DeviceID' => $this->buildDeviceId(),
            ]
        ], [
            'headers' => [
                'Accept' => 'application/json, text/plain, */*',
                'Origin' => 'https://' . $this->session->getHost(),
                'Pragma' => 'no-cache',
                'Cache-Control' => 'no-cache',
            ]
        ]);

        $respData = $this->parseJsonResponse($response);

        $retCode = (int)$respData['BaseResponse']['Ret'];
        if ($retCode !== 0) {
            throw new SessionException('初始化客户端', $retCode, $response->getContext());
        }

        $this->session->setSyncKey($respData['SyncKey']);
        $this->session->setUsername($respData['User']['UserName']);
        $this->session->setNickname($respData['User']['NickName']);
    }

    /**
     * 读取联系人
     *
     * @param int $page
     * @throws ResponseException
     * @throws NetWorkException
     * @throws SessionException
     */
    protected function getContacts($page = 0)
    {
        $url = $this->makeApiUrl('base', '/webwxgetcontact');
        $response = $this->httpGetRequest($url, [
            'pass_ticket' => $this->session->getPassTicket(),
            'skey' => $this->session->getSKey(),
            'r' => $this->buildMilliseconds(),
            'seq' => $page,
            'lang' => 'zh_CN'
        ], [
            'headers' => [
                'Accept' => 'application/json, text/plain, */*',
                'Origin' => 'https://' . $this->session->getHost(),
                'Pragma' => 'no-cache',
                'Cache-Control' => 'no-cache',
            ]
        ]);

        $respData = $this->parseJsonResponse($response);

        $retCode = (int)$respData['BaseResponse']['Ret'];
        if ($retCode !== 0) {
            throw new SessionException('同步联系人', $retCode, $response->getContext());
        }

        $contacts = [];
        foreach ($respData['MemberList'] as $member) {
            // 微信特殊联系人
            if (in_array($member['UserName'], Session::SPECIAL_CONTACTS)) {
                continue;
            }
            // 微信公众号
            if (($member['VerifyFlag'] & 8) !== 0) {
                continue;
            }
            // 微信群
            if (strstr($member['UserName'], '@@') !== false) {
                continue;
            }
            // 微信联系人
            if ($member['RemarkName'] !== '') {
                $contacts[$member['UserName']] = $member['RemarkName'];
            }
        }

        $this->session->addContacts($contacts);

        // 是否存在下一页
        $seq = (int)$respData['Seq'];
        if ($seq !== 0) {
            $this->getContacts($seq);
        }
    }

    /**
     * 检查同步
     * @throws ResponseException
     * @throws NetWorkException
     * @throws SessionException
     */
    public function checkSync()
    {
        $url = $this->makeApiUrl('push', '/synccheck');

        $response = $this->httpGetRequest($url, [
            'r' => $this->buildMilliseconds(),
            'sid' => $this->session->getSid(),
            'uin' => $this->session->getUin(),
            'skey' => $this->session->getSkey(),
            'synckey' => $this->session->getSyncKeyString(),
            'deviceid' => $this->buildDeviceId(),
            '_' => time(),
        ], [
            'headers' => [
                'Accept' => '*/*',
                'Pragma' => 'no-cache',
                'Cache-Control' => 'no-cache',
            ]
        ]);

        // 调用上述接口后会返回:
        // window.synccheck={retcode:"0",selector:"2"}
        $pattern = '/window\.synccheck=\{retcode:"(\d+)",selector:"(\d+)"\}/';
        preg_match($pattern, $response->getBody(), $matches);

        // 检查响应中是否匹配出 retcode 和 selector
        if (!isset($matches[1]) || !isset($matches[2])) {
            throw new ResponseException('RetcodeOrSelectorParseFailed', $response->getContext());
        }

        $retCode = (int)$matches[1];
        $selector = (int)$matches[2];

        if ($retCode !== 0) {
            // 记录下 retcode 和 selector 供日后分析
            Logger::info('检查数据同步:状态码记录', [
                'retcode' => $retCode,
                'selector' => $selector
            ]);

            throw new SessionException('检查数据同步', $retCode, $response->getContext());
        }

        if ($selector !== 0) {
            $this->performSync();
        }
    }

    /**
     * 执行数据同步
     *
     * @throws ResponseException
     * @throws NetWorkException
     * @throws SessionException
     */
    protected function performSync()
    {
        $postUrl = $this->makeApiUrl('base', '/webwxsync', [
            'sid' => $this->session->getSid(),
            'skey' => $this->session->getSkey(),
            'lang' => 'zh_CN',
            'pass_ticket' => $this->session->getPassTicket()
        ]);
        $postData = [
            'BaseRequest' => [
                'Uin' => $this->session->getUin(),
                'Sid' => $this->session->getSid(),
                'Skey' => $this->session->getSKey(),
                'DeviceID' => $this->buildDeviceId(),
            ],
            'SyncKey' => $this->session->getSyncKey(),
            'rr' => ~time()
        ];

        $response = $this->httpPostRequest($postUrl, $postData, [
            'headers' => [
                'Accept' => 'application/json, text/plain, */*',
                'Origin' => 'https://' . $this->session->getHost(),
                'Pragma' => 'no-cache',
                'Cache-Control' => 'no-cache',
            ]
        ]);

        $respData = $this->parseJsonResponse($response);

        $retCode = (int)$respData['BaseResponse']['Ret'];
        if ($retCode !== 0) {
            throw new SessionException('执行数据同步', $retCode, $response->getContext());
        }

        // 上次同步后有联系人被修改，需要更新通讯录
        if (!empty($respData['ModContactList'])) {
            $contacts = [];
            foreach ($respData['ModContactList'] as $modContact) {
                if ($modContact['RemarkName'] !== '') {
                    $contacts[$modContact['UserName']] = $modContact['RemarkName'];
                }
            }
            if (!empty($contacts)) {
                $this->session->addContacts($contacts);
            }
        }

        // 上次同步后有联系人被删除，需要更新通讯录
        if (!empty($respData['DelContactList'])) {
            foreach ($respData['DelContactList'] as $modContact) {
                if ($modContact['RemarkName'] !== '') {
                    $this->session->delContact($modContact['UserName']);
                }
            }
        }

        $this->session->setSyncKey($respData['SyncKey']);
    }

    /**
     *  发送文本消息
     *
     * @param string $toUserName
     * @param string $content
     * @return string
     *
     * @throws ResponseException
     * @throws NetWorkException
     * @throws SessionException
     */
    public function sendText(string $toUserName, string $content): string
    {
        $localId = $this->buildMessageId();
        $postUrl = $this->makeApiUrl('base', '/webwxsendmsg', [
            'lang' => 'zh_CN',
            'pass_ticket' => $this->session->getPassTicket()
        ]);
        $postData = [
            'BaseRequest' => [
                'Uin' => $this->session->getUin(),
                'Sid' => $this->session->getSid(),
                'Skey' => $this->session->getSKey(),
                'DeviceID' => $this->buildDeviceId(),
            ],
            'Msg' => [
                'ClientMsgId' => $localId,
                'Content' => $content,
                'FromUserName' => $this->session->getUserName(),
                'ToUserName' => $toUserName,
                'LocalID' => $localId,
                'Type' => 1,
            ],
            'Scene' => 0
        ];

        $response = $this->httpPostRequest($postUrl, $postData);

        $respData = $this->parseJsonResponse($response);

        $retCode = (int)$respData['BaseResponse']['Ret'];
        if ($retCode !== 0) {
            throw new SessionException('发送文本消息', $retCode, $response->getContext());
        }

        return $respData['MsgID'];
    }

    /**
     * 执行 HTTP GET 请求
     *
     * @param string $url
     * @param array $params
     * @param array $options
     * @return Response
     * @throws NetWorkException
     */
    protected function httpGetRequest(string $url, $params = [], $options = [])
    {
        if (!empty($params)) {
            $options['query'] = $params;
        }
        return $this->execHttpRequest($url, 'GET', $options);
    }

    /**
     * 执行 HTTP POST 请求
     *
     * @param string $url
     * @param array $data
     * @param array $options
     * @return Response
     * @throws NetWorkException
     */
    protected function httpPostRequest(string $url, array $data = [], $options = [])
    {
        if (!empty($data)) {
            $options['body'] = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }
        return $this->execHttpRequest($url, 'POST', $options);
    }

    /**
     * 执行 HTTP 请求
     *
     * @param string $url
     * @param string $method
     * @param array $options
     * @return Response
     * @throws NetWorkException
     */
    protected function execHttpRequest(string $url, string $method = 'GET', array $options = [])
    {
        try {
            $cookies = $this->session->getCookieJar();
            $options = array_replace_recursive([
                'timeout' => 60,
                'cookies' => $this->session->getCookieJar(),
                'headers' => [
                    'Connection' => 'keep-alive',
                    'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_14_4) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/73.0.3683.103 Safari/537.36',
                    'Accept-Encoding' => 'gzip, deflate, br',
                    'Accept-Language' => 'zh-CN,zh;q=0.9,en;q=0.8',
                    'Host' => parse_url($url, PHP_URL_HOST),
                    'Referer' => 'https://' . $this->session->getHost() . '/',
                ],
                'force_ip_resolve' => 'v4',
                'allow_redirects' => false,
                'verify' => false,
            ], $options);

            $client = new Client($options);
            $request = [
                'url' => $url,
                'body' => $options['body'] ?? '',
                'query' => $options['query'] ?? '',
                'headers' => $options['headers'],
                'cookies' => $cookies->toArray(),
            ];
            $response = $client->request($method, $url);

            return new Response($response, $request);
        } catch (GuzzleException $exception) {
            throw new NetWorkException('网络请求出错', func_get_args(), $exception);
        }
    }

    /**
     * 拼接 API 接口地址
     *
     * @param string $hostAlias
     * @param string $uri
     * @param array $queries
     * @return string
     */
    protected function makeApiUrl(string $hostAlias, string $uri, array $queries = [])
    {
        $host = $this->session->getHost();
        $pattern = 'https://%s/cgi-bin/mmwebwx-bin';
        $aliases = [
            'base' => sprintf($pattern, $host),
            'file' => sprintf($pattern, "file.{$host}"),
            'push' => sprintf($pattern, "webpush.{$host}")
        ];

        if (empty($queries) === false) {
            $uri .= '?' . http_build_query($queries);
        }

        return $aliases[$hostAlias] . '/' . ltrim($uri, '/');
    }

    /**
     * 生成设备号
     *
     * @return string
     */
    protected function buildDeviceId(): string
    {
        $result = '';
        $source = "0123456789";
        for ($i = 0; $i < 15; $i++) {
            $random = mt_rand(0, strlen($source) - 1);
            if (isset($source{$random})) {
                $result .= $source{$random};
            }
        }

        return 'e' . $result;
    }

    /**
     * 生成消息编号
     *
     * @return string
     */
    protected function buildMessageId(): string
    {
        return (string)((int)(microtime(true) * 10000000) + rand(1, 9999));
    }

    /**
     * 生成毫秒时间戳
     *
     * @return int
     */
    protected function buildMilliseconds()
    {
        return (int)(microtime(true) * 1000);
    }

    /**
     * 解析 XML 格式的响应内容
     *
     * @param Response $response
     * @return array
     * @throws ResponseException
     */
    protected function parseXmlResponse(Response $response): array
    {
        libxml_use_internal_errors(true);
        libxml_disable_entity_loader(true);
        $responseXml = simplexml_load_string($response->getBody(), 'SimpleXMLElement', LIBXML_NOCDATA);
        $tempJsonObj = json_encode($responseXml, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        $responseArr = json_decode($tempJsonObj, JSON_OBJECT_AS_ARRAY);

        if (!isset($responseArr['ret'])) {
            throw new ResponseException('InvalidXmlResponse', $response->getContext());
        }

        return (array)$responseArr;
    }

    /**
     * 解析 JSON 格式的响应内容
     *
     * @param Response $response
     * @return array
     * @throws ResponseException
     */
    protected function parseJsonResponse(Response $response): array
    {
        $array = $response->toJsonArray();
        if (isset($array['BaseResponse']['Ret']) === false) {
            throw new ResponseException('InvalidJsonResponse', $response->getContext());
        }

        return $array;
    }
}
