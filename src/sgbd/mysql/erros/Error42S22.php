<?php

class Error42S22 extends DbError{
    
    protected $functions = array('1054');
    protected $default_message = "Coluna ou tabela não existe no banco de dados";
    public function f1054($msg){
        $append = "Coluna não existe no banco de dados";
        if(strstr($msg, "Unknown column '")){
            $msg = explode("Unknown column '", $msg);
            array_shift($msg);
            $msg = implode("", $msg);
            $msg = explode("'", $msg);
            $msg = array_shift($msg);
            $append = "A coluna $msg não existe na tabela!";
        }
        return $append;
    }
    
}

?>
