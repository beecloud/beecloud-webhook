# BeeCloud Webhook开发指南

## 简介

通过BeeCloud SDK以及秒支付Button发起的支付和退款，在如下状态发生时：

1. **支付交易成功**
2. **退款成功**

BeeCloud将向用户在BeeCloud的"控制台->设置->webhook"中指定的url发送状态数据。用户可以根据该状态数据，结合自身系统内记录的订单信息做相应的处理。

>服务器间的交互,不像页面跳转同步通知(REST api中bill的参数return_url指定)可以在页面上显示出来,这种交互方式是不可见的。

## 样例代码
目前BeeCloud提供获取webhook消息的各语言代码样例：  
[PHP DEMO](https://github.com/beecloud/beecloud-php/blob/master/demo/webhook.php)  
[.Net DEMO](https://github.com/beecloud/beecloud-dotnet/blob/master/BeeCloudSDKDemo/notify.aspx.cs)  
[Java DEMO](https://github.com/beecloud/beecloud-java/blob/master/demo/WebRoot/notify_url.jsp)  
[Python DEMO with tornado](https://github.com/beecloud/beecloud-python/blob/master/demo/webhook.py)

>请注意发送的HTTP头部Content-type为application/json,而非大部分框架自动解析的application/x-www-form-urlencoded格式,可能需要自行读取后解析,注意参考各样例代码中的读取写法。

## 应用场景

在BeeCloud获得渠道的确认信息（包括支付成功，退款成功）后，会通过主动推送的方式将确认消息推送给客户的server。如果客户需要接收此类消息来实现业务逻辑，需要:

1. 开通公网可以访问的IP地址和端口
2. 接收BeeCloud Webhook服务器发起的HTTP POST请求报文。如果需要对传输加密，请使用支持HTTPS的webhook的url地址。

>!!!注意：同一条订单可能会发送多条支付成功的webhook消息，这是由渠道触发的(比如渠道的重试)，同一个订单的重复的支付成功的消息**应该被忽略**。退款同理。

## 推送机制

BeeCloud在"收到渠道的确认结果"后的1秒时刻主动向用户server发送消息

## 推送重试机制

用户server接收到某条消息时如果未返回字符串"success", BeeCloud将认为此条消息未能被成功处理, 将触发推送重试机制：

如果用户server一直未能返回字符串"success"，BeeCloud将在"收到渠道的确认结果"后的2秒，4秒，8秒，...，2^17秒（约36小时）时刻重发该条消息；如果在以上的某一时刻，用户server返回了字符串"success"则不再重发该消息 。

>你可以理解为:第n次重发距离第n-1重发的时间间隔为2的n次方,其中第0次"重发"为1秒时刻第一次发送消息；某次重发时刻，用户server返回了"success"则停止重发

>用户需要能够处理BeeCloud notify接口标准的数据

## 处理消息后给BeeCloud返回结果

用户返回"success"字符串给BeeCloud代表-"正确接收并确认了本次状态数据的结果"，其他所有返回都代表需要继续重传本次的状态数据。

## 推送接口标准

```python
HTTP 请求类型 : POST
HTTP 数据格式 : JSON
HTTP Content-type : application/json
```

## 字段说明


  Key             | Type          | Example
-------------     | ------------- | -------------
  sign            | String        | 32位小写
  timestamp       | Long          | 1426817510111
  channelType     | String        | 'WX' or 'ALI' or 'UN' or 'KUAIQIAN' or 'JD'
  transactionType | String        | 'PAY' or 'REFUND'
  transactionId   | String        | '201506101035040000001'
  transactionFee  | Integer       | 1 表示0.01元
  messageDetail   | Map(JSON)     | {orderId:xxxx}
  optional        | Map(JSON)     | {"agentId":"Alice"}

## 参数含义

key  | value
---- | -----
sign | 服务器端通过计算appID + appSecret + timestamp的MD5生成的签名(32字符十六进制),请在接受数据时自行按照此方式验证sign的正确性，不正确不返回success即可
timestamp | 服务端的时间（毫秒），用以验证sign, MD5计算请参考sign的解释
channelType| WX/ALI/UN/KUAIQIAN/JD   分别代表微信/支付宝/银联/块钱/京东
transactionType| PAY/REFUND  分别代表支付和退款的结果确认
transactionId | 交易单号，对应支付请求的bill\_no或者退款请求的refund\_no
transactionFee | 交易金额，是以分为单位的整数，对应支付请求的total\_fee或者退款请求的refund\_fee
messageDetail| {orderId:xxx…..} 用一个map代表处理结果的详细信息，例如支付的订单号，金额， 商品信息
optional| 附加参数，为一个JSON格式的Map，客户在发起购买或者退款操作时添加的附加信息

## messageDetail样例 
1.**支付宝 (ALI):**

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


2.**银联 (UN)：**

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
  queryId        | String        | 2015081216170048 | 银联交易流水号
  traceNo       | String     | 510067 | 银联系统跟踪号
  orderId	|	String 	|	2015081216171028   |    商家内部交易号
  txnTime    | String  | 20150528103823 | 交易创建时间
  txnAmt	|	String	|	1	|	商品总价，单位为分
  signature        |   String  |      |   银联签名串 ，商户可忽略
  respCode   |  String |  00 |   交易返回码 00 代表成功
  respMsg    |  String |  Success! | 交易返回信息  Success!  代表成功
  

3.**微信 (WX)：**

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

4.**快钱 (KUAIQIAN)：**

```
"messageDetail":{
"payResult":"10",
"merchantAcctId":"1001213884201",
"orderId":"d48c05c0f7f04e42ac589af0348ee040",
"dealId":"31030713",
"fee":"",
"language":"1",
"version":"mobile1.0",
"bankDealId":"150921923960",
"bindMobile":"1366926",
"bankId":"BOC",
"payType":"21-1",
"orderTime":"20150921114432",
"orderAmount":"1",
"dealTime":"20150921114600",
"payAmount":"1"
"tradeSuccess":true,
"errCode":"",
"signType":"4",
"bindCard":"6217907388",
"signMsg":"hd1b3n3H/CVxy/oqLKo8q+p2OEOyT"
"ext2":"",
"ext1":"",
}
```

*部分字段含义：*
 
 Key             | 类型           | Example               | 含义
-------------     | ------------- | -------------         | -------
  payResult        | String     | 10 | 支付结果，“10”代码支付成功
  orderId	|	String 	|	d48c05c0f7f04e42ac589af0348ee040   |    商户订单号
  dealId	|	String	|	31030713	|	快钱系统中的交易号
  orderTime     |   String  |    20150921114432  |   快钱订单提交时间，格式yyyymmddhhMMss
  orderAmount   |  String |  10 |   商户订单金额，单位是分
  dealTime    |  String |  20150921114600 |  快钱对交易的处理时间，格式yyyymmddhhMMss
  payAmount    |  String |  10 |  订单实际支付金额
  errCode    |  String |  4 |  错误代码
  
5.**京东 (JD)：**

```
"messageDetail":{
"CURRENCY":"CNY",
"CARDHOLDERNAME":"*俊",
"CARDHOLDERID":"**************8888",
"PAYAMOUNT":"2",
"TIME":"152337",
"DESC":"成功",
"DATE":"20150921",
"STATUS":"0",
"CODE":"0000",
"CARDHOLDERMOBILE":"138****8888",
"BANKCODE":"BOC",
"AMOUNT":"2",
"tradeSuccess":true,
"NOTE":"买矿泉水",
"ID":"159f69842fad43e3b3c2770130a2da75"
"CARDTYPE":"DEBIT_CARD",
"REMARK":"",
"TYPE":"S",
"CARDNO":"621790****8888",

}
```

*部分字段含义：*
 
 Key             | 类型           | Example               | 含义
-------------     | ------------- | -------------         | -------
  PAYAMOUNT        | String     | 2 | 支付金额，单位是分，退款时无此字段
  DATE	|	String 	|	20150921   |    交易时间，格式yyyyMMdd
  TIME	|	String	|	152337	|	交易时间，格式HHmmss
  STATUS     |   String  |    0  |   交易返回码 0：状态成功，3：退款，4：部分退款，6：处理中，7：失败
  CODE   |  String |  0000 |   交易返回码，0000代表交易成功
  AMOUNT    |  String |  2|  交易金额，单位是分，一般和PAYAMOUNT相等，如果京东支付有满减活动，则为用户实际支付金额；退款时为退款金额
  ID    |  String |  159f69842fad43e3b3c2770130a2da75 |  交易号；退款时为退款单号
  OID    |  String |  159f69842fad43e3b3c2770130a2da75 |  原交易号，退款时才有，是指这笔退款在原来支付时候的订单号
  TYPE    |  String |  S |  交易类型编码，S：支付，R：退款 
  

6.**百度 (BD)：**

```
"messageDetail":{
"order_no":"e599fad3d7e149abaa318b74166517d7",
"bfb_order_no":"2015091010001399281110681948040",
"input_charset":"1",
"sign":"094fbaf6e3adea755cc0b06d65be2156",
"sp_no":"1000139928",
"unit_amount":"1",
"bank_no":"",
"transport_amount":"0",
"version":"2",
"bfb_order_create_time":"20150910143407",
"pay_result":"1",
"pay_time":"20150910143407",
"fee_amount":"0",
"buyer_sp_username":"",
"total_amount":"1",
"tradeSuccess":true,
"extra":"",
"sign_method":"1",
"currency":"1",
"pay_type":"2",
"unit_count":"1"}
```

*部分字段含义：*
 
 Key             | 类型           | Example               | 含义
-------------     | ------------- | -------------         | -------
  order_no        | String     | e599fad3d7e149abaa318b74166517d7 | 商户订单号
  bfb_order_no	|	String 	|	2015091010001399281110681948040   |    百度钱包交易号
  sp_no	|	String	|	1000139928	|	百度钱包商户号
  pay_result     |   String  |    1  |   支付结果代码 1：支付成功，2：等待支付，3：退款成功
  total_amount   |  Integer |  1 |   总金额，以分为单位
  pay_time   |  String |  20150910143407 |   支付时间
  pay_type   |  String |  2 |   支付方式,1:余额支付;2:网银支付;3:银行网关支付
   

7.**PayPal (PAYPAL)：**

```
"messageDetail":{
"app_sign":"f0915474a6d3c6ceb55f89c76694d9a4",
"timestamp":1441964975409,
"title":"PayPal payment test",
"total_fee":1,
"optional":{"PayPal key2":"PayPal value2","PayPal key1":"PayPal value1"},
"app_id":"c37d661d-7e61-49ea-96a5-68c34e83db3b",
"bill_no":"8D876079M42176727KXZKHFQ",
"channel":"PAYPAL_SANDBOX",
"access_token":"Bearer A015qoqdOWu7gGpk-1SQxYHrO97rfe18ONMJALm4-m4LGgI",
"currency":"USD",
"tradeSuccess":true}
```

*部分字段含义：*
 
 Key             | 类型           | Example               | 含义
-------------     | ------------- | -------------         | -------
  access_token        | String     | Bearer A015qoqdOWu7gGpk-1SQxYHrO97rfe18ONMJALm4-m4LGgI | PAYPAL访问授权码
  currency	|	String 	|	USD   |    币种
  channel	|	String	|	PAYPAL_SANDBOX	|	贝宝沙箱支付或者真实支付


8.**易宝网银 (YEE_WEB)：**

```
"messageDetail":{
"r0_Cmd":"Buy",
"rb_BankId":"BOCO-NET",
"rp_PayDate":"20150912151013",
"p1_MerId":"10012506312",
"r3_Amt":"0.01",
"r9_BType":"2",
"r7_Uid":"",
"rq_SourceFee":"0.0",
"r5_Pid":"买矿泉水",
"rq_TargetFee":"0.0",
"r4_Cur":"RMB",
"r6_Order":"bfea66381a6149009fba5d35e2f0cfbf",
"r1_Code":"1",
"tradeSuccess":true,
"hmac":"4e37569abca34e4f1462568daaca9da6",
"r2_TrxId":"118262251787405I",
"ru_Trxtime":"20150912151043",
"r8_MP":"c37d661d-7e61-49ea-96a5-68c34e83db3b:dc5582b0-8c54-4bf5-b70b-4694283d2aa7",
"rq_CardNo":"",
"ro_BankOrderId":"2843719202150912"}
```

*部分字段含义：*
 
 Key             | 类型           | Example               | 含义
-------------     | ------------- | -------------         | -------
  r0_Cmd        | String     | Buy | 业务类型,固定值：Buy 
  p1_MerId	|	String 	|	10012506312   |    商户编号 
  r3_Amt	|	String	|	0.01	|	支付金额 
  r2_TrxId	|	String	|	118262251787405I	|	易宝交易流水号 
  r6_Order	|	String	|	bfea66381a6149009fba5d35e2f0cfbf	|	商户订单号 
  r1_Code	|	String	|	1	|	固定值：1 - 代表支付成功 
  r8_MP	|	String	|	测试	|	商户扩展信息
  r9_BType	|	String	|	2	|	通知类型 1 - 浏览器重定向；2 - 服务器点对点通讯
  

8.**易宝一键支付 (YEE_WAP)：**

```
"messageDetail":{
"amount":1,
"bank":"招商银行",
"bankcode":"CMB",
"cardtype":2,
"lastno":"8530",
"merchantaccount":"10000418926",
"orderid":"1f74528ae33d478c8b64d8385357fbe0",
"sign":"KlGQYOJP8fXY7cfximI7xuO8VG9F2wbXy28fijmaFXqvqc3IhskKiStLTlt1rVTpejhqAAA9n9SxVOjgEeRqjjOTJcX4rNexlVsr1eCsN7SPjt2+CA+9elEHBG/oflhbg4RzkmbWUqZ1Hiib2cHqxB1mj+RZtEgW1MqO6QLqpaY=",
"status":1,
"tradeSuccess":true,
"yborderid":"411508290439050771"}
```

*部分字段含义：*
 
 Key             | 类型           | Example               | 含义
-------------     | ------------- | -------------         | -------
  amount        | Integer     | 1 | 订单金额,以「分」为单位
  merchantaccount	|	String 	|	10000418926   |    商户编号 
  orderid	|	String	|	1f74528ae33d478c8b64d8385357fbe0	|	商户订单号 
  yborderid	|	Long	|	411508290439050771	|	易宝交易流水号 
  status	|	Integer	|	1	|	订单状态.1：成功
  bank	|	String	|	招商银行	|	支付卡所属银行的名称 
  cardtype	|	Integer	|	2	|	支付卡的类型，1 为借记卡，2 为信用卡
  lastno	|	String	|	8530	|	支付卡卡号后 4 位
  
9.**易宝点卡支付 (YEE_NOBANKCARD)：**

```
"messageDetail":{
"r0_Cmd":"ChargeCardDirect",
"p6_confirmAmount":"19.9",
"p1_MerId":"10001126856",
"p7_realAmount":"19.9",
"pc_BalanceAct":"0111001507010658538",
"r1_Code":"1",
"p8_cardStatus":"0",
"p5_CardNo":"0111001507010658538",
"p3_Amt":"0.1",
"tradeSuccess":true,
"p2_Order":"201509240000000000010",
"p4_FrpId":"TELECOM",
"hmac":"a571d6c3796dda8f83b5ef717d59000d",
"r2_TrxId":"516222232623803I",
"pb_BalanceAmt":"19.8",
"p9_MP":""}
```

*部分字段含义：*
 
 Key             | 类型           | Example               | 含义
-------------     | ------------- | -------------         | -------
  r1_Code        | String     | 1 | "1"代表支付成功，其他结果代表失败
  p1_MerId	|	String 	|	10001126856   |    商户编号 
  p2_Order	|	String	|	201509240000000000010	|	易宝支付返回商户订单号 
  p3_Amt	|	String	|	0.1	|	成功金额， 单位是元，保留两位小数,不足两位小数的将保留一位!(如0.10 将返回 0.1,0 会返回 0.0)
  p4_FrpId	|	String	|	TELECOM	|	支付方式，“TELECOM”代表电信卡
  p5_CardNo	|	String	|	0111001507010658538	|	点卡卡号
  p6_confirmAmount	|	String	|	19.9|	确认金额
  p7_realAmount	|	String	|	19.9	|	实际金额
  p8_cardStatus	|	String	|	0|	卡状态，0代表销卡成功，订单成功，其他为异常
  pb_BalanceAmt	|	String	|	19.8	|	支付余额
  pc_BalanceAct	|	String	|	0111001507010658538	|	余额卡号

  
## 设置Webhook
在"控制台->应用->设置->xx支付"中

1. 设置并保存Webhook
2. 点击"验证"按钮，验证你的webhook能否正确处理sign签名验证

![webhook-01](http://beeclouddoc.qiniudn.com/webhook-02.png)

## 官方文档地址

BeeCloud Webhook文档的官方GitHub地址是 [https://github.com/beecloud/beecloud-webhook](https://github.com/beecloud/beecloud-webhook)

## 联系我们
- 如果有什么问题，可以到BeeCloud开发者1群:**321545822** 或 BeeCloud开发者2群:**427128840** 提问
- 如果发现了bug，欢迎提交[issue](https://github.com/beecloud/beecloud-webhook/issues)
- 如果有新的需求，欢迎提交[issue](https://github.com/beecloud/beecloud-webhook/issues)

## 代码许可
The MIT License (MIT).
