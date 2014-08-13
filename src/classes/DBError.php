<?php

class DBError{
    protected $default_message = "Erro desconhecido no banco de dados!";
    protected $functions = array();
    public function getMessage($msg){
        if(!empty($this->functions)){
            foreach($this->functions as $func){
                if(strstr($msg, $func) === false) continue;
                $function = "f$func";
                if(!method_exists($this, $function)) continue;
                return $this->$function($msg);
            }
        }
        return $this->default_message;
    }
}

?>