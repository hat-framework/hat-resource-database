<?php

class MysqlPopulator extends classes\Classes\Object {

    //guarda a instancia atual do banco de dados
    static private $instance;
    public function __construct(){
        $this->bdname = (defined("cbd_name"))?cbd_name:bd_name;
        $this->LoadResource("database", 'db');
        
        databaseResource::set_bd_name("information_schema");
        $this->infoe = databaseResource::OpenNewConnection("Mysql", 'PDO');
    }

    public static function getInstanceOf(){
        $class_name = __CLASS__;
        if (!isset(self::$instance)){self::$instance = new $class_name;}
        return self::$instance;
    }
    
    public function populateRow($tabela, $qtd = 100){
        $keys = $arr = array();
        $this->populateRow_prepareArray($tabela, $keys, $arr);
        $this->populateRow_prepareFkeyArray($tabela, $arr);
        $this->populateRow_generateQuery(&$keys, $arr, $tabela, $qtd);
        return true;
    }
    
            private function populateRow_prepareArray($tabela, &$keys, &$arr){
                $sql     = $this->populateRow_SQL($tabela);
                $tabelas = $this->infoe->ExecuteQuery($sql);
                if(empty ($tabelas)) {return false;}
                foreach($tabelas as $v){
                    $keys[] = $v['coluna'];
                    $arr[$v['coluna']]['tipo']   = $v['tipo'];
                    $arr[$v['coluna']]['size']   = str_replace(array($v['tipo'], "(", ")"), "", $v['size']);
                    $arr[$v['coluna']]['pkey']   = ($v['pkey'] == "PRI")?"1":'0';
                    $arr[$v['coluna']]['unique'] = ($v['pkey'] == "UNI")?"1":'0';
                }
            }
            
                    private function populateRow_SQL($tabela){
                        return "SELECT COLUMN_NAME as coluna, DATA_TYPE as tipo, COLUMN_TYPE as size, COLUMN_KEY as pkey
                        FROM  `information_schema`.`COLUMNS` 
                        WHERE TABLE_NAME = '$tabela' AND
                        EXTRA != 'auto_increment' AND
                        TABLE_SCHEMA = '$this->bdname'";
                    }
            
            private function populateRow_prepareFkeyArray($tabela, &$arr){
                $sql2 = $this->populateRow_ForeignSql($tabela);
                $fkeys = $this->infoe->ExecuteQuery($sql2);
                foreach($fkeys as $v){
                    $arr[$v['tcol']]['fkey']['tabela'] = $v['tref'];
                    $arr[$v['tcol']]['fkey']['key']    = $v['colref'];
                }
            }
            
                    private function populateRow_ForeignSql($tabela){
                        return "
                        SELECT TABLE_NAME as tname, COLUMN_NAME as tcol, REFERENCED_TABLE_NAME as tref, REFERENCED_COLUMN_NAME as colref
                        FROM  `information_schema`.`REFERENTIAL_CONSTRAINTS` rc
                        NATURAL JOIN KEY_COLUMN_USAGE ku
                        NATURAL JOIN TABLE_CONSTRAINTS tc
                        WHERE CONSTRAINT_TYPE = 'FOREIGN KEY'
                        AND CONSTRAINT_NAME LIKE '".$tabela."%'
                        AND CONSTRAINT_SCHEMA = '$this->bdname'";
                    }
            
            private function populateRow_generateQuery(&$keys, $arr, $tabela, $qtd){
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
                                $valortipo = str_replace("'", '"', $this->genValues_types($arr['tipo'], $arr['size']));
                                if(in_array($valortipo, $ignore)){$str .= "$v " . $valortipo;}
                                else {$str .= "$v '" . $valortipo . "'";}
                                $v   = ", ";
                            }
                            $str .= ")";
                            $v2 = ", ";
                            $tstr .= $str;
                            $i++;
                        }
                        return $tstr;
                    }

                            private function genValues_types($tipo, $size){
                                static $err = array();
                                $method = "genValues_$tipo";
                                if(!method_exists($this, $method)){
                                    if(!isset($err[$tipo])){
                                        $err[$tipo] = "";
                                        echo " Warning: Tipo ($tipo) não tratado no gerador de tipos do MysqlCreator<br/>";
                                    }
                                    
                                    return "NULL";
                                }
                                return $this->$method($size);
                            }
                            
                                    public function genValues_int($size){
                                        return rand(0, pow(10,$size));
                                    }
                                    
                                    public function genValues_varchar($size){
                                        return $this->palavras[rand(0, count($this->palavras) - 1)];
                                    }
                                    
                                    public function genValues_text(){
                                        return $this->paragrafos[rand(0, count($this->paragrafos)-1)];
                                    }
                                    
                                    public function genValues_float($size){
                                        return (rand(0, 10^$size) + "."+rand(0, 2) );
                                    }
                                    
                                    public function genValues_decimal($size){
                                        return (rand(0, 10^$size) + "."+rand(0, 2) );
                                    }
                                    
                                    public function genValues_enum($size){
                                        $exp = explode(",", $size); 
                                        return ($exp[rand(0, count($exp)-1)]);
                                    }
                                    
                                    public function genValues_timestamp(){
                                        return date("Y-m-d H:i:s",rand(1262055681,1262055681));
                                    }
                                    
                                    public function genValues_datetime(){
                                        return date("Y-m-d H:i:s",rand(1262055681,1262055681));
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