<?php

class ErrorHY000 extends DbError{
    protected $default_message = "Coluna ou tabela não existe no banco de dados";
    public function f1045($msg){
        $exp = explode("user", $msg);
        array_shift($exp);
        $exp = array_shift($exp);
        $exp = explode("(", $exp);
        $user = array_shift($exp);
        return "Não foi possível conectar ao banco de dados! Acesso negado ao usuário $user";
    }
    
    public function f1049($msg){
        return "Erro ao acessar o banco de dados: banco de dados desconhecido!";
    }
    
    public function f2002($msg){
        return "Não foi possível conectar ao servidor de banco de dados!";
    }
}

?>
