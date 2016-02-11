<?php

class databaseResource extends \classes\Interfaces\resource{
        
   /**
    * @uses Contém a instância do banco de dados
    */
    private static $instance = NULL;
    
    /**
    * Construtor da classe
    * @uses Carregar os arquivos necessários para o funcionamento do recurso
    * @throws DBException
    * @return retorna um objeto com a instância do banco de dados
    */
    private static $bd_server, $bd_name, $bd_user, $bd_password, $active_instance, $instances;
    public function __construct() {
        $this->dir = dirname(__FILE__);
        
        $this->LoadResourceFile("config/conection.php");
        $this->LoadResourceFile("config/config.php");
        $this->LoadResourceFile("classes/DBConectionInterface.php");
        $this->LoadResourceFile("classes/DBError.php");
        $this->LoadResourceFile("classes/DatabaseInterface.php");
    }
    
    /**
    * retorna a instância do banco de dados
    * @uses Faz a chamada do contrutor
    * @throws DBException
    * @return retorna um objeto com a instância do banco de dados
    */
    public static function getInstanceOf(){
        
        $class_name = __CLASS__;
        if(self::$active_instance != null) {
            return self::$active_instance;
        }
        if (!isset(self::$instance) || self::$instance == null) {
            $obj = new $class_name();
            self::$instance = $obj->load();
        }

        return self::$instance;
    }
    
    public static function set_bd_server($bd_server){
        self::$bd_server = $bd_server;
    }
    public static function set_bd_name($bd_name){
        self::$bd_name = $bd_name;
    }
    public static function set_bd_user($bd_user){
        self::$bd_user = $bd_user;
    }
    public static function set_bd_password($bd_password){
        self::$bd_password = $bd_password;
    }
    
    public static function CheckConnection($sgbd = "", $engine = '', $getError = false){
         try{
             $obj = self::OpenNewConnection($sgbd, $engine);
             return (is_object($obj));
         }catch (\classes\Exceptions\DBException $e){
             if($getError) return $e->getMessage();
             return false;
         }
    }
    
    public static function OpenNewConnection($sgbd = "", $engine = ''){
        $bd_server   = (self::$bd_server     == "")? bd_server:   self::$bd_server;
        $bd_name     = (self::$bd_name       == "")? bd_name:     self::$bd_name;
        $bd_user     = (self::$bd_user       == "")? bd_user:     self::$bd_user;
        $bd_password = (self::$bd_password   == "")? bd_password: self::$bd_password;
        self::$bd_server = self::$bd_name = self::$bd_user = self::$bd_password = "";
        $sgbd   = strtolower(($sgbd == "")? bd_sgbd  :$sgbd);
        $engine = ($engine == "")? bd_engine:$engine;
        $class  = ucfirst($sgbd) ."Engine";
        $file   = dirname(__FILE__) ."/sgbd/{$sgbd}/$class.php";
        require_once $file;
        
        $file   = dirname(__FILE__) . "/sgbd/{$sgbd}/conections/{$engine}Conection.php";
        require_once  $file;
        return new $class($bd_server, $bd_name, $bd_user, $bd_password);
    }
    
    public static function OpenOtherSGBD($sgbd = '', $engine = ''){
        $bd_server   = (self::$bd_server     == "")? bd_server:   self::$bd_server;
        $bd_name     = (self::$bd_name       == "")? bd_name:     self::$bd_name;
        $bd_user     = (self::$bd_user       == "")? bd_user:     self::$bd_user;
        $bd_password = (self::$bd_password   == "")? bd_password: self::$bd_password;
        self::$bd_server = self::$bd_name = self::$bd_user = self::$bd_password = "";
        self::LoadEngineFile($sgbd, $engine);
        $sgbd   = ($sgbd   == "")? bd_sgbd  :$sgbd;
        $class  = ucfirst($sgbd) ."Engine";
        self::$instances[$bd_server][$bd_name] = call_user_func($class ."::getInstance", $bd_server, $bd_name, $bd_user, $bd_password);
        return self::$instances[$bd_server][$bd_name];
    }
    
    public static function setActiveInstance($bd_server, $bd_name){
        if(!isset(self::$instances[$bd_server][$bd_name])){
            self::OpenOtherSGBD();
        }
        self::$active_instance = self::$instances[$bd_server][$bd_name];
    }
    
    public static function dropActiveInstance(){
        self::$active_instance = NULL;
    }
    
    private static function LoadEngineFile($sgbd = "", $engine = ""){
        $sgbd   = ($sgbd   == "")? bd_sgbd  :$sgbd;
        $engine = ($engine == "")? bd_engine:$engine;
        
        $class  = ucfirst($sgbd) ."Engine";
        $file   = dirname(__FILE__) ."/sgbd/{$sgbd}/$class.php";
        require_once $file;
        $file   = dirname(__FILE__) . "/sgbd/{$sgbd}/conections/{$engine}Conection.php";
        require_once  $file;
    }


    
   /**
    * @abstract Loader da classe
    * @uses Carregar os arquivos necessários para o funcionamento do recurso
    * @throws DBException
    * @return retorna um objeto com a instância do banco de dados
    */
    public function load(){
        if(self::$active_instance == null){
            self::$active_instance = $this->OpenOtherSGBD(bd_sgbd, bd_engine);
        }
        return self::$active_instance;
    }
}