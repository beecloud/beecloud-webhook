# BeeCloud Webhook开发指南

## 应用场景

在BeeCloud获得渠道的确认信息（包括支付结果，退款结果）后，会通过主动推送的方式将确认信息推送给客户的server。如果客户需要接收此类信息来实现业务逻辑，需要开通公网可以访问的IP地址和端口，按以下格式接受BeeCloud Webhook服务器发起的POST请求。支持HTTP或者HTTPS，如果需要对传输内容加密，请开通HTTPS的接口接收Webhook回调请求。

>注意：同一条订单可能会发送多条webhook消息，例如前几条的状态是在等待用户支付，最后一条支付成功， 也有可能同一条订单收到多条成功的消息，建议仅对同一个订单的第一条支付成功的消息做处理，同一个订单的重复的支付成功的消息应该被忽略（可能由于渠道推送重试导致）。退款同理。

## 推送机制

用户提供符合BeeCloud notify接口标准的API，BeeCloud在收到渠道的确认结果后1秒，2秒，4秒，8秒，...，2^17秒（约36小时）主动通知客户server，直到客户server返回正确处理该通知的信息为止。

## 推送接口标准

```python
HTTP请求类型 : POST
数据格式 : JSON
```

## 字段说明


  Key             | Type          | Example
-------------     | ------------- | -------------
  sign            | String        | 32位小写
  timestamp       | Long          | 1426817510111
  channelType     | String        | 'WX' or 'ALI' or 'UN'
  transactionType | String        | 'PAY' or 'REFUND'
  transactionId   | String        | '201506101035040000001'
  transactionFee  | Integer       | 1 表示0.01元
  tradeSuccess    | Boolean       | true/false
  messageDetail   | Map(JSON)     | {orderId:xxxx}
  optional        | Map(JSON)     | {"agentId":"Alice"}


## 参数含义

key  | value
---- | -----
sign | 服务器端通过计算appID + appSecret + timestamp的MD5生成的签名(32字符十六进制),请在接受数据时自行按照此方式验证sign的正确性，不正确请返回fail
timestamp | 服务端的时间（毫秒），用以验证sign, MD5计算请参考sign的解释
channelType| WX/ALI/UN   分别代表微信/支付宝/银联
transactionType| PAY/REFUND  分别代表支付和退款的结果确认
transactionId | 交易单号，对应支付请求的bill\_no或者退款请求的refund\_no
transactionFee | 交易金额，是以分为单位的整数，对应支付请求的total\_fee或者退款请求的refund\_fee
tradeSuccess | 交易类型的时候会有， 此参数为true代表支付成功， 当此参数为false时不代表支付失败，可能后续会发送为true的消息，建议用户拿此参数为true做业务开关
messageDetail| {orderId:xxx…..} 用一个map代表处理结果的详细信息，例如支付的订单号，金额， 商品信息
optional| 附加参数，为一个JSON格式的Map，客户在发起购买或者退款操作时添加的附加信息

## messageDetail样例 
1.**支付宝:**

```
"messageDetail":{
"bc_appid":"test”,
"discount":"0.00",
"payment_type":"1",
"subject":"测试",
"trade_no":"2015052300001000620053541865",
"buyer_email":"13909965298",
"gmt_create":"2015-05-23 22:26:20",
"notify_type":"trade_status_sync",
"quantity":"1",
"out_trade_no":"test_no",
"seller_id":"2088911356553910",
"notify_time":"2015-05-23 22:26:20",
"body":"测试",
"trade_status":"WAIT_BUYER_PAY",
"is_total_fee_adjust":"Y",
"total_fee":"0.10",
"seller_email":"test@test.com",
"price":"0.10",
"buyer_id":"2088802823343621",
"notify_id":"e79d45a7c43180db6041da31deb51bdf5g",
"use_coupon":"N",
"sign_type":"RSA",
"sign":"eOwNVySlvFDENsww4zWmo4iSv5XUG+O6T9jma1szEe15DlSFdMl8eJfwUzu37V77Tws+gfcKjvWSOX5mIS82vZU2Ga2u19COFFM20Zp0YEJwxw5zllCIAhd+A7KXX1EbcXid5Q/bVi/XUVffy9sd0HL39Ak53lyduzYQ/MmiwWY="
}
```

*部分字段含义*

  Key             | 类型           | Example               | 含义
-------------     | ------------- | -------------         | -------
    trade_status | String | WAIT_BUYER_PAY | 交易状态
     trade_no        | String        | 2014040311001004370000361525|支付宝交易号
  out\_trade_no	|	String 	|	test_no   |    商家内部交易号
     buyer_email   |  String | 13758698870 | 买家支付宝账号可以是email或者手机号
  total_fee	|	String	|	0.01	|	商品总价，单位为元
    price	|	String	|	0.01	|	商品单价，单位为元
  subject         | String        | 白开水                 |  订单标题
  discount        | String        | 0                     | 折扣     
  gmt_create    | String  | 2008-10-22 20:49:31 | 交易创建时间
  notify_type   | String | trade\_status_sync | 通知类型
  quantity	| 	String	|	1	|	购买数量
  seller_id		| String	|	2088911356553910  | 卖家支付宝唯一用户号
  buyer_id	|	String	|	2088802823343621 |  买家支付宝唯一用户号
  use_coupon  |	String  |  N	|  买家是否使用了红包  （N/Y)


2.**银联：**

```
"messageDetail":{
"bizType":"000201",
"orderId":"aa0c27e47b9e4ea1a595118ee0acf79f",
"txnSubType":"01",
"signature":"FPD/1uJ1HL9hRax8gR5S29rXYa9d9+U03qHgN4vNMJPWdK2NC9c0TIcfUVYYCKfphNiuzxXQUUWG3iLHe37QAdl2IDGbz76u3jQ5xZvXJBZ7d7CTfCBn5iBQuu8G4bFJOQMZYyQfPYz7joMSjdJl0/Nu7Lu1/m2xOxDIL90PhD5TrxheAWrCUfaN3Uw10dKXIwSiKVdu9wp32D9M1l6Wkhrso0jWbaKY4HNsa+jjzTAMpIvpROjRQZukdMM8NaI2uzXyBOewbSKY7/hexSW2tuXVnOUuiyPUDsk44RciZsaaDZkkql0HyB/hJCVsochYqzo6k9j0UYb8Xdj6e3UtXA==",
"traceNo":"510016",
"settleAmt":"1",
"settleCurrencyCode":"156",
"settleDate":"0528",
"txnType":"01",
"certId":"21267647932558653966460913033289351200",
"encoding":"UTF-8",
"version":"5.0.0",
"queryId":"201505281038235100168",
"accessType":"0",
"respMsg":"Success!",
"traceTime":"0528103823",
"txnTime":"20150528103823",
"merId":"898320548160202",
"currencyCode":"156",
"respCode":"00",
"signMethod":"01",
"txnAmt":"1"
}
```
 *部分字段含义：*
 
 Key             | 类型           | Example               | 含义
-------------     | ------------- | -------------         | -------
  traceNo        | String        | 510016 | 银联交易号
  orderId	|	String 	|	test_no   |    商家内部交易号
  txnTime    | String  | 20150528103823 | 交易创建时间
  txnAmt	|	String	|	1	|	商品总价，单位为分
  signature        |   String  |      |   银联签名串 ，商户可忽略
  respCode   |  String |  00 |   交易返回码 00 代表成功
  respMsg    |  String |  Success! | 交易返回信息  Success!  代表成功
  

3.**微信：**

```
"messageDetail":{
"transaction_id":"1006410636201505250163820565",
"nonce_str":"441956259efc417291d904f90f76fd69",
"bank_type":"CMB_CREDIT",
"openid":"oPSDwtwFnD54dB_ggPFGBO_KMdpo",
"fee_type":"CNY",
"mch_id":"xxx",
"cash_fee":"1",
"out_trade_no":"4095601432530130",
"appid":"xxx",
"total_fee":"1",
"trade_type":"JSAPI",
"result_code":"SUCCESS",
"time_end":"20150525130218",
"is_subscribe":"Y",
"return_code":"SUCCESS"
}
```

*部分字段含义：*
 
 Key             | 类型           | Example               | 含义
-------------     | ------------- | -------------         | -------
  transaction_id        | String     | 1006410636201505250163820565 | 微信交易号
  time_end    | String  | 20150528103823 | 交易结束时间
  out\_trade_no	|	String 	|	test_no   |    商家内部交易号
  total_fee	|	String	|	1	|	商品总价，单位为分
  cash_fee	|	String	|	1	|	现金付款额
  openid        |   String  |      |   买家的openid
  return_code   |  String |  SUCCESS |   通信标示
  result_code    |  String |  SUCCESS |  业务结果

## 返回结果

返回"success"字符串代表正确接收并确认了结果，其他所有返回都代表需要继续重传。

## 设置Webhook
在"控制台->应用->设置->xx支付"中

1. 设置并保存Webhook
2. 点击"验证"按钮，验证你的webhook能否正确处理sign签名验证

![webhook-01](http://beeclouddoc.qiniudn.com/webhook-02.png)


## 联系我们
- 如果有什么问题，可以到QQ群 **321545822** 或 **427128840** BeeCloud开发者大联盟 提问
- 如果发现了bug，欢迎提交[issue](https://github.com/beecloud/beecloud-webhook/issues)
- 如果有新的需求，欢迎提交[issue](https://github.com/beecloud/beecloud-webhook/issues)

## 代码许可
The MIT License (MIT).
