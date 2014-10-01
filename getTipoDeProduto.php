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
$ws = sprintf("%s/tipoDeProdutows.php", $conf["SISTEMA"]["saciWS"]);
 
/* loja padrao */
//$loja = $conf["MISC"]["loja"];


/* variaveis recebidas na requisicao
 * {Array}: dados(wscallback, produto(codigo))
 */
$dados = $_REQUEST["dados"];
$wscallback = $dados["wscallback"];
$tipoDeProduto = $dados["tipo_de_produto"];

//$tipoDeProduto["codigo"] = 0;
//$tipoDeProduto["nome"] = "";
//$wscallback = "wsResponseTipoDeProduto";

/* variaveis de retorno do ws */
$wsstatus = 0;
$wsresult = array();

// url de ws
$client = new nusoap_client($ws);
$client->useHTTPPersistentConnection();

// serial do cliente
$serail_number_cliente = readSerialNumber();

$dados = sprintf("<tipo_de_produto>\n\t<codigo>%s</codigo><nome>%s</nome>\n</tipo_de_produto>", $tiposDeProduto["codigo"], $tiposDeProduto["nome"]);

// grava log
$log->addLog(ACAO_REQUISICAO, "getTipoDeProduto", $dados, SEPARADOR_INICIO);

// monta os parametros a serem enviados
$params = array(
    "crypt" => $serail_number_cliente,
    "dados" => $dados
);

// realiza a chamada de um metodo do ws passando os paramentros
$result = $client->call("listar", $params);

// grava log
$log->addLog(ACAO_RETORNO, "dadosTipoDeProduto", $result);

// converte o xml em um array
$res = XML2Array::createArray($result);

if ($res["resultado"]["sucesso"] && isset($res["resultado"]["dados"]["tiposDeProduto"])) {

  $tipos = array();

  if(key_exists("0", $res["resultado"]["dados"]["tiposDeProduto"]))
    $tipos = $res["resultado"]["dados"]["tiposDeProduto"];
  else
    $tipos[] = $res["resultado"]["dados"]["tiposDeProduto"];

  $wsstatus = 1;
  $wsresult = array();

  foreach($tipos as $tipo){
    /* dados do tipo */
    $wsresult[] = array(
        "tipo_produto_codigo" => $tipo["codigo"],
        "tipo_produto_nome" => $tipo["nome"]
    );
  }
}

else{
  /* monta o xml de retorno */
  $wsstatus = 0;
  $wsresult["wserror"] = "Nenhum tipo de produto encontrado!";

  // grava log
  $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

  returnWS($wscallback, $wsstatus, $wsresult);
}

// grava log
$log->addLog(ACAO_RETORNO, $wscallback, $wsresult, SEPARADOR_FIM);

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>
