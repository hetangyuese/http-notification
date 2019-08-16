# http-notification
Notification service based on HTTP

基于http的高送达率异步通知服务

## 使用场景

* 支付成功通知服务
* 多业务之间的高送达率类异步通知服务
* ...

> 比如A要将某个消息通知到B，但是又不想在通知的过程中对A的业务执行造成阻塞，那么就可以将通知任务打包给专门的通知服务处理中心，由该中心执行消息通知服务

## 安装

1. 下载程序
2. `composer install`完成依赖安装

## 启动服务
1. 打开config.php配置redis以及通知策略
2. 执行`php worker.php`启动通知服务

## 发布任务
> 内置了一个通过http post的方式提交任务的进程，只需要执行 `php http_api.php` 即可通过post的方式发布通知任务，另外您也可以根据自身情况通过其他方式发布任务，例如直接写redis

***注意，每发布一个任务，最好规定一个唯一标识，参考http_api.php里面的uuid参数，方便做任务追踪分析。也可以加入其它筛选条目方便做数据筛选分析。能很方便的实现统计分析来自不同客户的的任务处理情况***
```
curl post http://127.0.0.1:2345

url:http://xxx.com/path/to?foo=bar  通知地址
payload:somestring... 通知内容
tactic:A 通知策略
time:15968576857 初次发起通知的时间 不填则默认当前时间
```

## 配置项说明

* **fail_interval**：通知失败后再次发起通知的间隔秒数
* **max_times**：最大通知次数，失败次数到达此次数则认为目标失活，会丢弃该条目的通知服务
* **notify_rates**：通知频率，数组第一个表示当前时间点的通知频率，第二个表示当前时间点上一秒的通知频率，以此类推，通常当前时间点就能把任务通知完毕，若任务较多，在当前时间的任务未全部执行，剩下的任务会跌落到上一个时间点，一般3-5个时间点比较合理
* **clear_rate**：若上面的所有时间点都没有将任务执行完毕，还有一个兜底的进程，做清理工作，此处就是兜底进程的执行频率
* **clear_start**：兜底进程的清理时间点，通常是当前时间的前面10分钟基本能满足需求，视具体情况而定，比如系统停止了一个小时，那么兜底时间最好填写系统停止时间之前

***上面的配置项通常需要结合服务器自身硬件配置和带宽大小等等，若一个服务器无法完成通知服务，可以多开几个服务器运行此服务***

## 运维监控
> 系统默认是将通知日志记录在日志文件里面，您可以通过简单的配置（见common.php里面的logger方法，monolog）将日志记录到elastic，redis，mysql...等数十个平台进行运维监控

## 使用条款
无论是商用还是学习，你都可以无偿免费使用本项目，也可修改本项目代码以适应自身业务需要，但禁止未经本人许可用于分发