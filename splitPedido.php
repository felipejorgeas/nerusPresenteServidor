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
 * {Array}: dados(
 *            wscallback,
 *            file,
 *            inicio, 
 *            cabecalho(
 *              funcionarioCodigo, 
 *              usuarioCodigo, 
 *              clienteCodigo, 
 *              tipoListaCodigo, 
 *              dataEvento
 *            ), 
 *            produtos(
 *              produtoCodigo, 
 *              produtoGrade, 
 *              produtoQuantidade
 *            )
 *          )
 */
$dados = $_REQUEST['dados'];
$wscallback = $dados['wscallback'];
$cabecalho = $dados['cabecalho'];
$produtos = $dados['produtos'];

if(!file_exists($dir_tmp))
  exec("mkdir " . $dir_tmp);

if(isset($dados["inicio"])){
  file_put_contents($dir_tmp . $dados["file"], json_encode($cabecalho));
}

else{
  $content = file_get_contents($dir_tmp . $dados["file"]);
  $content = (array) json_decode($content);

  if(!is_array($content["produtos"]))
    $content["produtos"] = array();

  $prds = $produtos;

  $produtos = array_merge($content["produtos"], $prds);
  $content["produtos"] = $produtos;

  file_put_contents($dir_tmp . $dados["file"], json_encode($content));
}

$wsstatus = 1;
$wsresult = array();

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>
