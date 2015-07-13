<?php
/**
 * http类型为 Application/json, 非XMLHttpRequest的application/x-www-form-urlencoded, $_POST方式是不能获取到的
 */
$jsonStr = file_get_contents("php://input");

$webhookObj = json_decode($jsonStr);


// webhook字段文档: http://beecloud.cn/doc/php.php#webhook

var_dump($webhookObj);
