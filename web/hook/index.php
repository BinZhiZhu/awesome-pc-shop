<?php
// 这里只是最简单的拉取代码，如果要做更加多的操作，如验证、日志，请自己解析push内容并操作

// 获取push数据内容的方法
$requestBody = file_get_contents("php://input");

// 只需这一行代码便可拉取
shell_exec("cd /home/wwwroot/com.admin.demo && git pull"); // 目录换成项目的目录

