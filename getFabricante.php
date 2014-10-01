<?php

define("WService_DIR", dirname(__FILE__) . DIRECTORY_SEPARATOR);
require_once WService_DIR . "lib/define.inc.php";
require_once WService_DIR . "lib/function.inc.php";
require_once WService_DIR . "lib/nusoap/nusoap.php";
require_once WService_DIR . "classes/XML2Array.class.php";
require_once WService_DIR . "classes/Log.class.php";

/* LOG */
$log = new Log();

/* obtendo algumas configuracoes do sistema */
$conf = getConfig();
$ws = sprintf("%s/fabricantews.php", $conf["SISTEMA"]["saciWS"]);
 
/* loja padrao */
//$loja = $conf["MISC"]["loja"];

/* variaveis recebidas na requisicao
 * {Array}: dados(wscallback, produto(codigo))
 */
$dados = $_REQUEST["dados"];
$wscallback = $dados["wscallback"];
$fabricante = $dados["fabricante"];

/* variaveis de retorno do ws */
$wsstatus = 0;
$wsresult = array();

// url de ws
$client = new nusoap_client($ws);
$client->useHTTPPersistentConnection();

// serial do cliente
$serail_number_cliente = readSerialNumber();

$dados = sprintf("<fabricante>\n\t<codigo_fabricante>%s</codigo_fabricante><nome_fabricante>%%%s%%</nome_fabricante><nome_fantasia>%%%s%%</nome_fantasia>\n</fabricante>", 
        $fabricante["codigo_fabricante"], $fabricante["nome_fabricante"], $fabricante["nome_fantasia"]);

// grava log
$log->addLog(ACAO_REQUISICAO, "getFabricante", $dados, SEPARADOR_INICIO);

// monta os parametros a serem enviados
$params = array(
    "crypt" => $serail_number_cliente,
    "dados" => $dados
);

// realiza a chamada de um metodo do ws passando os paramentros
$result = $client->call("listar", $params);

// grava log
$log->addLog(ACAO_RETORNO, "dadosFabricante", $result);

// converte o xml em um array
$res = XML2Array::createArray($result);

if ($res["resultado"]["sucesso"] && isset($res["resultado"]["dados"]["fabricante"])) {

  $fabricantes = array();

  if(key_exists("0", $res["resultado"]["dados"]["fabricante"]))
    $fabricantes = $res["resultado"]["dados"]["fabricante"];
  else
    $fabricantes[] = $res["resultado"]["dados"]["fabricante"];

  $wsstatus = 1;
  $wsresult = array();

  foreach($fabricantes as $fabricante){
    /* dados do tipo */
    $wsresult[] = $fabricante;
  }
}

else{
  /* monta o xml de retorno */
  $wsstatus = 0;
  $wsresult["wserror"] = "Nenhum fabricante encontrado!";

  // grava log
  $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

  returnWS($wscallback, $wsstatus, $wsresult);
}

// grava log
$log->addLog(ACAO_RETORNO, $wscallback, $wsresult, SEPARADOR_FIM);

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>
