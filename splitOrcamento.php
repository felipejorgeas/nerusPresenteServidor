<?php

define('WService_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
require_once WService_DIR . 'lib/define.inc.php';
require_once WService_DIR . 'lib/function.inc.php';
require_once WService_DIR . 'lib/nusoap/nusoap.php';
require_once WService_DIR . 'classes/XML2Array.class.php';
require_once WService_DIR . 'classes/Log.class.php';

/* LOG */
$log = new Log();

/* obtendo algumas configuracoes do sistema */
$conf = getConfig();
$ws = sprintf("%s/pedidows.php", $conf['SISTEMA']['saciWS']);
$storeno = $conf["MISC"]['loja'];
$pdvno = $conf["MISC"]['pdv'];
$dir_tmp = "tmp/";

/* variaveis recebidas na requisicao
 * {Array}: dados(wscallback, orcamento(codigo, funcionario, usuario, cliente, produtos(
 *                           codigo_produto, grade_produto, quantidade, loja, ambiente)))
 */
$dados = $_REQUEST['dados'];
$wscallback = $dados['wscallback'];
$orcamento = $dados['orcamento'];

if(!file_exists($dir_tmp))
  exec("mkdir " . $dir_tmp);

if(isset($orcamento["inicio"])){
  file_put_contents($dir_tmp . $orcamento["file"], json_encode($orcamento));
}

else{
  $content = file_get_contents($dir_tmp . $orcamento["file"]);
  $content = (array) json_decode($content);

  if(!is_array($content["produtos"]))
    $content["produtos"] = array();

  $prds = $orcamento["produtos"];

  $produtos = array_merge($content["produtos"], $prds);
  $content["produtos"] = $produtos;

  file_put_contents($dir_tmp . $orcamento["file"], json_encode($content));
}

$wsstatus = 1;
$wsresult = array();

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>
