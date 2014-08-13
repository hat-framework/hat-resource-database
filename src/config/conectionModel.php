<?php

class conectionModel extends configModel{

    public function selecionar(){
        echo "to aki";
        $file = dirname(__FILE__). "/conection.php";
        parent::select($file);
    }
}

?>
