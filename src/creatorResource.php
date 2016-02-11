<?php

class creatorResource extends \classes\Interfaces\resource{
        
   /**
    * @uses Contém a instância do banco de dados
    */
    private static $instance = NULL;
    private $fkeys = array();
    private $fks = "";
    private $str = "";
    //guarda os nomes dos plugins com dependência externa
    private $dext  = array();

    //intens ja instalados
    private $inst  = array();
    
   /**
    * retorna a instância do banco de dados
    * @uses Faz a chamada do contrutor
    * @throws DBException
    * @return retorna um objeto com a instância do banco de dados
    */
    public static function getInstanceOf(){
        $class_name = __CLASS__;
        if (!isset(self::$instance)) {self::$instance = new $class_name;}
        return self::$instance;
    }
    
    /**
    * Construtor da classe
    * @uses Carregar os arquivos necessários para o funcionamento do recurso
    * @throws DBException
    * @return retorna um objeto com a instância do banco de dados
    */
   
    private $dbcreator = NULL;
    public function __construct() {
        $this->LoadResource("database" , 'db');
        $this->dir = dirname(__FILE__);
        $this->LoadResourceFile("creator/CreatorInterface.php");
        $this->dbcreator = $this->load();
    }
    
            /**
            * @abstract Loader da classe
            * @uses Carregar os arquivos necessários para o funcionamento do recurso
            * @throws DBException
            * @return retorna um objeto com a instância do banco de dados
            */
            public function load($db = bd_sgbd){
                return $this->CreatorFromOtherSGBD($db);
            }
            
                    public function CreatorFromOtherSGBD($sgbd){
                        $class  = ucfirst($sgbd) ."Creator";
                        $file   = dirname(__FILE__) ."/sgbd/{$sgbd}/$class.php";
                        require_once $file;
                        //die($class);
                        return call_user_func($class ."::getInstanceOf");
                    }
    
    
    
    /**
     * 
    * @abstract Reset Creator Variables
     * @autor Thom <thom@hat-framework.com>
     */
    public function reset(){
        $this->fkeys = array();
        $this->str   = '';
        $this->fks   = '';
    }
    
    private $logname = "";
    public function setLogName($logname){
        $this->logname = $logname;
    }
    
    private function add2Log($msg, $error = true) {
        if($error){$this->appendErrorMessage($msg);}
        die("$this->logname - $msg");
        if($this->logname !== ""){\classes\Utils\Log::save($this->logname, $msg);}
        return false;
    }
    
    public function install($plugin, $in_action= array(), $caninstall = 1, $canexecute = true){
        $str = $fks = "";
        if($this->install_isCalled($plugin, $in_action)){return true;}
        if($this->exec($plugin) === false){return false;}
        if(false === $this->install_initStrings($fks, $str)){return false;}
        if(false === $this->install_cannotInstall($caninstall, $str, $fks)){return true;}
        if(false !== $this->install_getStr($str, $fks, $canexecute)){return $str;}
        $bool = $this->install_execute($str);
        return $this->install_checkErrors($bool, $str, $fks);
    }
    
            private function install_isCalled($plugin, $in_action){
                static $plugins = array();
                if(array_key_exists($plugin, $plugins)) {return true;}
                $plugins[$plugin] = '';
                $this->plugin     = $plugin;
                $this->inst[]     = $plugin;
                $this->inst       = $in_action;
                return false;
            }
            
            private function install_initStrings(&$fks, &$str){
                foreach($this->dext as $plug){
                   $obj = new creatorResource();
                   if(!$obj->install($plug, $this->inst, 0)){
                       $this->setErrorMessage($obj->getErrorMessage());
                       return false;
                   }
                   $fks .= $obj->getFkeys();
                   $str .= $obj->getQuerys();
               }
            }
            
            private function install_cannotInstall($caninstall, $str, $fks){
                if($caninstall){return true;}
                $this->str = $str . "". $this->str;

                //se não tem nada novo a ser instalado não criará as relações
                if(($this->str == "") || empty ($this->str)){
                    $this->fks = "";
                    return false;
                }
                $this->fks = $fks . "". $this->fks;
                return false;
            }
            
            private function install_getStr(&$str, &$fks, $canexecute){
                $str .= $this->str;
                $fks .= $this->fks;
                $str = $str . $fks;
                if(($str == "") || empty ($str)) {return true;}
                if(!$canexecute) {return $str;}
                return false;
            }
            
            private function install_execute($str){
                //$this->debug($str);
                $bool = $this->execute($str);
                if(!empty ($this->warning)){
                    $msg = implode("<br/>", $this->warning);
                    $this->setAlertMessage($msg);
                }
                return $bool;
            }
            
            private function install_checkErrors($bool, $str, $fks){
                if(!$bool){
                    if($str == $fks){
                        $this->setAlertMessage("Não foi possível criar as relações de chaves estrangeiras, 
                            mas as tabelas foram criadas com sucesso! <br/> $fks");
                    }else{
                        $this->setErrorMessage($str);
                        //$this->debug($str);
                        //$this->unstall($plugin);
                    }
                }
                $this->reset();
                return $bool;
            }

    public function exec($plugin){
        
        $subplugins = $this->getPlugin($plugin);
        if($subplugins === false) {return false;}
        
        $str = "";
        $this->dbcreator->setPlugin($plugin);
        foreach($subplugins as $splug){
            $this->processSubPluginDados($splug, $str);
        }
        return $this->finalise($str);
    }
    
            public function getPlugin($plugin){
               if(trim($plugin) === "") {return $this->add2Log("Selecione um plugin válido");}

               $file = \classes\Classes\Registered::getPluginLocation($plugin,true);
               if(!file_exists($file)){return $this->add2Log("plugin $plugin não existe");}

               $subplugins = $this->LoadResource("files/dir", "objdir")->getPastas($file);
               if(empty ($subplugins)){return $this->add2Log("Não existem subplugins neste plugin");}

               return $this->findSubpluginsModels($subplugins, $file, $plugin);
            }
            
                    private function findSubpluginsModels($subplugins, $file, $plugin){
                        $out    = array();
                        foreach($subplugins as $splug){
                           $models = $this->objdir->getArquivos(\classes\Classes\Registered::getPluginLocation($plugin,true)."/$splug/classes/");
                           foreach($models as $model){
                              $model = str_replace("Model.php", "", $model);
                              $class = "{$model}Model";
                              $arq   = "$file/$splug/classes/$class.php";
                              if(!file_exists($arq)) {continue;}
                              $md               = ($model == $splug)? "$plugin/$splug": "$plugin/$splug/$model";
                              $out[$splug][$md] = $md;
                           }
                        }
                        return $out;
                    }
                    
            private function processSubPluginDados($splug, &$str){
                foreach($splug as $model){
                    //echo "($model - ";
                    $this->LoadModel($model, 'mobj', false);
                    if(!is_object($this->mobj)){continue;}
                    if(!method_exists($this->mobj, "getTable")) {
                        $this->addWarning("Model $model não possui método getTable<br/>");
                        continue;
                    }
                    if(!method_exists($this->mobj, "getDados")) {
                        $this->addWarning("Model $model não possui método getDados<br/>");
                        continue;
                    }
                    $tabela = $this->mobj->getTable();
                    $dados  = $this->mobj->getDados();
                    if($tabela == "" || $dados == "" || empty ($dados)) {
                        $this->addWarning("Model $model não possui dados<br/>");
                        continue;
                    }
                    $str .=  $this->createTable($model, $tabela, $dados);
                }
            }
            
                    private function addWarning($msg){
                        if(DEBUG){$this->warning[] = $msg;}
                    }
                    
            private function finalise($str){
                //$this->debug($str);
                $action = "install";
                $this->str = $str;
                $this->fks = $this->genFkeys($action);
                return true;
            }

    public function getFkeys(){
        return $this->fks;
    }

    public function getQuerys(){
        return $this->str;
    }

    public function unstall($plugin){
       $subplugins = $this->getPlugin($plugin);
       if($subplugins === false) {return false;}

       $str = $this->dbcreator->destroyPlugin($plugin);
       if($str == ""){
           $this->setErrorMessage('Classe: A string de Desinstalação do arquivo está vazia<br/> Método'.__METHOD__. " <br/>Linha: ".__LINE__ . "<br/>");
           return true;
       }
       return $this->execute($str);
    }

    public function update($plugin){
        $this->plugin = $plugin;
        $subplugins   = array_keys($this->getPlugin($plugin));
        $str          = "";
        foreach($subplugins as $model){
            $this->processLine($str, $plugin, $model);
        }
        $fkeys = $this->dbcreator->destroyFkeys($plugin, true);
        return $this->getResult("$fkeys $str");
    }
    
            private function processLine(&$str, $plugin, $model){
                $modelname = "$plugin/$model";
                $this->LoadModel($modelname, 'tmp_model', false);
                if(!is_object($this->tmp_model)) {return false;}
                if(!method_exists($this->tmp_model, "getDados")){return false;}

                $tabela = $this->getTable($modelname);
                if($tabela === false){return false;}
                
                $dados = $this->prepareDados($modelname, $tabela, $model);
                if($dados === false){return false;}
                
                $tmp = $this->dbcreator->updateSubPlugin($tabela, $dados);
                if(trim($tmp) === ""){return false;}
                $str .= $tmp;
                return true;
            }
            
                    private function getTable($modelname){
                        $tabela = $this->tmp_model->getTable();
                        if($tabela === ""){
                            $this->appendAlertMessage("O model $modelname não possui uma tabela!");
                            return false;
                        }
                        return $tabela;
                    }
            
                    private function prepareDados($modelname, $tabela, $model){
                        $dados = $this->tmp_model->getDados();
                        if(empty($dados)){
                            $this->appendAlertMessage("O model $modelname não possui dados!");
                            return false;
                        }

                        foreach($dados as $name => $arr){
                            if(array_key_exists('fkey', $arr)){
                                $this->foreign($name, $tabela, $arr, $model);
                            }
                        }
                        return $dados;
                    }
                    
            private function getResult($str){
                $bool  = true;
                $fkeys = $this->genFkeys();
                if(trim($str)   !== ""){$bool = $bool and $this->executeSentenca($str, false);}
                if(trim($fkeys) !== ""){$bool = $bool and $this->executeSentenca($fkeys, false);}
                return $bool;
            }
    
                    private function executeSentenca($sql){
                        if(trim($sql) === ""){return true;}
                        if(false !== $this->execute($sql  , false)){
                            //$this->db->printSentenca();
                            return true;
                        }
                        $sentencas = explode(";", $sql);
                        if(empty($sentencas)){return true;}
                        return $this->individualTry($sentencas);
                    }
                    
                            private function individualTry($sentencas){
                                $tentativas = 0;
                                $total      = count($sentencas);
                                foreach($sentencas as $sentenca){
                                    if(trim($sentenca) == ""){continue;}
                                    if(false == $this->execute($sentenca, false)){
                                        $tentativas++;
                                        $this->add2Log("Falha ao executar a sentença $sentenca");
                                    }
                                }
                                
                                if($tentativas == 0){return true;}
                                $msg = "Falha ao executar dados no sql! Total de sentenças: $total. Total de tentativas $tentativas";
                                $this->add2Log($msg);
                                return $this->appendErrorMessage($msg);
                            }
    private function execute($str, $show_empty_error = true){
       if(false === $this->checkString($show_empty_error, $str)){return false;}
       $this->db->setErrorMessage("");
       $this->db->ExecuteInsertionQuery($str);
       return $this->processError($str);
    }
    
            private function checkString($show_empty_error, $str){
                if(trim($str) != ""){return true;}
                if(!$show_empty_error){return true;}
                return $this->setErrorMessage("Caro usuário, a string de criação do banco de dados está vazia. Método: ".__METHOD__);
            }
            
            private function processError($str){
                $erro = trim($this->db->getErrorMessage());
                if($erro != ""){
                     $str .= "<h5>$erro</h5>";
                     $str .= "<p>".$this->getAlertMessage()."</p>";
                     $this->warning[] = $str;
                     $this->debug($str); 
                     $this->add2Log($str, false);
                     return false;
                }
                return true;
            }
    
    private function createTable($model, $tabela, $dados){
        if($tabela == "") {return;}
        $str   = $this->dbcreator->createTable($tabela);
        $lines = array();
        foreach($dados as $name => $arr){
            if(array_key_exists('fkey', $arr)) {
                $var = $this->foreign($name, $tabela, $arr, $model);
                if($var == "") {continue;}
                $arr = $var;
            }
            $temp = $this->addRow($tabela, $name, $arr);
            if(trim($temp) == ""){continue;}
            $lines[] = $temp;
        }
        $lines[] = $this->dbcreator->closeTable($tabela);
        $str .= implode(", ", $lines);
        return $str;
    }
    
    private function foreign($name, $tabela, $arr, $modelname){
        
        //verifica se é chave estrangeira
        if(!array_key_exists('fkey', $arr) || !isset($arr['fkey']['model'])) {return;}
        $model = $arr['fkey']['model'];
        $dependencia = explode("/", $model);
        $dependencia = array_shift($dependencia);
        if($dependencia != $this->plugin && !in_array($dependencia, $this->dext) && trim($dependencia) !== ""){
            $this->dext[] = $dependencia;
        }
        
        //verifica a cardinalidade
        $card = $arr['fkey']['cardinalidade'];
        if($card == "n1" || $card == "nn") return;

        //salva o nome da chave estrangeira para gerar a referencia depois
        $arr['fkey']['tabela'] = $tabela;
        $this->fkeys[$tabela][$name] = $arr['fkey'];
  
        //recupera o tipo e o tamanho da chave para qual o modelo aponta
        $model = $arr['fkey']['model'];
        $this->LoadModel($model, 'tmp_model', false);
        if(!is_object($this->tmp_model)) return;
        if(!array_key_exists('keys', $arr['fkey'])) {
            die("O model $modelname não possui as referências de chaves estrangeiras");
        }
        
        $tmp_name = $arr['fkey']['keys'][0];
        $dados = $this->tmp_model->getDados();
        if(!array_key_exists($tmp_name, $dados)) {return;}
        if(!array_key_exists('type', $dados[$tmp_name])) {return;}
        $arr['type'] = $dados[$tmp_name]['type'];
        if(array_key_exists('size', $dados[$tmp_name])) $arr['size'] = $dados[$tmp_name]['size'];  
        $t2 = $this->tmp_model->getTable();

        return $arr;
    }
    
    private function addRow($tabela, $name, $arr){
        
        if(!array_key_exists("type", $arr)) return;
        $name = strtolower($name);
        $keys = $size = $pkey = $default = $ai = $notnull = $index = $unique = "";
        
        $type = $arr['type'];
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
        
        return $this->dbcreator->addRow($tabela, $name, $type, $pkey, $notnull, $ai, $keys, $size, $default, $index, $unique);
    }
    
    private function genFkeys(){
        if(empty($this->fkeys)) {return;}
        $var = "";
        //foreach($this->fkeys as $table => $fks){
        foreach($this->fkeys as $fks){
            $this->processTableFkeys($fks, $var);
        }
        return $var;
    }
    
            private function processTableFkeys($fks, &$var){
                foreach($fks as $name => $fk){
                    $model  = $fk['model'];
                    $coluna = array_shift($fk['keys']);
                    $card   = $fk['cardinalidade'];
                    $table_src  = $fk['tabela'];
                    $this->LoadModel($model, 'temp_model', false);
                    if(!is_object($this->temp_model)){continue;}
                    $table_dst = $this->temp_model->getTable();
                    $onupdate  = isset($fk['onupdate'])? $fk['onupdate']:"";
                    $ondelete  = isset($fk['ondelete'])? $fk['ondelete']:"";
                    if($table_src == "" || $table_dst == "" || $name == "" || $coluna == "" || $card == ""){
                        die("erro ao gerar chave estrangeira: Valores vazios");
                    }
                    $temp = $this->dbcreator->getFkey($table_src, $table_dst, $name, $coluna, $card, $onupdate, $ondelete);
                    //$this->db->ExecuteInsertionQuery($temp);
                    $var .= $temp;
                }
            }
    
    private function debug($str){
        $log = "";
        $var = explode(";", $str);
        array_pop($var);
        foreach($var as $v){
            $this->genDebugLine($v, $log);
        }
        $log .= "<hr/>";
        \classes\Utils\Log::save('plugins/erro', $log);
    }
    
            private function genDebugLine(&$v, &$log){
                $p     = explode("(", $v);
                $prim  = $p[0];
                $u     = explode(")", $v);
                $ult   = end($u);

                $v     = str_replace(array("$prim(", ")$ult"), "", $v);
                $linha = explode(", ", $v);
                $virg = "";

                $log .= "$prim (";
                foreach($linha as $l){
                    if($l != ""){
                        $log .= "$virg <br/>$l";
                    }
                    $virg = ",";
                }
                $log .= ")<br/>$ult;<br/><br/> ";
            }
}