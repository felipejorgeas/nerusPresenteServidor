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
      <lista_produto>
        <codigo_produto>%s</codigo_produto>
        <grade>%s</grade>
        <quantidade_listada>%s</quantidade_listada>
      </lista_produto>", $produto['produtoCodigo'], $produto['produtoGrade'], $produto['produtoQuantidade']);
}

// monta o xml de atualizacao de pedido
$dados = sprintf("
  <dados>
    <codigo_cliente>%s</codigo_cliente>
    <tipo>%s</tipo>
    <data_evento>%s</data_evento>
    %s
  </dados>", $pedido['clienteCodigo'], $pedido['tipoListaCodigo'], $pedido['dataEvento'], $produtos);

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
