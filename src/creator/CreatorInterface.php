<?php

interface CreatorInterface{
    
    public function createTable($tabela);
    
    public function closeTable($tabela);
    
    /*
     * name = str
     * type = str
     * pkey = bool
     * notnull = bool
     * ai   = bool
     * keys = array
     * size = (str)
     * default = str
     * index = bool
     * unique = bool
     */
    public function addRow($tabela, $name, $type, $pkey, $notnull, $ai, $keys, $size, $default, $index, $unique);
        
    public function getFkey($table_src, $table_dst, $name, $coluna, $cardinalidade, $onupdate = "", $ondelete = "");
    
    public function destroyPlugin($plugin);

    public function setPlugin($plugin);
    
    
}

?>
