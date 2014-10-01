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
 * {Array}: dados(
 *            wscallback, 
 *            file
 *          )
 */
$dados = $_REQUEST['dados'];
$wscallback = $dados['wscallback'];
$file = $dados["file"];

$content = file_get_contents($dir_tmp . $file);
unlink($dir_tmp . $file);

$pedido = (array) json_decode($content);
$produtos = $pedido['produtos'];

// converte cada posicao object para array
foreach ($produtos as &$prd)
  $prd = (array) $prd;

// url de ws
$client = new nusoap_client($ws);
$client->useHTTPPersistentConnection();

// serial do cliente
$serail_number_cliente = readSerialNumber();

$prds = array();

// obtem todos os produto que vieram no xml
if (key_exists('0', $produtos))
  $prds = $produtos;
else
  $prds[] = $produtos;

$produtos = "";

// concatena cada produto ao xml de produtos
foreach ($prds as $produto) {
  $produtos .= sprintf("
      <produto>
        <prdno>%s</prdno>
        <grade>%s</grade>
        <qtty>%d</qtty>
      </produto>", $produto['produtoCodigo'], $produto['produtoGrade'], $produto['produtoQuantidade']);
}

// monta o xml de atualizacao de pedido
$dados = sprintf("
  <dados>
    <codigo_loja>%d</codigo_loja>
    <codigo_pdv>%d</codigo_pdv>
    <codigo_pedido>%d</codigo_pedido>    
    <data_pedido>%s</data_pedido>    
    <codigo_funcionario>%d</codigo_funcionario>
    <codigo_cliente>%d</codigo_cliente>    
    <situacao>%d</situacao>    
    <lista>%d</lista>
    <tipo_lista>%d</tipo_lista>
    %s
  </dados>", 1, 1, 0, date("Ymd"), 1, $pedido['clienteCodigo'], 1, 1, $pedido['tipoListaCodigo'], $produtos);

// grava log
$log->addLog(ACAO_REQUISICAO, "atualizaPedidoPorCodigoInterno", $dados, SEPARADOR_INICIO);

// monta os parametros a serem enviados
$params = array(
    'crypt' => $serail_number_cliente,
    'dados' => $dados
);

// realiza a chamada de um metodo do ws passando os paramentros
$result = $client->call('atualizaPedidoPorCodigoInterno', $params);
$result = removerAcentos($result);

$res = XML2Array::createArray($result);

// grava log
$log->addLog(ACAO_RETORNO, "dadosLista", $result);

if (isset($res['resultado']['dados']['pedido'])) {
  $wsstatus = 1;
  $wsresult = array();
}

// grava log
$log->addLog(ACAO_RETORNO, $wscallback, $wsresult, SEPARADOR_FIM);

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>
