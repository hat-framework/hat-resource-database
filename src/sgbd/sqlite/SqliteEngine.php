<?php

class SqliteEngine extends \classes\Interfaces\resource implements DatabaseInterface {
    
    //informações sobre o banco de dados
    private $bd_name = bd_name;
    
    //guarda os joins que serao feitos
    private $join;
    
    //guarda a query que ainda será execultada
    private $query;
    
    //guarda a ultima sentença sql execultada
    private $sentenca;
    
    //seta se determinado comando é uma transação
    private $is_transaction = false;
    
    //guarda a instancia atual do banco de dados
    static private $instance;
    public function __construct(){
        
        $this->dir = dirname(__FILE__);
        $this->engine = 'sqlite';
        if(!$this->connect()){
            throw new DBException($this->getErrorMessage());
        }
        
    }

    public static function getInstanceOf(){
        
        $class_name = __CLASS__;
        if (!isset(self::$instance)) {
            self::$instance = new $class_name;
        }

        return self::$instance;
    }
    
    //conecta ao banco de dados
    private function connect(){
        
        try{
            $class = $this->engine . "Conection";
            $file  = dirname(__FILE__) . "/conections/$class.php";
            if(!file_exists($file)){
                throw new DBException("Classe ($class) não encontrada");
            }
            require_once $file;
            
            $this->conn = new $class();
            if(!$this->conn->connect()){
                throw new DBException("Não foi possíevel estabelecer comunicação com o
                    banco de dados.");
            }
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
            $campos[] = "$inds"; 
            if(strpos($vals, "FUNC_") === false) $valores[] = "'$vals'";
            else $valores[] = str_replace("FUNC_", '', $vals);
            
        }
        $campos =  implode(", ", array_values($campos));
        $valores = implode(", ", array_values($valores));

        $query = "INSERT INTO $table ( $campos ) VALUES( $valores )";
        $this->setQuery($query);
        return $this->execute($fetch = false);
    }
    
    public function setJoin($join){
        $this->join = $join;
    }

    public function getJoin(){
        return $this->join;
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
                $and = "AND";
            }
        }
        if($conditions != "") $conditions = "ON ($conditions)";
        $this->join .= "$side JOIN $table_dst $conditions";
        echo "funcao join não testada, não tenho certeza se funciona para o sqlite";
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

        $where   = ($where   != NULL)   ? "WHERE {$where}"       : "";
        $limit   = ($limit   != NULL)   ? "LIMIT {$limit}"       : "";
        $offset  = ($offset  != NULL)   ? "OFFSET {$offset}"     : "";
        $orderby = ($orderby != NULL)   ? "ORDER BY {$orderby}"  : "";
        $campos  = ($campos  != NULL)   ? implode(", ", $campos) : " * ";
        $query = (" SELECT  $campos FROM $table $this->join {$where} {$orderby} {$limit} {$offset}");
        $this->join = "";
        $this->setQuery($query);
        return $this->execute($fetch = true);
        
    }

    public function Update($table, array $dados, $where)
    {
            $campos = array();
            foreach($dados as $inds => $vals){
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

            $query = " UPDATE $table SET $campos WHERE $where";
            $this->setQuery($query);
            return $this->execute($fetch = false);

    }

    public function Delete($table, $where){
        
        if($where == NULL || $where == ""){
            $this->setErrorMessage(__CLASS__ . ": Cláusula where nao pode ser vazia");
        }
        $query = " DELETE FROM $table WHERE {$where}";
        $this->setQuery($query);
        return $this->execute($fetch = false);

    }

    public function ExecuteQuery($bd_query){
        $this->setQuery($bd_query);
        $var = $this->execute($fetch = true);
        return $var;
    }
    
    public function ExecuteInsertionQuery($bd_query){
        $this->setQuery($bd_query);
        return $this->execute($fetch = false);
    }
    
    public function StartTransation(){
        if(!$this->is_transaction){
            $this->setQuery(" START TRANSACTION ");
            $this->is_transaction = true;
        }
    }
    
    public function StopTransation(){
        $this->setQuery(" COMMIT ");
        $this->is_transaction = false;
        return $this->execute(false);
    }
    
    private function execute($fetch = true){
        try{
            if($this->is_transaction){
                if($fetch){
                    return array();
                }
                return true;
            }
            $query = $this->getQuery();
            $this->resetQuery();
            
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
    
    public function getQuery(){
        return $this->query;
    }
    
    public function resetQuery(){
        $this->query = "";
    }

    public function showTables(){
        return $this->ExecuteQuery("show tables");
    }
    
    public function dropTable($table){
        return $this->ExecuteQuery("drop table $table");
    }
    
    public function TableExists($table){
        $table = "SELECT * FROM sqlite_master WHERE type='table' AND name='$table';";
        return $this->ExecuteQuery($table);
    }

    private function setQuery($query){
        $query = str_replace("", '', $query);
        $this->query .= $query . ";";
        $this->sentenca = nl2br($query);
    }
    
    private function generateErrorMessage($e){

        if(DEBUG){
             echo "
                <hr/>
                <div class='error'>
                    <b>Mensagem de erro</b>: <br/>" . $e->getMessage()     . "<br/><br/>
                    <b>Código de erro</b>:   <br/>" . $e->getCode()        . "<br/><br/>
                    <b>Sentença sql</b>:     <br/>" . $this->getSentenca() . "<br/><br/>
                </div>
                <hr/>";
        }
        $msg   = $e->getMessage();
        $this->setErrorMessage($msg);
    }

}
?>
