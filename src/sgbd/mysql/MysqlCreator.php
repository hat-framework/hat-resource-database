<?php

use classes\Classes\Object;
class MysqlCreator extends classes\Classes\Object implements CreatorInterface {
    
    //informações sobre o banco de dados
    private $pkey   = array(); 
    private $instaled  = array();
    private $unique = array();

    //guarda a instancia atual do banco de dados
    static private $instance;
    public function __construct(){
        $this->bdname = (defined("cbd_name"))?cbd_name:bd_name;
        $this->dir    = dirname(__FILE__);
        $this->engine = bd_engine;
        $this->LoadResource("database", 'db');
        
        databaseResource::set_bd_name("information_schema");
        $this->infoe = databaseResource::OpenNewConnection("Mysql", 'PDO');
    }

    public static function getInstanceOf(){
        $class_name = __CLASS__;
        if (!isset(self::$instance))self::$instance = new $class_name;
        return self::$instance;
    }

    public function setPlugin($plugin){
        $sql = "SELECT TABLE_NAME as tabela
        FROM  `information_schema`.`TABLES`
        WHERE `TABLE_NAME` LIKE '{$plugin}%' AND
       `TABLE_SCHEMA` = '$this->bdname'";
        $var = $this->infoe->ExecuteQuery($sql);
        foreach($var as $t){
            $this->instaled[] = $t['tabela'];
        }
    }

    public function createTable($tabela){
        if($tabela == "") return;
        $tabela = strtolower($tabela);
        if(in_array($tabela, $this->instaled)) return;
        $this->memory[$tabela]['timestamp'] = false;
        return "CREATE TABLE IF NOT EXISTS `$tabela` (";
    }
    
    public function closeTable($tabela){
        if($tabela == "") {return;}
        if(in_array($tabela, $this->instaled)) {return;}
        $str   = $this->getPkeys($tabela);
        $str  .= $this->getUnique($tabela);
        $extra = ($this->autoincrement)?"AUTO_INCREMENT=1":"";
        $this->autoincrement = false;
        $this->virg          = "";
        return "$str) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci $extra;";
    }
    
    private function getPkeys($tabela){
        if($tabela == "") {return;}
        if(empty ($this->pkey)) {return;}
        $pkey = $this->pkey;
        $this->pkey = array();
        $virg = (!empty ($this->unique))? ",":"";
        return " PRIMARY KEY(`".implode("`, `", $pkey)."`)$virg ";
    }
    
    private function getUnique($tabela){
        if($tabela == "") return;
        if(empty ($this->unique)) return;
        $unique = $this->unique;
        $this->unique = array();
        return implode(" , ", $unique);
    }

    public function getFkey($table_src, $table_dst, $name, $coluna, $cardinalidade, $update = "", $delete = ""){
        $constraint = $this->getFkey_Constraint($table_src, $table_dst, $name);
        $sql        = $this->getFkey_Query($constraint);
        $var        = $this->infoe->ExecuteQuery($sql);
        if(!empty ($var)) {return '';}
        $this->getOnModifiers($delete, $update, $cardinalidade);
        
        return "ALTER TABLE `$table_src` ADD CONSTRAINT `$constraint` FOREIGN KEY (`$name`) ".
               "REFERENCES `$table_dst` (`$coluna`) $update $delete;";
    }
    
            private function getFkey_Constraint($table_src, $table_dst, $name){
                $constraint = "$table_src-$table_dst-$name";
                if(strlen($constraint) > 64) {$constraint = substr($constraint, 0, 64);  }
                return $constraint;
            }
    
            private function getFkey_Query($constraint){
                return "SELECT  TABLE_NAME as tname
                FROM  `information_schema`.`TABLE_CONSTRAINTS`
                WHERE  CONSTRAINT_NAME = '$constraint'
                AND TABLE_SCHEMA = '$this->bdname'";
            }
            
            private function getOnModifiers(&$delete, &$update, $cardinalidade){
                //gera as restricoes
                if($delete == ""){
                    if($cardinalidade == "11")     {$delete = "RESTRICT";}
                    elseif($cardinalidade == "1n") {$delete = "CASCADE";}
                    elseif($cardinalidade == "n1") {return;}
                    elseif($cardinalidade == "nn") {$delete = "CASCADE";}
                }
                
                if($update == "") {$update = $delete;}
                $update = "ON UPDATE $update";
                $delete = "ON DELETE $delete";
            }
    
            
    public function destroyPlugin($plugin){
        $temp = $this->destroyFkeys($plugin);
        $sql  = $this->destroyPlugin_Sql($plugin);
        return $this->prepareDropTable($sql, $temp);
    }
    
            public function destroyFkeys($plugin, $onlyChanged = false){
                $sql = $this->destroyFkeys_SQL($plugin);
                $var = $this->infoe->ExecuteQuery($sql);
                return $this->destroyFkeys_sql_out($var, $onlyChanged);
            }
            
                    private function destroyFkeys_SQL($plugin){
                        return "SELECT CONSTRAINT_NAME as cname, TABLE_NAME as tname
                        FROM  `information_schema`.`TABLE_CONSTRAINTS` 
                        WHERE  TABLE_NAME LIKE '".$plugin."%'
                        AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                        AND TABLE_SCHEMA = '$this->bdname'";
                    }
                    
                    private function destroyFkeys_sql_out($var, $onlyChanged = false){
                        $temp = "";
                        foreach($var as $v){
                            if(false === $this->checkChanged($onlyChanged, $v)){continue;}
                            $temp .= " ALTER TABLE `{$v['tname']}` DROP FOREIGN KEY `{$v['cname']}`; ";
                        }
                        return $temp;
                    }
                    
                            private function checkChanged($onlyChanged, $v){
                                if($onlyChanged == false){return true;}
                                $e    = explode('-', $v['cname']);
                                $name = end($e);
                                return (!array_key_exists($name, $this->changed))?false:true;
                            }
    
            private function destroyPlugin_Sql($plugin){
                return "SELECT TABLE_NAME as tabela 
                FROM  `information_schema`.`TABLES` 
                WHERE `TABLE_NAME` LIKE '".$plugin."%' AND
               `TABLE_SCHEMA` = '$this->bdname'";
            }
            
            private function prepareDropTable($sql, $temp){
                $var  = $this->infoe->ExecuteQuery($sql);
                foreach($var as $v){
                    $temp .= "DROP TABLE `{$v['tabela']}`;";
                }
                return $temp;
            }
    
    private $changed = array();
    public function getChanged(){
        return $this->changed;
    }
    
    public function updateSubPlugin($tabela, $dados){
        $this->ignore_instaled = true;
        $sql       = $this->updateSubPlugin_SQL($tabela);
        $installed = $toremove = $this->changed = array();
        $sentenca  = $this->updateSubPlugin_dropDeletedCols($installed, $sql, $dados, $tabela);
        if(empty($dados)) {return "";}
        foreach($dados as $name => $arr){
            if(false === $this->updateSubPlugin_needUpdate($name, $installed, $arr)){continue;}
            $sentenca .= $this->updateSubPlugin_PrepareAddRow($arr, $tabela, $name);       
        }
        $this->ignore_instaled = false;
        return $sentenca;
    }
    
            private function updateSubPlugin_SQL($tabela){
                return "SELECT COLUMN_NAME as coluna
                        FROM  `information_schema`.`COLUMNS` 
                        WHERE TABLE_SCHEMA = '$this->bdname'
                        AND TABLE_NAME =  '$tabela'";
            }
            
            //gera um array com as colunas instalados deste plugin
            private function updateSubPlugin_dropDeletedCols(&$installed, $sql, $dados, $tabela){
                $sentenca  = "";
                $var       = $this->infoe->ExecuteQuery($sql);
                foreach($var as $v){
                    $installed[$v['coluna']] = $v['coluna'];

                    //verifica as colunas removidas
                    if(!array_key_exists($v['coluna'], $dados)){
                        $this->changed[$v['coluna']] = $v['coluna'];
                        $temp      = "ALTER TABLE `$tabela` DROP `".$v['coluna']."`; ";
                        $sentenca .= $temp;
                    }
                }
                return $sentenca;
            }
            
            private function updateSubPlugin_needUpdate($name, $installed, $arr){
                if(array_key_exists($name, $installed)) {return false;}
                if(!array_key_exists('fkey', $arr)){return true;}
                return (!isset($arr['fkey']['cardinalidade']) || in_array($arr['fkey']['cardinalidade'], array('nn','n1')));
            }
            
            private function updateSubPlugin_PrepareAddRow($arr, $tabela, $name){
                if(!array_key_exists('type', $arr)) {return "";}
                $type = $arr['type'];
                $keys = $size = $pkey = $default = $ai = $notnull = $index = $unique = "";

                if($type == "enum" || $type == 'set')  {$keys  = array_keys($arr['options']);}
                elseif(array_key_exists("size", $arr)) {$size  = $arr['size'];}
               
                //tratamento de chaves especiais
                $pkey    = (array_key_exists("pkey", $arr) && $arr['pkey']);
                $unique  = (array_key_exists("unique" , $arr));
                $index   = (array_key_exists("index"  , $arr));

                //tratamento de chaves genéricas
                $notnull = (array_key_exists("notnull", $arr) && $arr['notnull'] == true);
                $ai      = (array_key_exists("ai"     , $arr) && $arr['ai']      == true);
                $default = (array_key_exists("default", $arr)? $arr['default']:"");
                $this->memory[$tabela]['timestamp'] = false;
                $row = $this->addRow($tabela, $name, $type, $pkey, $notnull, $ai, $keys, $size, $default, $index, $unique).";";
                //$r   = str_replace(",", '', $row);
                return "ALTER TABLE `$tabela` ADD $row ";
            }
            
                    private $ignore_instaled = false;
                    private $autoincrement   = false;
                    private $virg            = "";
                    public function addRow($tabela, $name, $type, $pkey, $notnull, $ai, $keys, $size, $default, $index, $unique){
                        if(in_array($tabela, $this->instaled) && !$this->ignore_instaled) {return "";}
                        $this->virg = "";
                        $str        = "";
                        $this->process_byType($str, $name, $type, $keys, $size);
                        $this->process_specialKeys($str, $name, $pkey, $unique, $index, $ai);
                        $this->getDefault($str, $notnull, $default, $tabela, $type);
                        return $this->getString($str);
                    }

                            private function process_byType(&$str, $name, $type, $keys, $size){
                                $str .= "`$name` $type";
                                if($type == "enum" || $type == 'set') {$str .= '("'. implode('","', $keys) .'") ';}
                                else {$str .= ($size == "")?"":"(".str_replace (array("(", ")"), "", $size).") "; }
                            }

                            private function process_specialKeys(&$str, $name, $pkey, $unique, $index, $ai){
                                if($pkey){$this->pkey[$name] = $name;}
                                if($unique){$this->unique[$name] = "UNIQUE(`$name`)";}
                                if($index){$this->index [$name] = "INDEX(`$name`)";}
                                if($ai){
                                    $str .= " AUTO_INCREMENT ";
                                    $this->autoincrement = true;
                                }
                            }

                            private function getDefault(&$str, $notnull, $default, $tabela, $type){

                                if(false === $this->getEmptyDefault($str, $type, $notnull, $default)){return;}

                                if($default == "CURRENT_TIMESTAMP" && !$this->memory[$tabela]['timestamp']){    
                                    if($default == "CURRENT_TIMESTAMP"){$this->memory[$tabela]['timestamp'] = true;}
                                }

                                if(($type == "timestamp" && strtolower($default) == "current_timestamp") || $type != "timestamp"){
                                    $str .= (in_array($default, array("CURRENT_TIMESTAMP", "NULL")) || in_array($type, array('bit')))? 
                                            " DEFAULT $default ": " DEFAULT '$default' ";
                                }

                                if($type != "timestamp" && $notnull){
                                    $str .= " NOT NULL ";
                                }
                            }

                                    private function getEmptyDefault(&$str, $type, $notnull, $default){
                                        if($default == "" || is_array($default)) {
                                            if($type != "timestamp"){
                                                $str .= ($notnull)? " NOT NULL ": " DEFAULT NULL ";
                                            }
                                            return false;
                                        }
                                        return true;
                                    }

                            private function getString($str){
                                $v = "$this->virg$str";
                                $this->virg = "; ";
                                return trim($v);
                            }
    
    public function populateRow($tabela, $qtd = 100){
        $populator = $this->loadPopulator();
        $bool      = $populator->populateRow($tabela, $qtd);
        $this->setMessages($populator->getMessages());
        return $bool;
    }
    
    public function setPalavras($palavras){
        return $this->loadPopulator()->setPalavras($palavras);
    }
    
    public function setParagrafos($paragrafos){
        return $this->loadPopulator()->setParagrafos($paragrafos);
    }
    
            private function loadPopulator(){
                static $obj;
                if(is_object($obj)){return $obj;}
                $class    = "MysqlPopulator";
                $filename = __DIR__ ."/$class.php";
                if(!file_exists($filename)){die("populator não encontrado $filename");}
                require_once $filename;
                if(!class_exists($class, false)){die("populator não encontrado $class");}
                $obj = call_user_func($class ."::getInstanceOf");
                return $obj;
            }

}