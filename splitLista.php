<?php

define('WService_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
require_once WService_DIR . 'lib/define.inc.php';
require_once WService_DIR . 'lib/function.inc.php';
require_once WService_DIR . 'lib/nusoap/nusoap.php';
require_once WService_DIR . 'classes/XML2Array.class.php';
require_once WService_DIR . 'classes/Log.class.php';

/* LOG */
$log = new Log();


$dir_tmp = "tmp/";

/* variaveis recebidas na requisicao
 * {Array}: dados(wscallback, lista(codigo_cliente, tipo, data_evento, data_criacao, produtos(
 *                           produto_codigo, produto_grade, produto_qtty, produto_qtty_vend)))
 */
$dados = $_REQUEST['dados'];
$wscallback = $dados['wscallback'];
$lista = $dados['lista'];

//descomentar para teste

//if($_GET['teste'] == 1){
//$lista = array("inicio" => 1, "file" => "teste" , "cliente_codigo" => "28", "tipo" => "1", "data_evento" => "20140825", "data_criacao" => date("Ymd"),
//        "produtos" => array(0 => array("produto_codigo" => "13", "produto_grade" => "", 
//            "produto_qtty" => "5", "produto_qtty_vend" => "0"), 1 => array("produto_codigo" => "14", "produto_grade" => "", 
//            "produto_qtty" => "3", "produto_qtty_vend" => "0")));
//}
//
//if($_GET['teste'] == 2){
//    $lista = array("file" => "teste" ,"cliente_codigo" => "28", "tipo" => "1", "data_evento" => "20140825", "data_criacao" => date("Ymd"),
//        "produtos" => array(0 => array("produto_codigo" => "11", "produto_grade" => "", 
//            "produto_qtty" => "1", "produto_qtty_vend" => "0"), 1 => array("produto_codigo" => "12", "produto_grade" => "", 
//            "produto_qtty" => "4", "produto_qtty_vend" => "0")));
//}
//
//if($_GET['teste'] == 3){
//    $lista = array("file" => "teste" ,"cliente_codigo" => "28", "tipo" => "1", "data_evento" => "20140825", "data_criacao" => date("Ymd"),
//        "produtos" => array(0 => array("produto_codigo" => "10", "produto_grade" => "", 
//            "produto_qtty" => "10", "produto_qtty_vend" => "0")));
//}
//
//if($_GET['teste'] == 4){
//    $lista = array("file" => "teste" ,"cliente_codigo" => "28", "tipo" => "1", "data_evento" => "20140825", "data_criacao" => date("Ymd"),
//        "produtos" => array(0 => array("produto_codigo" => "1", "produto_grade" => "", 
//            "produto_qtty" => "10", "produto_qtty_vend" => "0"), 1 => array("produto_codigo" => "2", "produto_grade" => "", 
//            "produto_qtty" => "10", "produto_qtty_vend" => "0"), 2 => array("produto_codigo" => "3", "produto_grade" => "", 
//            "produto_qtty" => "10", "produto_qtty_vend" => "0"), 3 => array("produto_codigo" => "4", "produto_grade" => "", 
//            "produto_qtty" => "10", "produto_qtty_vend" => "0"), 4 => array("produto_codigo" => "5", "produto_grade" => "", 
//            "produto_qtty" => "10", "produto_qtty_vend" => "0"),5 => array("produto_codigo" => "6", "produto_grade" => "", 
//            "produto_qtty" => "10", "produto_qtty_vend" => "0"),6 => array("produto_codigo" => "7", "produto_grade" => "", 
//            "produto_qtty" => "10", "produto_qtty_vend" => "0"),7 => array("produto_codigo" => "8", "produto_grade" => "", 
//            "produto_qtty" => "10", "produto_qtty_vend" => "0"),8 => array("produto_codigo" => "9", "produto_grade" => "", 
//            "produto_qtty" => "10", "produto_qtty_vend" => "0")));
//}


if(!file_exists($dir_tmp))
  exec("mkdir " . $dir_tmp);

if(isset($lista["inicio"])){
  file_put_contents($dir_tmp . $lista["file"], json_encode($lista));
}

else{
  $content = file_get_contents($dir_tmp . $lista["file"]);
  $content = (array) json_decode($content);

  if(!is_array($content["produtos"]))
    $content["produtos"] = array();

  $prds = $lista["produtos"];

  $produtos = array_merge($content["produtos"], $prds);
  $content["produtos"] = $produtos;

  file_put_contents($dir_tmp . $lista["file"], json_encode($content));
}

$wsstatus = 1;
$wsresult = array();

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>
