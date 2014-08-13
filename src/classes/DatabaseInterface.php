<?php

interface DatabaseInterface{
    
    public function Insert($tabela, array $dados);

    public function getJoin();
    
    public function setJoin($join);
    
    public function Join($tabela_fonte, $tabela_dst, $chaves_fonte = array(), $chaves_dst = array(), $juncao = "NATURAL");
    
    public function Read($tabela, $campos = NULL, $where = NULL, $limit = NULL, $offset = NULL, $orderby = NULL);

    public function Update($tabela, array $dados, $where);

    public function Delete($tabela, $where);

    public function ExecuteQuery($bd_query);
    
    public function ExecuteInsertionQuery($bd_query);
    
    public function StartTransation();
    
    public function StopTransation();
    
    public function getSentenca();
    
    public function getQuery();
    
    public function resetQuery();
}
?>
