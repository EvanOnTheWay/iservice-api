## 简述
基于 Swoole 实现的异步任务队列。

## 安装
```
composer install
```

## 使用
启动 
```
php bin/server.php start
```
停止 
```
php bin/server.php stop
```
控制台
```
tail -f -100 storage/swoole.log
```

## 功能

### 微信

#### 网页微信
通过对微信网页版的通信特征进行分析，并参考 [wxpy](https://github.com/youfou/wxpy)，使用 PHP 实现登录和消息等逻辑。

##### Pipeline
```
       +--------------+     +---------------+   +---------------+
       |              |     |               |   |               |
       |   Get UUID   |     |  Get Contact  |   | Status Notify |
       |              |     |               |   |               |
       +-------+------+     +-------^-------+   +-------^-------+
               |                    |                   |
               |                    +-------+  +--------+
               |                            |  |
       +-------v------+               +-----+--+------+      +--------------+
       |              |               |               |      |              |
       |  Get QRCode  |               |  Weixin Init  +------>  Sync Check  <----+
       |              |               |               |      |              |    |
       +-------+------+               +-------^-------+      +-------+------+    |
               |                              |                      |           |
               |                              |                      +-----------+
               |                              |                      |
       +-------v------+               +-------+--------+     +-------v-------+
       |              | Confirm Login |                |     |               |
+------>    Login     +---------------> New Login Page |     |  Weixin Sync  |
|      |              |               |                |     |               |
|      +------+-------+               +----------------+     +---------------+
|             |
|QRCode Scaned|
+-------------+
```