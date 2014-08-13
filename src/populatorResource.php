<?php

class populatorResource extends \classes\Interfaces\resource{

    public function __construct() {
        $this->dir = dirname(__FILE__);
    }
    
    /**
    * retorna a instância do banco de dados
    * @uses Faz a chamada do contrutor
    * @throws DBException
    * @return retorna um objeto com a instância do banco de dados
    */
    private static $instance = NULL;
    public static function getInstanceOf(){
        
        $class_name = __CLASS__;
        if (!isset(self::$instance)) {
            self::$instance = new $class_name();
        }
        return self::$instance;
    }
    
    public function populate($plugin){
        $this->LoadResource('database/creator', 'cre');
        $subplugins = $this->cre->getPlugin($plugin);
        $this->obj = $this->cre->load();
        $this->setaStrings();
        if($subplugins === false) return false;
        foreach($subplugins as $splug){
              foreach($splug as $model){
                  $this->LoadModel($model, 'mobj');
                  if(!method_exists($this->mobj, "getTable")) continue;
                  if(!method_exists($this->mobj, "getDados")) continue;
                  $tabela = $this->mobj->getTable();
                  $dados  = $this->mobj->getDados();
                  if($tabela == "" || $dados == "" || empty ($dados)) continue;
                  $this->processa($model, $tabela, $dados);
              }
        }

        die();
    }
    
    private function processa($model, $tabela, $dados){

        $this->obj->populateRow($tabela);
        echo "<hr/>";
    }

    private function setaStrings(){
        require_once 'string.php';
        $str = string_getString();
        $this->paragrafos = explode("\n", $str);
        $this->palavras   = explode(" ", $str);
        $this->obj->setPalavras($this->palavras);
        $this->obj->setParagrafos($this->paragrafos);
    }
}
?>
