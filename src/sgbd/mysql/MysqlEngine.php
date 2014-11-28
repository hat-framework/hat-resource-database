<?php

class MysqlEngine extends \classes\Interfaces\resource implements DatabaseInterface {
    
    //informações sobre o banco de dados
    private $bd_name = bd_name;
    
    //mode de debug
    private $debug;
    
    //guarda os joins que serao feitos
    private $join;
    
    //guarda a query que ainda será execultada
    private $query;
    
    //guarda a ultima sentença sql execultada
    private $sentenca;
    
    //seta se determinado comando é uma transação
    private $is_transaction = false;
    
    //contem a conexao com o banco de dados
    private $conn = null;
    
    //guarda a instancia atual do banco de dados
    static private $instance;
    public function __construct($bd_server = "", $bd_name = "", $bd_user = "", $bd_password = ""){
        $this->dir = dirname(__FILE__);
        $this->engine = bd_engine;
        if(!$this->connect($bd_server, $bd_name, $bd_user, $bd_password)){
            throw new \classes\Exceptions\DBException($this->getErrorMessage());
        }
        
    }

    public static function getInstance($bd_server, $bd_name, $bd_user, $bd_password){
        $class_name = __CLASS__;
        if (!isset(self::$instance[$bd_server][$bd_name])) {
            self::$instance[$bd_server][$bd_name] = new $class_name($bd_server, $bd_name, $bd_user, $bd_password);
        }
        return self::$instance[$bd_server][$bd_name];
    }
    
    //conecta ao banco de dados
    private function connect($bd_server, $bd_name, $bd_user, $bd_password){
        
        try{
            $class = $this->engine . "Conection";
            $file  = dirname(__FILE__). "/conections/$class.php";
            if(!file_exists($file)) throw new \classes\Exceptions\DBException("Classe ($class) não encontrada");
            
            require_once $file;
            $this->bd_name = $bd_name;
            $this->conn = new $class($bd_server, $bd_name, $bd_user, $bd_password);
            if(!$this->conn->connect()) 
                    throw new \classes\Exceptions\DBException("Não foi possíevel estabelecer comunicação com o
                    banco de dados.");
            return true;
        }
        catch (Exception $e){
            $this->generateErrorMessage($e);
            return false;
        }
    }
    
    /*
     * operacoes da interface
     */

    public function Insert($table, array $dados){
        $campos = array();
        if(empty ($dados)){
            $this->setErrorMessage( __CLASS__ .": Dados não enviados ao inserir no banco de dados");
            return false;
        }
        foreach($dados as $inds => $vals)
        {
            $campos[] = "`$inds`"; 
            if(strpos($vals, "FUNC_") === false){
                $valores[] = "'$vals'";
            }
            else{
                $vals = explode("FUNC_", $vals);
                $vals = end($vals);
                $valores[] = "$vals";
            }
        }
        $campos =  implode(", ", array_values($campos));
        $valores = implode(", ", array_values($valores));

        $query = " INSERT INTO ".$this->bd_name .".". $table. "(".$campos.")"." VALUES(".$valores."); ";
        $this->setQuery($query);
        if(!$this->execute($fetch = false)) return false;
        
        $this->setQuery("SELECT LAST_INSERT_ID() as id");
        $this->sentenca = $query;
        $exec = $this->execute();
        $exec = array_shift($exec);
        return $exec['id'];
    }
    
    public function clearJoin(){
        $this->join = "";
    }
    
    public function setJoin($join){
        $this->join = $join;
    }
    
    public function getJoin(){
        return $this->join;
    }

    public function addJoin($join){
        $this->join .= "$join";
    }

    public function Join($table_src, $table_dst, $key_src = array(), $key_dst = array(), $side = "NATURAL"){
        
        $and = $conditions = "";
        if($side == "") $side = "NATURAL";
        
        $trename = explode(" as ", $table_dst);
        array_shift($trename);
        if(!empty ($trename)) $trename = array_shift($trename);
        else                  $trename = $table_dst;
        
        if($side != "NATURAL"){
            $array = array_combine($key_src, $key_dst);
            foreach ($array as $n => $v){
                $conditions .= "$and $table_src.$n = $trename.$v";
                $and = " AND ";
            }
        }
        if($conditions != "") $conditions = "ON ($conditions)";
        $this->join .= "$side JOIN $table_dst $conditions";
    }

    public function Read($table, $campos = NULL, $where = NULL, $limit = NULL, $offset = NULL, $orderby = NULL){
        
        if(is_array($table)){
            $virg   = "";
            $ntable = "";
            foreach($table as $tb){
                $ntable .= "$virg $tb";
                $virg = ",";
            }
            $table = $ntable;
        }
        else{
            $table = "$this->bd_name.$table";
        }

        $where   = ($where   != NULL)   ? "WHERE {$where}"       : "";
        $limit   = ($limit   != NULL)   ? "LIMIT {$limit}"       : "";
        $offset  = ($offset  != NULL)   ? "OFFSET {$offset}"     : "";
        $orderby = ($orderby != NULL)   ? "ORDER BY {$orderby}"  : "";
        $campos  = ($campos  != NULL && is_array($campos)) ? implode(", ", $campos) : " * ";
        $query = (" SELECT  $campos FROM $table $this->join {$where} {$orderby} {$limit} {$offset}");
        $this->join = "";
        $this->setQuery($query);
        return $this->execute(true);
        
    }

    public function Update($table, array $dados, $where)
    {
            $campos = array();
            foreach($dados as $inds => $vals){
                $inds = (strstr($inds, '.'))?$inds:"`$inds`";
                if(strpos($vals, "FUNC_") === false){
                    $campos[] = "$inds = '$vals'";
                }
                else{
                    $vals = explode("FUNC_", $vals);
                    $vals = end($vals);
                    $campos[] = "$inds = $vals";
                }
            }
            $campos = implode(", ", $campos);
            $where = ($where == "")?"":"WHERE $where";
            $query = " UPDATE $this->bd_name.$table $this->join SET $campos $where";
            $this->join = "";
            $this->setQuery($query);
            return $this->execute($fetch = false);

    }

    public function Delete($table, $where){
        
        if($where == NULL || $where == ""){
            $this->setErrorMessage(__CLASS__ . ": Cláusula where nao pode ser vazia");
        }
        $query = " DELETE FROM $this->bd_name.$table WHERE {$where}";
        $this->setQuery($query);
        return $this->execute($fetch = false);

    }

    public function ExecuteQuery($bd_query){
        $this->setQuery($bd_query);
        return $this->execute(true);
    }
    
    public function ExecuteInsertionQuery($bd_query){
        $this->setQuery($bd_query);
        return $this->execute(false);
    }
    
    public function StartTransation(){
        if(!$this->is_transaction){
            $this->conn->beginTransaction();
            $this->setQuery(" START TRANSACTION ");
            $this->is_transaction = true;
        }
    }
    
    public function StopTransation(){
        $this->setQuery(" COMMIT ");
        $this->is_transaction = false;
        try{
            $this->resetQuery();
            $this->conn->stopTransaction("");
            return true;
        }

        catch (Exception $e){
            $this->conn->rollback();
            $this->generateErrorMessage($e);
            return false;
        }
    }
    
    private function execute($fetch = true){
        try{
            $query = $this->getQuery();
            $this->resetQuery();
            if($this->is_transaction) {
                return (!$this->conn->ExecuteInTransaction($query));
            }
            //echo "($query - $fetch)<br/>";
            return($this->conn->execute($query, $fetch));
        }


        catch (Exception $e){
            $this->generateErrorMessage($e);
            return false;
        }
    }
    
    public function getSentenca(){
        return $this->sentenca;
    }
    
    public function getFormatedSentenca(){
        return str_replace(
            array("FROM", "LEFT JOIN", "WHERE", "AND", "ORDER", "LIMIT", "OFFSET"), 
            array("<br/><b>FROM</b>", "<br/><b>LEFT JOIN</b>", "<br/><b>WHERE</b>", "<b>AND</b><br/>", "<br/><b>ORDER</b>", "<br/><b>LIMIT</b>", "<br/><b>OFFSET</b>"), 
            $this->getSentenca());
    }
    
    public function getQuery(){
        return $this->query;
    }
    
    public function resetQuery(){
        $this->query = "";
    }

    public function showTables(){
        return $this->ExecuteQuery("show tables");
    }

    private function setQuery($query){
        $this->query .= $query . ";";
        $this->sentenca = $this->query;
    }

    
    private function generateErrorMessage($e){

        $msg   = $e->getMessage();
        $error = $e->getCode();
        $debug = $this->LoadErrorFromClass($error, $error, $msg);
        $default_error = "$error: Erro desconhecido no banco de dados";
        if($debug == ""){
            $temp = explode("SQLSTATE[", $e->getMessage());
            if(count($temp) > 1){
                $temp = $temp[1];
                $temp = explode("]", $temp);
                $state = trim(str_replace("[", '', $temp[0]));
                $erro  = trim(str_replace("[", '', $temp[1]));
                $debug = $this->LoadErrorFromClass($erro, $state, $msg, true);
            }
            if($debug == "") $debug = $default_error;
        }
        
        if(DEBUG){
            $sentenca = $this->getFormatedSentenca();
             $debug .= "
                <hr/>
                <div class='error'>
                    <b>Mensagem de erro</b>: <br/>$msg<br/><br/>
                    <b>Código de erro</b>:   <br/>$error<br/><br/>";
             if(trim($sentenca) != '')$debug .= "<b>Sentença sql</b>: <br/>$sentenca<br/><br/>";
             $debug .= 
                 "</div>
                <hr/>";
        }
        $arr['erro'] = $debug;
        $arr['status'] = '0';
        $this->setErrorMessage($debug);
    }
    
    private function LoadErrorFromClass($erro, $state, $msg, $die = false){
        $class = "Error$state";
        $file  = "erros/$class.php";
        if(!$this->LoadResourceFile($file, false)) return "";
        if(!class_exists($class, false)) return "";
        $function  = "f$erro";
        $error_obj = new $class();
        return (method_exists($error_obj, $function))?$error_obj->$function($msg):$error_obj->getMessage($msg);
    }
    
    public function importDataFromArray($dados, $tabela, $callback = null, $insertIgnore = false, $callbackArgs = array()){
        if(empty($dados)){
            \classes\Utils\Log::save('log_import_data_from_array', "dados vazios $tabela");
            $this->setAlertMessage("Dados a serem importados estão vazios");
            return true;
        }
        $dados  = array_values($dados);
        $page   = -1; 
        $max    = (int)count($dados);
        $len    = 2000;
        $ttpags = ceil($max/$len);
        
        $fn     = $this->callback();
        $fn_data= $this->getData($tabela, $dados[0]);
        $keys   = array_keys($fn_data);
        $cols   = implode("`,`", $keys);
        $update = $this->getOnUpdateStatement($keys);
        
        $cbk_data = array('dados' =>$callbackArgs, 'total' => count($keys));
        while($ttpags > $page++){
            $init   = $page*$len;
            $val    = array_slice($dados, $init, $len);
            if($callback !== null){
                array_walk($val,$callback, $cbk_data);
            }
            array_walk($val,$fn, array('dados'=>$fn_data, 'total' => count($keys)));
            if(empty($val)){continue;}
            $values = implode(",\n", $val);
            $SQL = ($insertIgnore)?
                    "insert ignore into $tabela  (`$cols`) values \n $values \n":
                    "insert into $tabela  (`$cols`) values \n $values \n on duplicate key update $update";
            $query = "SET foreign_key_checks = 0; $SQL; SET foreign_key_checks = 1;";
            if(false === $this->ExecuteInsertionQuery($query)){
                \classes\Utils\Log::save('logs/importDataFromArray', $this->getMessages());
                return false;
            }
        }
        return true;
    }
    
    private function getOnUpdateStatement($keys){
        $out = array();
        foreach($keys as &$k){
            $out[] = "`$k`=COALESCE( VALUES(`$k`), `$k`)";
        }
        return implode(",", $out);
    }
    
    private function callback(){
        return function(&$array, $key, $user_data){
            $dados = $user_data['dados'];
            $count = $user_data['total'];
            if(empty($dados) || empty($array)){
                $array = '';
                return;
            }
            $temp  = array();
            foreach($dados as $k => $validation){
                if(!isset($array[$k]) || trim($array[$k]) === ""){
                    if(trim($validation['tipo']) == 'timestamp'){
                        $temp[$k] = "CURRENT_TIMESTAMP";
                        continue;
                    }
                    if($validation['extra'] !== ""){
                        if(strstr($validation['extra'],'auto_increment')  || stristr($validation['extra'],'timestamp')){
                            $temp[$k] = "NULL";
                        }
                        continue;
                    }
                    $temp[$k] = (true === $validation['is_nullable'])?"NULL":"''";
                    continue;
                }
                
                $array[$k] = str_replace(array("'"), array('"'), $array[$k]);
                if(is_numeric($array[$k])){
                      $temp[$k] = $array[$k];
                }else{$temp[$k] = "'{$array[$k]}'";}
            }
            if($count !== count($temp)){
                die("falha catastrófica: ImportDataFromArray número incorreto de keys!($count - ".count($temp).")");
            }
            $array = ("(". implode(",", $temp).")");
        };
    }
    
    private function getData($table, $data){    
        $arr2 = $this->getCols($table);
        $out  = array();
        foreach($arr2 as $key => $col){
            if(!array_key_exists($key, $data)){continue;}
            $out[$key] = $col; 
        }
        return $out;
    }
    
    private function getCols($table){
        $dbname = $this->conn->getDbName();
        $sql = " 
            SELECT COLUMN_NAME as coluna, IS_NULLABLE as is_nullable, EXTRA as extra, DATA_TYPE as tipo
            FROM `information_schema`.`COLUMNS` 
            WHERE 
                TABLE_SCHEMA='$dbname' AND
                TABLE_NAME='$table'
            ";
        $arr = $this->ExecuteQuery($sql);
        if(empty($arr)){return $arr;}
        $out = array();
        foreach($arr as $a){
            $a['is_nullable'] = ($a['is_nullable'] === "NO")?false:true;
            $out[$a['coluna']] = $a;
        }
        return $out;
    }

}