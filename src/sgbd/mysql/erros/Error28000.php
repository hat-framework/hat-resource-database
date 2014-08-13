<?php

class Error28000 extends DbError{
    protected $default_message = "SqlState 28000";
    public function f1045($msg){
        $msg = explode(" user '", $msg);
        $msg = end($msg);
        return "Acesso negado ao usuário: '$msg";
    }
}

?>
