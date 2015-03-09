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


$dadosCliente = sprintf("<cliente><cpf_cgc>%s</cpf_cgc></cliente>", $cliente['cliente_cpf']);

// url de ws
$clienteC = new nusoap_client($ws);
$clienteC->useHTTPPersistentConnection();

// serial do cliente
$serial_number_cliente = readSerialNumber();

// monta os parametros a serem enviados
$paramsCliente = array(
    'crypt' => $serial_number_cliente,
    'dados' => $dadosCliente
);

// realiza a chamada de um metodo do ws passando os paramentros
$resultado = $clienteC->call("listar", $paramsCliente);

// remove acentos dos dados
$resultado = removerAcentos($resultado);

$resul = XML2Array::createArray($resultado);

// grava log
$log->addLog(ACAO_RETORNO, "dadosCliente", $resul);

//verifica se encontrou cliente com CPF informado. Se existe, retorna informação.
if ($resul["resultado"]["sucesso"] == true && isset($resul["resultado"]["dados"]["cliente"])) {
  
  $wsstatus = 0;
  $wsresult["wserror"] = "J&aacute; existe cliente cadastrado para este CPF/CNPJ!";
  
  // grava log
  $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

  returnWS($wscallback, $wsstatus, $wsresult);
  
}

/* variaveis de retorno do ws */
$wsstatus = 0;
$wsresult = array();

// url de ws
$client = new nusoap_client($ws);
$client->useHTTPPersistentConnection();

$cliente['cliente_nome'] = strtoupper($cliente['cliente_nome']);

// serial do cliente
$serail_number_cliente = readSerialNumber();

$dados = sprintf(
        "<cliente>"
        . "<nome_cliente>%s</nome_cliente>"
        . "<cpf_cgc>%s</cpf_cgc>"
        . "<ddd>%s</ddd>"
        . "<telefone>%s</telefone>"
        . "<email>%s</email>"
      . "</cliente>", 
        $cliente['cliente_nome'], $cliente['cliente_cpf'], $cliente['cliente_ddd'], 
        $cliente['cliente_telefone'], $cliente['cliente_email']);

// grava log
$log->addLog(ACAO_REQUISICAO, "saveCliente", $dados, SEPARADOR_INICIO);

// monta os parametros a serem enviados
$params = array(
    'crypt' => $serail_number_cliente,
    'dados' => $dados
);

// realiza a chamada de um metodo do ws passando os paramentros
$result = $client->call("atualizaClientePorCpf", $params);

// remove acentos dos dados
$result = removerAcentos($result);

$res = XML2Array::createArray($result);

// grava log
$log->addLog(ACAO_RETORNO, "dadosCliente", $result);

if ($res["resultado"]["sucesso"] && isset($res["resultado"]["dados"]["cliente"])) {
    $wsstatus = 1;
    $wsresult = $cliente;
} 

else {
    /* monta o xml de retorno */
    $wsstatus = 0;
    $wsresult["wserror"] = "N&atilde;o foi poss&iacute;vel cadastrar o cliente!";

    // grava log
    $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

    returnWS($wscallback, $wsstatus, $wsresult);
}

// grava log
$log->addLog(ACAO_RETORNO, $wscallback, $wsresult, SEPARADOR_FIM);

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>
