<?php

class reimportResource extends \classes\Interfaces\resource{
    
    public function __construct(){
        parent:: __contruct();
        $this->folder = DIR_FILES."export". DS;
        $this->LoadResource('files/curl' , 'curl');
        $this->LoadResource('files/unzip', 'unzip');
        $this->LoadResource('files/dir'  , 'dobj');
        $this->LoadResource('files/file' , 'fobj');
    }
    
    public function reimportDataFromPlugin($plugin, $url){
        $folder = "$this->folder/$plugin";
        getTrueDir($folder);
        if(false === $this->downloadFile($folder, $url)){return false;}
        if(false === $this->unzipp("$folder.zip")){return false;}
        if(false === $this->reimport($folder)){return false;}
        $this->removeFiles($folder);
        return $this->setSuccessMessage("Dados Importados Corretamente!");
    }
    
    /**
    * Faz o download dos arquivos a serem importados
    * @return boolean
    * @author Thom
    */
   private function downloadFile($file, $url){
       $this->removeFiles($file);
       if(!$this->curl->downloadFile($url, "$file.zip")){
           $this->setMessages($this->model->getMessages());
           return false;
       }
       return true;
   }
   
   private function unzipp($zipfile){
       return $this->propagateMessage($this->unzip, 'extractFile', $zipfile);
   }
    
   private function reimport($folder){
       getTrueDir($folder);
       
       $bool    = true;
       $arqs    = $this->dobj->getArquivos($folder);
       if(!empty($arqs)){
           $bool = $bool and $this->import($folder, $arqs);
       }
       
       $folders = $this->dobj->getPastas($folder);
       foreach($folders as $f){
           $bool = $bool and $this->reimport("$folder/$f");
       }
       return $bool;
   }
   
   private function import($folder, $arquivos){
       $bool = true;
       foreach($arquivos as $arquivo){
           $file = "$folder/$arquivo";
           getTrueDir($file);
           if(!file_exists($file)){continue;}
           $e = explode(DS,$file);
           array_pop($e);
           $subplugin = array_pop($e);
           $plugin    = array_pop($e);
           $model     = "$plugin/$subplugin";
           $obj       = $this->LoadModel($model, 'model', false);
           if(!is_object($obj) || !method_exists($obj, 'importDataFromArray')){continue;}
           
           $bool      = $bool and $this->propagateMessage($obj, 'importDataFromArray', json_decode($this->fobj->GetFileContent($file), true));
       }
       return $bool;
   }
   
   private function removeFiles($file){
       if(file_exists($file)){$this->dobj->removeFile($file);}
       if(file_exists("$file.zip")){$this->dobj->removeFile("$file.zip");}
   }
   
}