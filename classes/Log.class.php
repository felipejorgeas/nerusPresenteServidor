<?php

require_once dirname(__FILE__) . DIRECTORY_SEPARATOR . "../lib/function.inc.php";

define("SEPARADOR_INICIO", 1);
define("SEPARADOR_FIM", 2);
define("ACAO_REQUISICAO", "REQUISICAO");
define("ACAO_RETORNO", "RETORNO");

class Log {

  private $sep = "===========================================================================";
  private $log = null;
  private $useLog = false;
  public $conf = null;
  public $dir_log = null;
  public $filename = null;

  function __construct() {
    $this->conf = getConfig();
    $this->dir_log = trim($this->conf["SISTEMA"]["dirLog"]);
    if(!empty($this->dir_log)){
      $this->useLog = true;
      if(!file_exists($this->dir_log))
        exec("mkdir " . $this->dir_log);
      $this->filename = sprintf("%s/saciPresente_%s", $this->dir_log, date("Ymd"));
    }
  }

  function createLog(){
    $this->log = fopen($this->filename, "w");
    $this->closeLog();
  }

  function openLog(){
    if(!file_exists($this->filename)){
      $this->createLog();
    }
    $this->log = fopen($this->filename, "a");
  }

  function closeLog(){
    fclose($this->log);
    $this->log = null;
  }

  function addLog($type, $method, $info, $sep=false){
    if($this->useLog){
      $this->openLog();
      $content = sprintf("%s%s= %s", chr(10), ($sep == SEPARADOR_INICIO ? $this->sep . chr(10) : ""), date("d/m/Y H:i:s"));
      $content .= sprintf(" | %s: %s%s", $type, $method, chr(10));
      $content .= sprintf("%s%s%s%s", chr(10), print_r($info, 1), ($sep == SEPARADOR_FIM ? chr(10) . $this->sep : ""), chr(10));
      fprintf($this->log, $content);
      $this->closeLog();
    }
  }
}
?>