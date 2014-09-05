<?php

class exportResource extends \classes\Interfaces\resource{
    
    public function __construct(){
        parent:: __contruct();
        $this->folder = DIR_FILES."export". DS;
        $this->LoadResource('files/dir', 'dobj');
        $this->LoadResource('files/file', 'fobj');
    }
    
    
    public function exportDataFromPlugin($plugin){
        $path = classes\Classes\Registered::getPluginLocation($plugin, true);
        $folders = $this->dobj->getPastas($path);
        $bool = true;
        foreach($folders as $f){
            $obj = $this->getObj($path, $plugin, $f);
            if($obj === false){continue;}
            $where = isset($this->where[$f])?$this->where[$f]:"";
            $bool = $bool and $this->setFilename("$plugin/$f")
                 ->exportPagination($obj, $where);
        }
        if($bool){$this->setSuccessMessage("Exportação do plugin $plugin concluída com sucesso!");}
        if($this->download){
            $this->LoadResource('files/zip', 'zip')->downloadZipDir("$this->folder$plugin", true);
        }
        $this->reset();
        return $bool;
    }
    
    private $pagesize = 1000;
    private function exportPagination($obj, $where){
        $total = $obj->getCount($where);
        if($total === 0 || $total === "0"){return true;}
        $totalpages = ceil($total/$this->pagesize);
        //echo ("$totalpages - $total <br/>");
        $i = 0;
        $bool = true;
        $arr = array();
        do{
            $offset = ($this->pagesize*$i);
            $arr = $obj->selecionar(array(), $where, $this->pagesize, $offset);
            //echoBr($obj->db->getSentenca());
            if(empty($arr)){break;}
            $i++;
            //continue;
            $filename = "$this->filename/f$i.json";
            getTrueDir($filename);
            $bool = $bool and $this->fobj->savefile($filename, json_encode($arr, JSON_NUMERIC_CHECK), 0777);
            if(false === $bool){
                $this->appendErrorMessage($this->fobj->getMessages(true));
            }
        }while($i <= $totalpages);
        return $bool;
    }
    
    private function getObj($path, $plugin, $subplugin){
        if(in_array($subplugin, array(".git", 'Config', "tests"))){return false;}
        $file = $path.DS.$subplugin.DS."classes".DS."{$subplugin}Model.php";
        if(!file_exists($file)){return false;}
        $this->LoadModel("$plugin/$subplugin", 'obj', false);
        if($this->obj === null){return false;}
        return $this->obj;
    }
    
    
    private $where = array();
    public function setWhere($where){
        $this->where = $where;
        return $this;
    }
    
    public function reset(){
        $this->where    = "";
        $this->filename = "";
        $this->download = false;
    }
    
    private function setFilename($filename){
        $this->filename = $this->folder . $filename;
        getTrueDir($this->filename);
        return $this;
    }
    
    private $download = false;
    public function enableDownload(){
        $this->download = true;
        return $this;
    }
}