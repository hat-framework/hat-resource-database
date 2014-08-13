<?php

class Error42000 extends DbError{
    protected $default_message = "Erro ao acessar banco de dados: Banco não existe!";
    protected $functions = array('1044', '1049', '1064');
    public function f1044($msg){
        $msg = explode(" user '", $msg);
        $msg = end($msg);
        return "Acesso negado ao usuário: '$msg";
    }
    
    public function f1049($msg){
        $msg = explode("'", $msg);
        array_shift($msg);
        $db = array_shift($msg);
        return "O Banco de dados '$db' não foi encontrado!";
    }
    
    public function f1064($msg){
        return "Erro de sintaxe ou violação de acesso ao acessar o banco de dados";
    }
    
}

?>
