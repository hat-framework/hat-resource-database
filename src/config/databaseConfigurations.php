<?php
        
class databaseConfigurations extends \classes\Classes\Options{
                
    protected $files   = array(
        
        'database/config' => array(
            'title'        => 'Opções do Banco de dados',
            'descricao'    => 'Dados de conexão do banco de dados do site',
            'grupo'        => 'Configurações do Banco de dados',
            'type'         => 'resource', //config, plugin, jsplugin, template, resource
            'referencia'   => 'database/config',
            'visibilidade' => 'webmaster', //'usuario', 'admin', 'webmaster'
            'configs'      => array(
                
                'bd_debug' => array(
                    'name'          => 'bd_debug',
                    'label'         => 'Debug do Banco de Dados',
                    'type'          => 'enum',//varchar, text, enum
                    'options'       => "'true' => 'Sim', 'false' => 'Não'",
                    'default'       => 'false',
                    'description'   => 'Com esta opção marcada os detalhes da consulta são exibidos ao fazer
                        uma consulta que retorna um erro. Desabilite esta opção quando acabar a fase de testes!',
                    'value'         => 'true',
                    'value_default' => 'true'
                ),
                
                'bd_sgbd' => array(
                    'name'          => 'bd_sgbd',
                    'label'         => 'Sistema de banco de dados do site',
                    'type'          => 'enum',//varchar, text, enum
                    'options'       => "'mysql' => 'Mysql'",
                    'default'       => 'mysql',
                    'description'   => 'Atualmente apenas o mysql está disponível',
                    'value'         => 'mysql',
                    'value_default' => 'mysql'
                ),
                
                'bd_engine' => array(
                    'name'          => 'bd_engine',
                    'label'         => 'Sistema de banco de dados do site',
                    'type'          => 'enum',//varchar, text, enum
                    'options'       => "'PDO' => 'PDO', 'MySqli' => 'MySqli'",
                    'default'       => 'PDO',
                    'description'   => 'A opção Mysqli está em fase de testes ainda.',
                    'value'         => 'PDO',
                    'value_default' => 'PDO'
                ),
                
            ),
        ),
    );
}

?>