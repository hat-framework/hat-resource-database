<?php

use classes\Classes\Object;
class sqliteConection extends classes\Classes\Object implements DBConectionInterface{
    
    private $sql = NULL;
    public function connect(){
        $sqliteerror = "";
        $file = dirname(__FILE__) . "/hat";
        $this->sql = new SQLiteDatabase($file, 0666, $sqliteerror); 
        if ($this->sql) return true;
        
        if ($sqliteerror != "") {
            throw new DBException($sqliteerror . " - Verifique se o diretorio (" . dirname(__FILE__) . ") tem permissao de escrita");
            exit();
        }
        return true;
    }
    
    public function getConection(){
        return $this->sql;
    }
    
    public function execute($query, $fetch = true){
        
        $error = "";
        $sql   = $this->getConection();
        $query = str_replace("`", '', $query);
        $q     = $sql->query($query, SQLITE_ASSOC, $error);
        if($error != "") throw new DBException($error);
        if($fetch)       return($q->fetchAll());
        return true;
    }
    
    public function beginTransaction(){
        
    }
    
    public function stopTransaction($query){
        
    }
    
    public function rollback(){
        
    }
    
    
}


?>
