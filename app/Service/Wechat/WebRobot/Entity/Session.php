<?php
/**
 * Created by PhpStorm.
 * @author Wenpeng
 * @email imwwp@outlook.com
 * @time 2019-04-21 19:14:47
 */

namespace App\Service\Wechat\WebRobot\Entity;

use GuzzleHttp\Cookie\CookieJar;

class Session
{
    const STATE_PENDING = 'PENDING';
    const STATE_EXPIRED = 'EXPIRED';
    const STATE_SCANNED = 'SCANNED';
    const STATE_LOGGING = 'LOGGING';
    const STATE_ONLINE = 'ONLINE';
    const STATE_OFFLINE = 'OFFLINE';

    /**
     * 微信特殊联系人
     */
    const SPECIAL_CONTACTS = ['newsapp', 'fmessage', 'filehelper', 'weibo', 'qqmail',
        'fmessage', 'tmessage', 'qmessage', 'qqsync', 'floatbottle',
        'lbsapp', 'shakeapp', 'medianote', 'qqfriend', 'readerapp',
        'blogapp', 'facebookapp', 'masssendapp', 'meishiapp',
        'feedsapp', 'voip', 'blogappweixin', 'weixin', 'brandsessionholder',
        'weixinreminder', 'wxid_novlwrv3lqwv11', 'gh_22b87fa7cb3c',
        'officialaccounts', 'notification_messages', 'wxid_novlwrv3lqwv11',
        'gh_22b87fa7cb3c', 'wxitil', 'userexperience_alarm', 'notification_messages'
    ];

    protected $user;

    /**
     * @var string
     */
    protected $repId;

    /**
     * @var string
     */
    protected $state;

    /**
     * @var array
     */
    protected $contacts = [];

    /**
     * @var string
     */
    protected $username;

    /**
     * @var string
     */
    protected $nickname;

    /**
     * @var CookieJar
     */
    protected $cookieJar;

    /**
     * @var array
     */
    protected $attributes = [
        'uin' => '0',
        'sid' => '',
        'skey' => '',
        'uuid' => '',
        'host' => 'wx.qq.com',
        'sync_key' => [],
        'pass_ticket' => '',
    ];

    public function __construct(string $id)
    {
        $this->repId = $id;
        $this->state = self::STATE_PENDING;
    }

    /**
     * @return string
     */
    public function getRepId(): string
    {
        return $this->repId;
    }

    /**
     * @return string
     */
    public function getState(): string
    {
        return $this->state;
    }

    /**
     * @return string
     */
    public function getStateString(): string
    {
        $messages = [
            static::STATE_PENDING => '二维码等待扫描',
            static::STATE_EXPIRED => '二维码已经过期',
            static::STATE_SCANNED => '二维码已被扫描',
            static::STATE_LOGGING => '会话正在建立中',
            static::STATE_ONLINE => '会话已成功建立',
            static::STATE_OFFLINE => '会话当前已离线',
        ];

        return $messages[$this->state];
    }

    /**
     * @param string $state
     * @return Session
     */
    public function setState(string $state): Session
    {
        $this->state = $state;
        return $this;
    }

    /**
     * @return string
     */
    public function getUsername(): string
    {
        return $this->user['username'];
    }

    /**
     * @param string $username
     */
    public function setUsername(string $username): void
    {
        $this->user['username'] = $username;
    }

    /**
     * @return string
     */
    public function getNickname(): string
    {
        return $this->user['nickname'];
    }

    /**
     * @param string $nickname
     */
    public function setNickname(string $nickname): void
    {
        $this->user['nickname'] = $nickname;
    }

    /**
     * @return array
     */
    public function getContacts(): array
    {
        return $this->contacts;
    }

    /**
     * @param array $contacts
     * @return void
     */
    public function addContacts(array $contacts): void
    {
        $this->contacts = array_merge($this->contacts, $contacts);
    }

    /**
     * @param string $username
     * @return void
     */
    public function delContact(string $username): void
    {
        if (isset($this->contacts[$username])) {
            unset($this->contacts[$username]);
        }
    }

    /**
     * 使用联系人的"备注名"查询"用户名"
     *
     * @param string $remarkName
     * @return string|null
     */
    public function getContactUsername(string $remarkName): ?string
    {
        if (in_array($remarkName, Session::SPECIAL_CONTACTS)) {
            return $remarkName;
        }

        $username = array_search($remarkName, $this->getContacts());

        return $username === false ? null : $username;
    }

    /**
     * @return CookieJar
     */
    public function getCookieJar(): CookieJar
    {
        if (null === $this->cookieJar) {
            $this->cookieJar = new CookieJar();
        }
        return $this->cookieJar;
    }

    /**
     * @return string
     */
    public function getUuid(): string
    {
        return $this->attributes['uuid'];
    }

    /**
     * @param string $uuid
     * @return void
     */
    public function setUuid(string $uuid): void
    {
        $this->attributes['uuid'] = $uuid;
    }

    /**
     * @return string
     */
    public function getHost(): string
    {
        return $this->attributes['host'];
    }

    /**
     * @param string $host
     * @return void
     */
    public function setHost(string $host): void
    {
        $this->attributes['host'] = $host;
    }


    /**
     * @return string
     */
    public function getUin(): string
    {
        return $this->attributes['uin'];
    }

    /**
     * @param string $uin
     * @return void
     */
    public function setUin(string $uin): void
    {
        $this->attributes['uin'] = $uin;
    }


    /**
     * @return string
     */
    public function getSid(): string
    {
        return $this->attributes['sid'];
    }

    /**
     * @param string $sid
     * @return void
     */
    public function setSid(string $sid): void
    {
        $this->attributes['sid'] = $sid;
    }

    /**
     * @return string
     */
    public function getSkey(): string
    {
        return $this->attributes['skey'];
    }

    /**
     * @param string $skey
     * @return void
     */
    public function setSkey(string $skey): void
    {
        $this->attributes['skey'] = $skey;
    }

    /**
     * @return string
     */
    public function getPassTicket(): string
    {
        return $this->attributes['pass_ticket'];
    }

    /**
     * @param string $passTicket
     * @return void
     */
    public function setPassTicket(string $passTicket): void
    {
        $this->attributes['pass_ticket'] = urldecode($passTicket);
    }


    /**
     * @return array
     */
    public function getSyncKey(): array
    {
        return $this->attributes['sync_key'];
    }

    /**
     * @return string
     */
    public function getSyncKeyString(): string
    {
        $tempArr = [];
        $syncKey = $this->getSyncKey();
        foreach ($syncKey['List'] as $item) {
            $tempArr[] = "{$item['Key']}_{$item['Val']}";
        }

        return implode('|', $tempArr);
    }

    /**
     * @param array $syncKey
     * @return void
     */
    public function setSyncKey(array $syncKey): void
    {
        $this->attributes['sync_key'] = $syncKey;
    }
}
