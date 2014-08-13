<?php

use classes\Classes\Object;
class MySqliConection extends classes\Classes\Object implements DBConectionInterface{
    
    private $mysqli = NULL;
    public function connect(){
        
       mysqli_report(MYSQLI_REPORT_ALL); 
       $this->mysqli = new mysqli(bd_server, bd_user, bd_password, bd_name);
       if (mysqli_connect_errno()) {
            throw new DBException("Falha ao conectar ao banco de dados", mysqli_connect_error());
            exit();
       }
        
       if(!is_object($this->mysqli)){
           $msg = "";
           if(dbdebug){
               $msg = "Há um erro na conexão com o banco de dados<br/>
                   arquivo: (". __FILE__ .")<br/>
                   método:  (". __METHOD__ .")";
            }
           $this->setErrorMessage(__CLASS__ . ": Não foi possível criar o objeto do banco de dados, erro! $msg");
           return false;
       }
       return true;
    }
    
    public function getConection(){
        return $this->mysqli;
    }
    
    
    public function execute($query, $fetch = true){
        
        $mysqli = $this->getConection();
        if(!is_object($mysqli)){
            if($fetch){
                return array();
            }
            return false;
        }
        if(!$result = $mysqli->prepare($query)){
            throw new DBException("Erro ao executar consulta . $mysqli->error" );
        }
        
        $result->execute();      
        if($fetch){
            return($result->fetch_array(MYSQLI_ASSOC));
        }
        return true;
    }
    
    public function beginTransaction(){
        
    }
    
    public function stopTransaction(){
        
    }
    
    
}


?>
