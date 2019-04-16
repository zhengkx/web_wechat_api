<?php

class mysqlClass 
{
    public $host    = '39.108.82.144:3307';
    public $db      = 'beijing';
    public $db_user = 'beijing';
    public $db_pwd  = 'Beijing00';
    public $objMysql;

    public function __construct() 
    {
        $this->objMysql = new mysqli($this->host, $this->db_user, $this->db_pwd, $this->db);

        if ($this->objMysql->connect_error) {
            die("could not connect to the database:\n" . mysql_error());//诊断连接错误
        }
    }

    public function querySelect()
    {
        $sql = "select * from wechat_messages;";

        $res = $this->objMysql->query($sql);

        return $res;
    }

    public function queryInsert($sql)
    {
        // $res = $this->objMysql->multi_query($sql);

        // if (!$res) {
        //     die($this->objMysql->connect_error); //诊断连接错误
        // }

        // return $res;
    }
}
