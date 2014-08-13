<?php

class Error23000 extends DBError{
    
    protected $functions = array('1048', '1062', '1451', '1452');
    protected $default_message = "Valor já existe ou erro com chave estrangeira.";
    
    public function f1048($msg){
        $append = "Uma regra (constraint) falhou!";
        if(strstr($msg, "be null")){
            $msg = explode("'", $msg);
            array_shift($msg);
            $campo = array_shift($msg);
            $append = "Preencha o campo $campo.";
        }
        return $append;
    }
    
    public function f1062($msg){
        $append = "Já existe um";
        $camp = "";
        if(strstr($msg, "key '")){
            $camp = explode("key '", $msg);
            array_shift($camp);
            $camp = implode("", $camp);
            $camp = explode("'", $camp);
            $camp = array_shift($camp);
            $camp = ucfirst($camp);
        }
        
        if(strstr($msg, "PRIMARY'"))
            $append = "Este item já está cadastrado no banco de dados";
        elseif(strstr($msg, "entry '")){
            $msg = explode("entry '", $msg);
            array_shift($msg);
            $msg = implode("", $msg);
            $msg = explode("'", $msg);
            $msg = array_shift($msg);
            $append = "$camp: $msg já existe.";
        }
        return "$append";
    }
    
    public function f1451($msg){
        return "Não é possível remover este registro: existem outros registros relacionados a este.";
    }
    
    public function f1452($msg){
        $append = "Uma regra (constraint) falhou!";
        if(strstr($msg, "FOREIGN KEY (`")){
            $msg = explode("FOREIGN KEY (`", $msg);
            array_shift($msg);
            $msg = implode("", $msg);
            $msg = explode("`)", $msg);
            $msg = array_shift($msg);
            $append = "O atributo $msg não foi preenchido corretamente!";
        }
        return "Não é possível modificar os dados. $append";
    }
    
    
    
    
}

?>
