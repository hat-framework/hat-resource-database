<?php

class migrateResource extends \classes\Interfaces\resource{
    
    public function __construct(){
        parent:: __contruct();
        $this->folder = DIR_FILES."export". DS;
        $this->LoadResource('files/dir', 'dobj');
        $this->LoadResource('files/file', 'fobj');
    }
    
    private $plugin    = "";
    private $db1       = "";
    private $dbname    = "";
    private $db2       = "";
    private $dbname2   = "";
    private $blacklist = array();
    
    public function setBlackList($bl){
        $this->blacklist = $bl;
        return $this;
    }
    
    public function migrateData($plugin, $db1, $dbname, $db2, $dbname2){
        $this->plugin  = $plugin;
        $this->db1     = $db1;
        $this->dbname  = $dbname;
        $this->db2     = $db2;
        $this->dbname2 = $dbname2;
        $var1          = $this->getSchema($this->plugin, $this->dbname , $this->db1);
        $var2          = $this->getSchema($this->plugin, $this->dbname2, $this->db2);
        $i             = 0;
        $bool          = true;
        $total         = count($var1);
        $logname       = "migration/$this->plugin";
        \classes\Utils\Log::delete($logname);
        \classes\Utils\Log::save($logname, "<h2>Importando o plugin $plugin</h2>");
        foreach($var1 as $table){
            $i++;
            if(!array_key_exists($table, $var2)){continue;}
            if(in_array($table, $this->blacklist)){continue;}
            $url = URL."/index.php?url=site/index/log";
            \classes\Utils\Log::save($logname, "Importando <a href='$url&file=$logname/$table.html'>$table</a> ($i de $total)");
            $bool = $bool and $this->import($table, $this->db1, $this->db2);
        }
        \classes\Utils\Log::save($logname, "Importação do plugin $plugin concluída!<hr/>");
        if(true === $bool){$this->setSuccessMessage("Importação concluída com sucesso!");}
        return $bool;
    }
    
    private function getSchema($plugin, $dbname, $db){
        $sql = "
            SELECT TABLE_NAME as tabela 
            FROM `information_schema`.`TABLES` 
            WHERE 
               `TABLE_NAME` LIKE '{$plugin}%' AND 
               `TABLE_SCHEMA` = '{$dbname}';
        ";
        $var = $db->ExecuteQuery($sql);
        if(empty($var)){return $var;}
        $out = array();
        foreach($var as $v){
            $out[$v['tabela']] = $v['tabela'];
        }
        return $out;
    }
                    
    private $pagesize = 10000;
    private function import($table){
        $logname = "migration/$this->plugin/$table";
        \classes\Utils\Log::delete($logname);
        \classes\Utils\Log::save($logname, "Importando a tabela $table");
        
        $out = $this->db1->executeQuery("select count(*) as total from $table");
        if(empty($out)){return $this->LogError("Erro ao carregar os dados da $table do db1!", $logname);}
        $total = $out[0]['total'];
        if($total === 0 || $total === "0"){return true;}
        \classes\Utils\Log::save($logname, "Nº Registros: ($total)");
        
        $totalpages = ceil($total/$this->pagesize);
        //echo ("$totalpages - $total <br/>");
        $i     = 0;
        $bool  = true;
        $arr   = array();
        do{
            
            $offset = ($this->pagesize*$i);
            $arr    = $this->db1->Read($table, array(), '', $this->pagesize, $offset);
            if(empty($arr)){break;}
            $i++;
            $b = $this->db2->importDataFromArray($arr, $table);
            $bool = $bool and $b;
            \classes\Utils\Log::save($logname, "Importação $i de $totalpages (".  number_format($i/$totalpages, 2) ."%)");
            if($b === false){
                $this->LogError("Erro ao importar dados da tabela $table", $logname, $this->db2->getMessages());
            }
        }while($i <= $totalpages);
        \classes\Utils\Log::save($logname, "Importação da tabela $table concluída!");
        return $bool;
    }
    
}