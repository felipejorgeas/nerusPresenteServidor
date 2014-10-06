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
$ws = sprintf("%s/clientews.php", $conf['SISTEMA']['saciWS']);


$dados = $_REQUEST['dados'];
$wscallback = $dados['wscallback'];
$cliente = $dados['cliente'];

if (!empty($cliente['cliente'])) {
  if($cliente['searchType'] == 1)
    $dados = sprintf("<cliente><cpf_cgc>%s</cpf_cgc></cliente>", $cliente['cliente']);
  
  else
    $dados = sprintf("<cliente><nome_cliente>%%%s%%</nome_cliente></cliente>", $cliente['cliente']);
}

/* variaveis de retorno do ws */
$wsstatus = 0;
$wsresult = array();

// url de ws
$client = new nusoap_client($ws);
$client->useHTTPPersistentConnection();

// serial do cliente
$serail_number_cliente = readSerialNumber();

// grava log
$log->addLog(ACAO_REQUISICAO, "getCliente", $dados, SEPARADOR_INICIO);

// monta os parametros a serem enviados
$params = array(
    'crypt' => $serail_number_cliente,
    'dados' => $dados
);


// realiza a chamada de um metodo do ws passando os paramentros
$result = $client->call("listar", $params);

// remove acentos dos dados
$result = removerAcentos($result);

$res = XML2Array::createArray($result);

// grava log
$log->addLog(ACAO_RETORNO, "dadosCliente", $result);

if ($res["resultado"]["sucesso"] && isset($res["resultado"]["dados"]["cliente"])) {

  $clientes = array();

  if (key_exists("0", $res["resultado"]["dados"]["cliente"]))
    $clientes = $res["resultado"]["dados"]["cliente"];
  else
    $clientes[] = $res["resultado"]["dados"]["cliente"];

  $wsstatus = 1;
  $wsresult = array();

  usort($clientes, 'ordenarCliente');

  foreach ($clientes as $cliente) {
    /* dados do produto */
    $wsresult[] = array(
        "cliente_codigo" => $cliente["codigo_cliente"],
        "cliente_nome" => $cliente["nome_cliente"],
        "cliente_identidade" => $cliente["identidade"],
        "cliente_cpf" => $cliente["cpf_cgc"],
        "cliente_email" => $cliente["email"]
    );
  }
} else {
  /* monta o xml de retorno */
  $wsstatus = 0;
  $wsresult["wserror"] = "Nenhum cliente encontrado!";

  // grava log
  $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

  returnWS($wscallback, $wsstatus, $wsresult);
}

// grava log
$log->addLog(ACAO_RETORNO, $wscallback, $wsresult, SEPARADOR_FIM);

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>
