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
        FROM  `TABLES`
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
        if($tabela == "") return;
        if(in_array($tabela, $this->instaled)) return;
        $str   = $this->getPkeys($tabela);
        $str  .= $this->getUnique($tabela);
        $extra = ($this->autoincrement)?"AUTO_INCREMENT=1":"";
        $this->autoincrement = false;
        $this->virg          = "";
        return "$str) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci $extra;";
    }
    
    private $ignore_instaled = false;
    private $autoincrement   = false;
    private $virg            = "";
    public function addRow($tabela, $name, $type, $pkey, $notnull, $ai, $keys, $size, $default, $index, $unique){
        if(in_array($tabela, $this->instaled) && !$this->ignore_instaled) return "";
        $str = "`$name` $type";
        if($type == "enum" || $type == 'set') $str .= '("'. implode('","', $keys) .'") ';
        else $str .= ($size == "")?"":"(".str_replace (array("(", ")"), "", $size).") ";
        
        if($pkey)          $this->pkey[$name]   = $name;
        if($unique)        $this->unique[$name] = "UNIQUE(`$name`)";
        if($index)         $this->index [$name] = "INDEX(`$name`)";
        if($ai){
            $str .= " AUTO_INCREMENT ";
            $this->autoincrement = true;
        }
        if($default != "" && !is_array($default)) {
            $ignore = array("CURRENT_TIMESTAMP", "NULL");
            $types  = array('bit');
            if($default == "CURRENT_TIMESTAMP" && !$this->memory[$tabela]['timestamp']){    
                if($default == "CURRENT_TIMESTAMP"){$this->memory[$tabela]['timestamp'] = true;}
            }
            
            /*elseif($this->memory[$tabela]['timestamp'] == true){
                die("Tabela ($tabela) possui mais de um atributo timestamp. $name é um deles.");
            }*/
            
            if(($type == "timestamp" && strtolower($default) == "current_timestamp") || $type != "timestamp"){
                $str .= (in_array($default, $ignore) || in_array($type, $types))? 
                        " DEFAULT $default ": " DEFAULT '$default' ";
            }
            
        }
        
        if($type != "timestamp"){
            $str .= ($notnull)? " NOT NULL ": " DEFAULT NULL ";
        }
        $v = "$this->virg$str";
        $this->virg = ", ";
        return trim($v);
    }
    
    private function getPkeys($tabela){
        if($tabela == "") return;
        if(empty ($this->pkey)) return;
        $pkey = $this->pkey;
        $this->pkey = array();
        $virg = (!empty ($this->unique))? ",":"";
        return $this->virg."PRIMARY KEY(`".implode("`, `", $pkey)."`)$virg";
    }
    
    private function getUnique($tabela){
        if($tabela == "") return;
        if(empty ($this->unique)) return;
        $unique = $this->unique;
        $this->unique = array();
        return implode(" , ", $unique);
    }

    public function getFkey($table_src, $table_dst, $name, $coluna, $cardinalidade, $update = "", $delete = ""){
        $constraint = "$table_src-$table_dst-$name";
        if(strlen($constraint) > 64) $constraint = substr($constraint, 0, 64);  
        $sql = "SELECT  TABLE_NAME as tname
                FROM  `TABLE_CONSTRAINTS`
                WHERE  CONSTRAINT_NAME = '$constraint'
                AND TABLE_SCHEMA = '$this->bdname'";
        $var = $this->infoe->ExecuteQuery($sql);
        //se a constraint já existe então retorna
        if(!empty ($var)) { return;}
        //gera as restricoes
        if($delete == ""){
            if($cardinalidade == "11")     $delete = "RESTRICT";
            elseif($cardinalidade == "1n") $delete = "CASCADE";
            elseif($cardinalidade == "n1") return;
            elseif($cardinalidade == "nn") $delete = "CASCADE";
        }
        if($update == "") $update = $delete;
        $update = "ON UPDATE $update";
        $delete = "ON DELETE $delete";
        $var = "
          ALTER TABLE `$table_src` ADD CONSTRAINT `$constraint` FOREIGN KEY (`$name`) 
          REFERENCES `$table_dst` (`$coluna`) $update $delete;";
        return $var;
    }
    
    public function destroyPlugin($plugin){
        $temp = $this->destroyFkeys($plugin);
        $sql = "SELECT TABLE_NAME as tabela 
        FROM  `TABLES` 
        WHERE `TABLE_NAME` LIKE '".$plugin."%' AND
       `TABLE_SCHEMA` = '$this->bdname'";
        
        $var = $this->infoe->ExecuteQuery($sql);
        foreach($var as $v){
            extract($v);
            $temp .= "DROP TABLE `$tabela`;";
        }
        return $temp;
    }
    
    public function destroyFkeys($plugin){
        $sql = "SELECT CONSTRAINT_NAME as cname, TABLE_NAME as tname
                FROM  `TABLE_CONSTRAINTS` 
                WHERE  TABLE_NAME LIKE '".$plugin."%'
                AND CONSTRAINT_TYPE = 'FOREIGN KEY'
                AND TABLE_SCHEMA = '$this->bdname'";
        $var = $this->infoe->ExecuteQuery($sql);
        $temp = "";
        foreach($var as $v){
            extract($v);
            $temp .= "
            ALTER TABLE `$tname`
            DROP FOREIGN KEY `$cname`; ";
        }
        return $temp;
    }
    
    public function updateSubPlugin($tabela, $dados){
        $this->ignore_instaled = true;
        $sql = "SELECT COLUMN_NAME as coluna
                FROM  `COLUMNS` 
                WHERE TABLE_SCHEMA = '$this->bdname'
                AND TABLE_NAME =  '$tabela'";
        $var = $this->infoe->ExecuteQuery($sql);
        
        //gera um array com as colunas instalados deste plugin
        $sentenca  = "";
        $installed = $toremove = array();
        foreach($var as $v){
            $installed[$v['coluna']] = $v['coluna'];
            
            //verifica as colunas removidas
            if(!array_key_exists($v['coluna'], $dados)){
                $sentenca .= "ALTER TABLE `$tabela` DROP `".$v['coluna']."`; ";
            }
        } 
        
        //verifica as colunas não instaladas
        if(empty($dados)) return "";
        foreach($dados as $name => $arr){
            if(array_key_exists($name, $installed)) continue;
            if(array_key_exists('fkey', $arr)){
                if(in_array($arr['fkey']['cardinalidade'], array('nn','n1'))) continue;
            }
            
            if(!array_key_exists('type', $arr)) continue;
            $type = $arr['type'];
            $keys = $size = $pkey = $default = $ai = $notnull = $index = $unique = "";
            
            if($type == "enum" || $type == 'set')  $keys  = array_keys($arr['options']);
            elseif(array_key_exists("size", $arr)) $size  = $arr['size'];
            
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
            $sentenca .= "ALTER TABLE `$tabela` ADD $row";
        }
        $this->ignore_instaled = false;
        return $sentenca;
    }
    
    public function populateRow($tabela, $qtd = 100){

        $sql = "
        SELECT COLUMN_NAME as coluna, DATA_TYPE as tipo, COLUMN_TYPE as size, COLUMN_KEY as pkey
        FROM  `COLUMNS` 
        WHERE TABLE_NAME = '$tabela' AND
        EXTRA != 'auto_increment' AND
        TABLE_SCHEMA = '$this->bdname'";
        $tabelas = $this->infoe->ExecuteQuery($sql);
        if(empty ($tabelas)) return;
        foreach($tabelas as $v){
            $keys[] = $v['coluna'];
            $arr[$v['coluna']]['tipo']   = $v['tipo'];
            $arr[$v['coluna']]['size']   = str_replace(array($v['tipo'], "(", ")"), "", $v['size']);
            $arr[$v['coluna']]['pkey']   = ($v['pkey'] == "PRI")?"1":'0';
            $arr[$v['coluna']]['unique'] = ($v['pkey'] == "UNI")?"1":'0';
        }

        $sql = "
        SELECT TABLE_NAME as tname, COLUMN_NAME as tcol, REFERENCED_TABLE_NAME as tref, REFERENCED_COLUMN_NAME as colref
        FROM  `REFERENTIAL_CONSTRAINTS` rc
        NATURAL JOIN KEY_COLUMN_USAGE ku
        NATURAL JOIN TABLE_CONSTRAINTS tc
        WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
        AND CONSTRAINT_NAME LIKE '".$tabela."%'
        AND CONSTRAINT_SCHEMA = '$this->bdname'";
        $fkeys = $this->infoe->ExecuteQuery($sql);
        foreach($fkeys as $v){
            print_r($v);
            echo "<br/>";
            $arr[$v['tcol']]['fkey']['tabela'] = $v['tref'];
            $arr[$v['tcol']]['fkey']['key']    = $v['colref'];
        }
        
        $keys = implode(", ",$keys);
        $values = $this->genValues($arr, $qtd);
        $str = "INSERT INTO `$tabela` ($keys) VALUES $values;";
        echo "$tabela<br/><br/>";
        foreach($arr as $name => $v){
            echo "$name: ";
            print_r($v);
            echo "<br/>";
        }
        echo("<br/><br/>".$str);
        
        echo "<hr/>";
    }


    private function genValues($array, $qtd){

        $ignore = array("CURRENT_TIMESTAMP", "NULL");
        $tstr = $v2 = "";
        $i = 0;
        while($i < $qtd){
            $v = "";
            $str = "$v2(";
            foreach($array as $arr){
                extract($arr);
                $valortipo = str_replace("'", '"', $this->tipos($tipo, $size));
                if(in_array($valortipo, $ignore))$str .= "$v " . $valortipo;
                else $str .= "$v '" . $valortipo . "'";
                $v   = ", ";
            }
            $str .= ")";
            $v2 = ", ";
            $tstr .= $str;
            $i++;
        }
        return $tstr;
    }

    private function tipos($tipo, $size){
        static $err = array();
        if($tipo == "int")     return rand(0, pow(10,$size));
        elseif($tipo == "varchar") {return $this->palavras[rand(0, count($this->palavras) - 1)];}
        elseif(strstr($tipo, "text") !== false) return $this->paragrafos[rand(0, count($this->paragrafos)-1)];
        elseif($tipo == "float")    return (rand(0, 10^$size) + "."+rand(0, 2) );
        elseif($tipo == "decimal")  return (rand(0, 10^$size) + "."+rand(0, 2) );
        elseif($tipo == "enum")    {$exp = explode(",", $size); return ($exp[rand(0, count($exp)-1)]);}
        elseif($tipo == "timestamp"){return 'CURRENT_TIMESTAMP';}
        elseif($tipo == "datetime"){return date("Y-m-d H:i:s",rand(1262055681,1262055681));}
        elseif(!isset($err[$tipo])) {
            $err[$tipo] = "";
            echo " Warning: Tipo ($tipo) não tratado no gerador de tipos do MysqlCreator<br/>";
        }
        return "NULL";
        
    }

    public function setPalavras($palavras){
        foreach($palavras as $i => $p ){
            if(strlen($p) < 4) continue;
            $this->palavras[] = $p;
        }
        
    }
    
    public function setParagrafos($paragrafos){
        foreach($paragrafos as $i => $p ){
            if(strlen($p) < 4) continue;
            $this->paragrafos[] = $p;
        }
    }

}
?>