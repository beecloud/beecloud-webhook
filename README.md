## BeeCloud Webhook开发指南

## 简介

Webhook是BeeCloud获得渠道的确认信息后，立刻向客户服务器发送的异步回调。支付，代付，退款成功时，BeeCloud将向用户指定的URL发送HTTP/HTTPS的POST数据请求。

如果客户需要接收此类消息来实现业务逻辑，需要:

1. 开通公网可以访问的IP地址(或域名）和端口（如果需要对传输加密，请使用支持HTTPS的URL地址，BeeCloud不要求HTTPS根证书认证）
2. 在 **控制台->App设置->Webhook** 中设置接收端URL，不同应用可设置不同URL，同一应用能且仅能设置一个测试URL，一个生产URL
2. 处理POST请求报文，实现业务逻辑

>服务器间的交互，不像页面跳转同步通知（REST API中bill的参数return_url指定）可以在页面上显示出来，这种交互方式是通过后台通信来完成的，对用户是不可见的。

## 推送机制

BeeCloud在收到渠道的确认结果后立刻发送Webhook，Webhook只会从如下IP地址发送：

- 123.57.146.46
- 182.92.114.175

客户服务器接收到某条Webhook消息时如果未返回字符串 **success**, BeeCloud将认为此条消息未能被成功处理, 将触发推送重试机制：

BeeCloud将在2秒，4秒，8秒，...，2^17秒（约36小时）时刻重发；如果在以上任一时刻，BeeCloud收到了 **success**，重试将终止。

## 处理Webhook消息

请参考各开发语言的Webhook demo学习如何处理Webhook消息。

### 第一步：验证数字签名

目的在于验证Webhook是由BeeCloud发起的，防止黑客向此Webhook接口发送伪造的订单信息。验证签名需要验证传入的 **sign** 是否与 **App ID + App Secret + timestamp** 的 MD5 生成的签名 (32字符十六进制) 是否相等，**App ID** 与 **App Secret** 存储在客户服务器上，**timestamp** 是Webhook传入的。

### 第二步：过滤重复的Webhook

同一条订单可能会发送多条支付成功的webhook消息，这有可能是由支付渠道本身触发的(比如渠道的重试)，也有可能是BeeCloud的Webhook重试。客户需要根据订单号进行判重，忽略已经处理过的订单号对应的Webhook。


### 第三步：验证订单金额

客户需要验证Webhook中的 **transaction_fee** （实际的交易金额）与客户内部系统中的相应订单的金额匹配。

这个验证的目的在于防止黑客反编译了iOS或者Android app的代码，将本来比如100元的订单金额改成了1分钱，应该识别这种情况，避免误以为用户已经足额支付。Webhook传入的消息里面应该以某种形式包含此次购买的商品信息，比如title或者optional里面的某个参数说明此次购买的产品是一部iPhone手机，或者直接根据订单号查询，客户需要在内部数据库里去查询iPhone的金额是否与该Webhook的订单金额一致，仅有一致的情况下，才继续走正常的业务逻辑。如果发现不一致的情况，排除程序bug外，需要去查明原因，防止不法分子对你的app进行二次打包，对你的客户的利益构成潜在威胁。而且即使有这样极端的情况发生，只要按照前述要求做了购买的产品与订单金额的匹配性验证，客户也不会有任何经济损失。

### 第四步：处理业务逻辑和返回

这里就可以完成业务逻辑的处理。最后返回结果。用户返回 **success** 字符串给BeeCloud表示 - **正确接收并处理了本次Webhook**，其他所有返回都代表需要继续重传本次的Webhook请求。

## Webhook接口标准

```python
HTTP 请求类型 : POST
HTTP 数据格式 : JSON
HTTP Content-type : application/json
```

### 字段说明


  Key             | Type          | Example
-------------     | ------------- | -------------
  sign            | String        | 32位小写
  timestamp       | Long          | 1426817510111
  channel_type     | String        | 'WX' or 'ALI' or 'UN' or 'KUAIQIAN' or 'JD' or 'BD' or 'YEE' or 'PAYPAL'
  sub_channel_type | String        | 'WX_APP' or 'WX_NATIVE' or 'WX_JSAPI' or 'WX_SCAN' or 'ALI_APP' or 'ALI_SCAN' or 'ALI_WEB' or 'ALI_QRCODE' or 'ALI_OFFLINE_QRCODE' or 'ALI_WAP' or 'UN_APP' or 'UN_WEB' or 'PAYPAL_SANDBOX' or 'PAYPAL_LIVE' or 'JD_WAP' or 'JD_WEB' or 'YEE_WAP' or 'YEE_WEB' or 'YEE_NOBANKCARD' or 'KUAIQIAN_WAP' or 'KUAIQIAN_WEB' or 'BD_APP' or 'BD_WEB' or 'BD_WAP'	
  transaction_type | String        | 'PAY' or 'REFUND' or 'TRANSFER'
  transaction_id   | String        | '201506101035040000001'
  transaction_fee  | Integer       | 1 表示0.01元 (当transaction_type为TRANSFER时无此字段)
  trade_success  | Bool       | true
  message_detail   | Map(JSON)     | {orderId:xxxx}
  optional        | Map(JSON)     | {"agent_id":"Alice"}

### 参数含义

key  | value
---- | -----
sign | 服务器端通过计算 **App ID + App Secret + timestamp** 的MD5生成的签名(32字符十六进制),请在接受数据时自行按照此方式验证sign的正确性，不正确不返回success即可
timestamp | 服务端的时间（毫秒），用以验证sign, MD5计算请参考sign的解释
channel_type| WX/ALI/UN/KUAIQIAN/JD/BD/YEE/PAYPAL   分别代表微信/支付宝/银联/快钱/京东/百度/易宝/PAYPAL
sub_channel\_type|  代表以上各个渠道的子渠道，参看字段说明
transaction_type| PAY/REFUND  分别代表支付和退款的结果确认
transaction_id | 交易单号，对应支付请求的bill\_no或者退款请求的refund\_no,对于秒支付button为传入的out\_trade\_no
transaction_fee | 交易金额，是以分为单位的整数，对应支付请求的total\_fee或者退款请求的refund\_fee
trade_success | 交易是否成功，目前收到的消息都是交易成功的消息
message_detail| {orderId:xxx…..} 从支付渠道方获得的详细结果信息，例如支付的订单号，金额， 商品信息等，详见附录
optional| 附加参数，为一个JSON格式的Map，客户在发起购买或者退款操作时添加的附加信息
  
## 样例代码
目前BeeCloud提供获取webhook消息的各语言代码样例：  
[PHP DEMO](https://github.com/beecloud/beecloud-php/blob/master/demo/webhook.php)  
[.Net DEMO](https://github.com/beecloud/beecloud-dotnet/blob/master/BeeCloudSDKDemo/notify.aspx.cs)  
[Java DEMO](https://github.com/beecloud/beecloud-java/blob/master/demo/WebRoot/webhook_receiver_example/webhook_receiver.jsp)  
[Python DEMO](https://github.com/beecloud/beecloud-python/blob/master/demo/webhook.py)

>请注意发送的HTTP头部Content-type为application/json,而非大部分框架自动解析的application/x-www-form-urlencoded格式,可能需要自行读取后解析,注意参考各样例代码中的读取写法。


## 意见反馈

[https://github.com/beecloud/beecloud-webhook/issues](https://github.com/beecloud/beecloud-webhook/issues)

## 附录 - message_detail 样例 

- **支付宝 (ALI)**

```
"message_detail":{
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


- **银联 (UN)**

```
"message_detail":{
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
  

- **微信 (WX)**

```
"message_detail":{
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

- **快钱 (KUAIQIAN)**

```
"message_detail":{
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
  
- **京东 (JD)**

```
"message_detail":{
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
  

- **百度 (BD)**

```
"message_detail":{
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
   

- **PayPal (PAYPAL)**

```
"message_detail":{
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
  channel	|	String	|	PAYPAL_SANDBOX	|	PAYPAL沙箱支付或者真实支付


- **易宝网银 (YEE_WEB)**

```
"message_detail":{
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
  

- **易宝一键支付 (YEE_WAP)**

```
"message_detail":{
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
  
- **易宝点卡支付 (YEE_NOBANKCARD)**

```
"message_detail":{
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
  
- **BC代付**

```
"message_detail":{
"trade_pay_date":"Wed Jan 13 16:27:04 CST 2016",
"merchant_no":"110119759002",
"bank_code":"BOC",
"return_params":"beecloud",
"notify_datetime":"20162713T162401444",
"pay_tool":"TRAN",
"trade_currency":"CNY",
"category_code":"beecloud",
"buyer_info":{},
"is_success":"Y",
"card_type":"DE",
"trade_class":"DEFY",
"biz_trade_no":"bfe1dea75cae4bdcb52c406add16d398",
"out_trade_no":"bfe1dea75cae4bdcb52c406add16d398",
"trade_amount":"1",
"tradeSuccess":true,
"trade_finish_time":"Wed Jan 13 16:27:04 CST 2016",
"trade_status":"FINI",
"trade_pay_time":"Wed Jan 13 16:27:04 CST 2016",
"trade_no":"20160113100042000010570232",
"trade_subject":"测试代付",
"trade_finish_date":"Wed Jan 13 16:27:04 CST 2016"}
```  
  
  *部分字段含义：*
 
 Key             | 类型           | Example               | 含义
-------------     | ------------- | -------------         | -------
  bank_code        | String     | BOC | 中国银行
  card_type	|	String 	|	DE   |    借记卡=DE;信用卡=CR
  out\_trade\_no	|	String	|	201509240000000000010|商户订单号 
  trade_amount	|	String	|	1	|	交易金额
  trade_status	|	String	|	FINI	|	FINI=交易成功;REFU=交易退款;CLOS=交易关闭，失败
  trade_no	|	String	|	BC代付内部交易	| 20160113100042000010570232
  trade_subject	|	String	|	标题|	测试代付