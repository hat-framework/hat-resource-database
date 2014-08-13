<?php

interface DBConectionInterface{
    
    public function connect();
    
    public function getConection();
    
    public function execute(&$query, $fetch = true);
    
    public function beginTransaction();
    
    public function stopTransaction($query);
    
    public function rollback();
}

?>
