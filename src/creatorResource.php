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
    * Construtor da classe
    * @uses Carregar os arquivos necessários para o funcionamento do recurso
    * @throws DBException
    * @return retorna um objeto com a instância do banco de dados
    */
   
    private $obj = NULL;
    public function __construct() {
        $this->LoadResource("database" , 'db');
        $this->dir = dirname(__FILE__);
        $this->LoadResourceFile("creator/CreatorInterface.php");
        $this->obj = $this->load();
    }
    
    /**
    * retorna a instância do banco de dados
    * @uses Faz a chamada do contrutor
    * @throws DBException
    * @return retorna um objeto com a instância do banco de dados
    */
    public static function getInstanceOf(){
        $class_name = __CLASS__;
        if (!isset(self::$instance)) self::$instance = new $class_name;
        return self::$instance;
    }
    
    public function CreatorFromOtherSGBD($sgbd){
        $class  = ucfirst($sgbd) ."Creator";
        $file   = dirname(__FILE__) ."/sgbd/{$sgbd}/$class.php";
        require_once $file;
        //die($class);
        return call_user_func($class ."::getInstanceOf");
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
        
    public function install($plugin, $in_action= array(), $caninstall = 1, $canexecute = true){

        static $plugins = array();
        if(array_key_exists($plugin, $plugins)) return true;
        $plugins[$plugin] = '';
        
        //se existe algum plugin sendo instalado que não seja o atual(remove dependência circular)
        $this->inst   = $in_action;
        $this->plugin = $plugin;
        $this->inst[] = $plugin;

        if($this->exec($plugin) === false){return false;}
        
        $str = $fks = "";
        foreach($this->dext as $plug){
           $obj = new creatorResource();
           if(!$obj->install($plug, $this->inst, 0)){
               $this->setErrorMessage($obj->getErrorMessage());
               return false;
           }
           $fks .= $obj->getFkeys();
           $str .= $obj->getQuerys();
       }

       if(!$caninstall){
           $this->str = $str . "". $this->str;
           
           //se não tem nada novo a ser instalado não criará as relações
           if(($this->str == "") || empty ($this->str)){
               $this->fks = "";
               return true;
           }
           $this->fks = $fks . "". $this->fks;
           return true;
       }

       $str .= $this->str;
       $fks .= $this->fks;
       $str = $str . $fks;
       if(($str == "") || empty ($str)) return true;
       if(!$canexecute) return $str;

       //$this->debug($str);
       $bool = $this->execute($str);
       if(!empty ($this->warning)){
           $msg = implode("<br/>", $this->warning);
           $this->setAlertMessage($msg);
       }

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

       return $bool;
    }

    public function exec($plugin){
        
        $subplugins = $this->getPlugin($plugin);
        if($subplugins === false) return false;
        $str = "";

        $this->obj->setPlugin($plugin);
        foreach($subplugins as $splug){
              foreach($splug as $model){
                  //echo "($model - ";
                  $this->LoadModel($model, 'mobj', false);
                  if(!is_object($this->mobj))continue;
                  if(!method_exists($this->mobj, "getTable")) {if(DEBUG)$this->warning[] = "Model $model não possui método getTable<br/>"; continue;}
                  if(!method_exists($this->mobj, "getDados")) {if(DEBUG)$this->warning[] = "Model $model não possui método getDados<br/>"; continue;}
                  $tabela = $this->mobj->getTable();
                  $dados  = $this->mobj->getDados();
                  if($tabela == "" || $dados == "" || empty ($dados)) {if(DEBUG)$this->warning[] = "Model $model não possui dados<br/>"; continue;}
                  //echo "$model, $tabela<br/>";
                  //echo "$model \n";
                  $str .=  $this->createTable($model, $tabela, $dados);
                  //echo "$model)<br/>";
              }
        }
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
       if($subplugins === false) return false;

       $str = $this->obj->destroyPlugin($plugin);
       if($str == ""){
           $this->setErrorMessage('Classe: A string de Desinstalação do arquivo está vazia<br/> Método'.__METHOD__. " <br/>Linha: ".__LINE__ . "<br/>");
           return true;
       }
       return $this->execute($str);
    }

    public function update($plugin){
       
        $this->plugin = $plugin;
        $subplugins   = $this->getPlugin($plugin);
        $str          = "";
        $this->obj->destroyFkeys($plugin);
        foreach($subplugins as $model => $d){
            $modelname = "$plugin/$model";
            $this->LoadModel($modelname, 'tmp_model', false);
            if(!is_object($this->tmp_model)) continue;
            if(!method_exists($this->tmp_model, "getDados")) continue;
            
            $dados = $this->tmp_model->getDados();
            $tabela = $this->tmp_model->getTable();
            if(!empty($dados)){
                foreach($dados as $name => $arr){
                    if(array_key_exists('fkey', $arr))
                        $this->foreign($name, $tabela, $arr, $model);
                }
            }
            $str .= $this->obj->updateSubPlugin($tabela, $dados);
        }
        $str .= $this->genFkeys();
        return $this->execute($str, false);
    }

    private function execute($str, $show_empty_error = true){

       if(trim($str) == ""){
            if($show_empty_error){
                $this->setErrorMessage("Caro usuário, a string de criação do banco de dados está vazia. Método: ".__METHOD__);
                return false;
            }
            return true;
       }
       $this->db->setErrorMessage("");
       $this->db->ExecuteInsertionQuery($str);
       $erro = trim($this->db->getErrorMessage());
       if($erro != ""){
            $str = "<h5>$erro</h5>";
            $str.= "<p>".$this->getAlertMessage()."</p>";
            $this->warning[] = $str;
            //$this->debug($str);
            return false;
       }
       return true;
       
    }
    
    public function getPlugin($plugin){
       if(trim($plugin) === "") {
           $this->setErrorMessage("Selecione um plugin válido");
           return false;
       }
       
       $file = MODULOS . $plugin;
       if(!file_exists($file)){
           $this->setErrorMessage("plugin $plugin não existe");
           return false;
       }
       
       $this->LoadResource("files/dir", "objdir");
       $subplugins = $this->objdir->getPastas($file);
       if(empty ($subplugins)){
           $this->setErrorMessage("Não existem subplugins neste plugin");
           return false;
       }
       
       $out    = array();
       foreach($subplugins as $splug){
          $models = $this->objdir->getArquivos(MODULOS."$plugin/$splug/classes/");
          
          foreach($models as $model){
             $model = str_replace("Model.php", "", $model);
             $class = "{$model}Model";
             $arq   = "$file/$splug/classes/$class.php";
             if(!file_exists($arq)) continue;
             $model = ($model == $splug)? "$plugin/$splug": "$plugin/$splug/$model";
             $out[$splug][$model] = $model;
          }
       }
       return $out;
    }
    
    private function createTable($model, $tabela, $dados){
        if($tabela == "") return;
        $str = $this->obj->createTable($tabela);
        foreach($dados as $name => $arr){
            if(array_key_exists('fkey', $arr)) {
                $var = $this->foreign($name, $tabela, $arr, $model);
                if($var == "") continue;
                $arr = $var;
            }
            $str .= $this->addRow($tabela, $name, $arr);
        }
        $str .= $this->obj->closeTable($tabela);
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
        
        return $this->obj->addRow($tabela, $name, $type, $pkey, $notnull, $ai, $keys, $size, $default, $index, $unique);
    }
    
    private function genFkeys(){
        if(empty($this->fkeys)) return;
        
        $var = "";
        foreach($this->fkeys as $table => $fks){
            foreach($fks as $name => $fk){
                $model  = $fk['model'];
                $coluna = array_shift($fk['keys']);
                $card   = $fk['cardinalidade'];
                $table_src  = $fk['tabela'];
                $this->LoadModel($model, 'temp_model', false);
                if(!is_object($this->temp_model))continue;
                $table_dst = $this->temp_model->getTable();
                $onupdate  = isset($fk['onupdate'])? $fk['onupdate']:"";
                $ondelete  = isset($fk['ondelete'])? $fk['ondelete']:"";
                if($table_src == "" || $table_dst == "" || $name == "" || $coluna == "" || $card == "")
                    die("erro ao gerar chave estrangeira: Valores vazios");
                $temp = $this->obj->getFkey($table_src, $table_dst, $name, $coluna, $card, $onupdate, $ondelete);
                //$this->db->ExecuteInsertionQuery($temp);
                $var .= $temp;
            }
        }
        return $var;
        
    }
    
    private function debug($str){
        //echo "<hr/>Sentença executada pelo banco de dados: <br/>".$this->db->getSentenca(). "<hr/>";
        //echo "Debug da Sentença: <br/>";
        $var = explode(";", $str);
        array_pop($var);
        foreach($var as $v){
            $prim  = explode("(", $v);
            $prim  = $prim[0];
            $ult   = explode(")", $v);
            $ult   = end($ult);

            $v     = str_replace(array("$prim(", ")$ult"), "", $v);
            $linha = explode(", ", $v);
            $virg = "";

            echo "$prim (";
            foreach($linha as $l){
                if($l != "")echo "$virg <br/>$l";
                $virg = ",";
            }
            echo ")<br/>$ult;<br/><br/> ";
        }
        echo "<hr/>";
        die();
    }
}