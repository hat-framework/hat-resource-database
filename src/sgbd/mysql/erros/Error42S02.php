<?php

class Error42S02 extends DbError{
    protected $default_message = "Não é possível apagar tabela";
    protected $functions = array('1146');
    public function f1146($msg){
        
        $table = "que está sendo acessada ";
        if(strstr($msg, "Table '") && DEBUG && \usuario_loginModel::IsWebmaster()){
            $camp = explode("'", $msg);
            array_shift($camp);
            $table = "(".array_shift($camp).")";
        }
        return "A tabela $table não existe no banco de dados, consulte o administrador do site para mais detalhes";
    }
}

?>
