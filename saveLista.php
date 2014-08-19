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
$ws = sprintf("%s/listaws.php", $conf['SISTEMA']['saciWS']);
$storeno = $conf["MISC"]['loja'];
$pdvno = $conf["MISC"]['pdv'];
$dir_tmp = "tmp/";

/* variaveis recebidas na requisicao
 * {Array}: dados(wscallback, orcamento(codigo, funcionario, usuario, cliente, produtos(
 *                           codigo_produto, grade_produto, quantidade, ambiente)))
 */
$dados = $_REQUEST['dados'];
$wscallback = $dados['wscallback'];
$lista = $dados['lista'];
$file = $lista["file"];

//$file = "teste";

$content = file_get_contents($dir_tmp . $file);
//unlink($dir_tmp . $file);
$lista = (array) json_decode($content);
$produtos = $lista['produtos'];

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
        <quantidade_vendida>%s</quantidade_vendida>
      </lista_produto>", $produto['produto_codigo'], $produto['produto_grade'], $produto['produto_qtty'], $produto['produto_qtty_vend']);
}

// monta o xml de atualizacao de pedido
$dados = sprintf("
  <dados>
    <codigo_cliente>%s</codigo_cliente>
    <tipo>%s</tipo>
    <data_evento>%s</data_evento>
    <nome_noivo>%s</nome_noivo>
    <pai_noivo>%s</pai_noivo>
    <mae_noivo>%s</mae_noivo>
    <cep_noivo>%s</cep_noivo>
    <telefone_noivo>%s</telefone_noivo>
    <endereco_noivo>%s</endereco_noivo>
    <bairro_noivo>%s</bairro_noivo>
    <cidade_noivo>%s</cidade_noivo>
    <estado_noivo>%s</estado_noivo>
    <observacoes>%s</observacoes>
    <outras_observacoes>%s</outras_observacoes>
    %s
  </dados>", $lista['cliente_codigo'], $lista['tipo'], $lista['data_evento'], 
        $lista['noivo_nome'], $lista['noivo_pai'], $lista['noivo_mae'], $lista['noivo_cep'], $lista['noivo_telefone'], 
        $lista['noivo_endereco'], $lista['noivo_bairro'], $lista['noivo_cidade'], $lista['noivo_estado'],
        $lista['observacoes'], $lista['outras_observacoes'], $produtos);

// grava log
$log->addLog(ACAO_REQUISICAO, "atualizaListaPorCodigoInterno", $dados, SEPARADOR_INICIO);

// monta os parametros a serem enviados
$params = array(
    'crypt' => $serail_number_cliente,
    'dados' => $dados
);

// realiza a chamada de um metodo do ws passando os paramentros
$result = $client->call('atualizaListaPorCodigoInterno', $params);
$result = removerAcentos($result);

$res = XML2Array::createArray($result);

// grava log
$log->addLog(ACAO_RETORNO, "dadosLista", $result);

if (isset($res['resultado']['dados']['lista'])) {
    $wsstatus = 1;
    $wsresult = array();
}

// grava log
$log->addLog(ACAO_RETORNO, $wscallback, $wsresult, SEPARADOR_FIM);

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>
