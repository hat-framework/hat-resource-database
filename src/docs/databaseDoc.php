<?php

class databaseDoc extends docs{
            //&lt;?php //do something  ?&gt;
    public function getDocumentacao(){
         $this->Title('
            <h2>Esta classe foi implementada usando o padrão de projeto Façade (Fachada), ou seja, ela esconde
            do sistema o que é realmente implementado. </h2>');
        
         $this->Paragraph('O Recurso database deve prover acesso à vários SGBDs diferentes e simultaneamente, 
             se necessário.
            Ao carregar o recurso ele automaticamente instancia a classe do banco de dados definido como padrão no 
            arquivo database/config/config.php');
        
         $this->Paragraph('Para carregar um outro sgbd faça o seguinte:');
         $this->phpCode('$this->db = databaseResource::OpenOtherSGBD($sgbd, $engine);');
         $this->Paragraph('Do contrário será aberta a mesma conexão padrão');
         return $this->flush();
    }
    
    public function getComoUsar(){
        $this->phpCode('$this->LoadResource("database", "db");
                $this->db->inserir($post);');
        return $this->flush();
    }
    
    public function getComoExtender(){
        return $this->flush();
    }
    
    public function getExemplo(){
        return $this->flush();
    }
    
    public function getMetodos(){
        $metodos = array(
            'Nome'       => '',
            'Descricao'  => '',
            'Argumentos' => ''
        );
        return $this->flush();
    }
    
}
?>
