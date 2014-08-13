<?php
if(!defined('bd_name'))     define("bd_name"    , "groops01");
if(!defined('bd_server'))   define("bd_server"  , ($_SERVER['SERVER_NAME'] == 'localhost')?'localhost':"mysql.".$_SERVER['SERVER_NAME']);
if(!defined('bd_user'))     define("bd_user"    , "root");
if(!defined('bd_password')) define("bd_password", "");
?>