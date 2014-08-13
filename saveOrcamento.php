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
 *                           codigo_produto, grade_produto, quantidade, ambiente)))
 */
$dados = $_REQUEST['dados'];
$wscallback = $dados['wscallback'];
$orcamento = $dados['orcamento'];
$file = $orcamento["file"];

$content = file_get_contents($dir_tmp . $file);
unlink($dir_tmp . $file);
$orcamento = (array) json_decode($content);
$produtos = $orcamento['produtos'];

// converte cada posicao object para array
foreach($produtos as &$prd)
  $prd = (array) $prd;

/* variaveis de retorno do ws */
$wsstatus = 0;
$wsresult = array();

// valores default
$cliente = 0;
$valor_desconto = 0;
$valor_total = 0;
$transportadora = 0;
$valor_frete = 0;
$bloqueado_sep = 0;
$tipo_frete = 0;
$endereco_entrega = 0;
$observacao = "";

// obtem a data atual
$data = date("Ymd");
$data_full = date("d/m/Y - H:i") . "hs";

// obtem o codigo do funcionario que criou o orcamento
$funcionario = $orcamento['funcionario'];

// obtem o codigo do usuario que criou o orcamento
$usuario = $orcamento['usuario'];

// obtem o numero de orcamento caso seja para atualizar
$update = $orcamento['codigo'];

// obtem o cliente pra guardar nas observacoes
$observacao = removerAcentos(utf8_decode($orcamento['cliente']));
$observacao = ($observacao == "CLIENTE") ? "" : $observacao;

// url de ws
$client = new nusoap_client($ws);
$client->useHTTPPersistentConnection();

// serial do cliente
$serail_number_cliente = readSerialNumber();

$codigos = array();

// obtem todos os produto que vieram no xml
if (key_exists('0', $produtos))
  $codigos = $produtos;
else
  $codigos[] = $produtos;

$produtos = "";

// verifica se ira atualizar algum orcamento e busca todos os seus dados
if ($update > 0) {
  $dados = sprintf("
    <dados>
      <codigo_pedido>%s</codigo_pedido>
    </dados>",
    $update);

  // grava log
  $log->addLog(ACAO_REQUISICAO, "getPedido", $dados, SEPARADOR_INICIO);

  // monta os parametros a serem enviados
  $params = array(
      'crypt' => $serail_number_cliente,
      'dados' => $dados
  );

  // realiza a chamada de um metodo do ws passando os paramentros
  $result = $client->call('listar', $params);
  $res = XML2Array::createArray($result);

  // grava log
  $log->addLog(ACAO_RETORNO, "dadosPedido", $result);

  $produtos_existentes = array();
  if (isset($res['resultado']['dados']['pedido'])) {

    // verifica se o pedido eh realmente um orcamento
    if ($res['resultado']['dados']['pedido']['situacao'] != EORDSTATUS_ORCAMENTO) {
      /* monta o xml de retorno */
      $wsstatus = 0;
      $wsresult['wserror'] = "O pedido informado n&atilde;o &eacute; um or&ccedil;amento!";

      // grava log
      $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

      returnWS($wscallback, $wsstatus, $wsresult);
    }

    // verifica se o criador do orcamento eh o mesmo que esta tentando atualizar
    if ($res['resultado']['dados']['pedido']['codigo_usuario'] != $usuario) {
      /* monta o xml de retorno */
      $wsstatus = 0;
      $wsresult['wserror'] = "O or&ccedil;amento informado foi criado por outro usu&aacute;rio! Apenas ele tem acesso.";

      // grava log
      $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

      returnWS($wscallback, $wsstatus, $wsresult);
    }

    // obtem os dados do orcamento a ser atualizado
    $data = $res['resultado']['dados']['pedido']['data_pedido'];
    $cliente = $res['resultado']['dados']['pedido']['codigo_cliente'];
    $funcionario = $res['resultado']['dados']['pedido']['codigo_funcionario'];
    $storeno = $res['resultado']['dados']['pedido']['codigo_loja'];
    $pdvno = $res['resultado']['dados']['pedido']['codigo_pdv'];
    $valor_desconto = $res['resultado']['dados']['pedido']['valor_desconto'];
    $valor_total = $res['resultado']['dados']['pedido']['valor_total'];
    $transportadora = $res['resultado']['dados']['pedido']['codigo_transportadora'];
    $valor_frete = $res['resultado']['dados']['pedido']['valor_frete'];
    $bloqueado_sep = $res['resultado']['dados']['pedido']['bloqueado_separacao'];
    $tipo_frete = $res['resultado']['dados']['pedido']['tipo_frete'];
    $endereco_entrega = $res['resultado']['dados']['pedido']['codigo_endereco_entrega'];
    $obs = $res['resultado']['dados']['pedido']['observacao'];
    $observacao = empty($obs) ? $observacao : removerAcentos(utf8_decode($res['resultado']['dados']['pedido']['observacao']));

    // obtem todos os produtos ja existentes no orcamento
    if (key_exists('0', $res['resultado']['dados']['pedido']['lista_produtos']['produto']))
      $produtos_existentes = $res['resultado']['dados']['pedido']['lista_produtos']['produto'];
    else
      $produtos_existentes[] = $res['resultado']['dados']['pedido']['lista_produtos']['produto'];
  }

  else {
    /* monta o xml de retorno */
    $wsstatus = 0;
    $wsresult['wserror'] = "Or&ccedil;amento n&atilde;o encontrado!";

    // grava log
    $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

    returnWS($wscallback, $wsstatus, $wsresult);
  }

  /* mescla os produtos do xml com os ja existentes */
  $prds = array_merge($produtos_existentes, $codigos);

  /* mescla as quantidades de produtos duplicados */
  $prds = mergeProdutos($prds);

  // concatena cada produto ao xml de produtos
  foreach ($prds as $produto) {

    if (!($produto['codigo_produto'] > 0))
      continue;

    $produtos .= sprintf("
      <produto>
        <prdno>%s</prdno>
        <grade>%s</grade>
        <qtty>%s</qtty>
        <preco_unitario>%s</preco_unitario>
        <codigo_endereco_entrega>0</codigo_endereco_entrega>
        <loja_retira>0</loja_retira>
        <ambiente>%d</ambiente>
      </produto>",
      $produto['codigo_produto'], $produto['grade_produto'], $produto['quantidade'], $produto['preco'], $produto['ambiente']);
  }
}

else {
  // concatena cada produto ao xml de produtos
  foreach ($codigos as $produto) {
    $produtos .= sprintf("
      <produto>
        <prdno>%s</prdno>
        <grade>%s</grade>
        <qtty>%s</qtty>
        <preco_unitario>%s</preco_unitario>
        <codigo_endereco_entrega>0</codigo_endereco_entrega>
        <loja_retira>0</loja_retira>
        <ambiente>%d</ambiente>
      </produto>",
      $produto['codigo_produto'], $produto['grade_produto'], $produto['quantidade'], $produto['preco'], $produto['ambiente']);
  }
}

// ajustando a quantidade de caracteres do campo de observacao
$observacao = str_pad($observacao, 480, " ", STR_PAD_RIGHT);

// monta o xml de atualizacao de pedido
$dados = sprintf("
  <dados>
    <codigo_loja>%s</codigo_loja>
    <codigo_pedido>%s</codigo_pedido>
    <codigo_pedido_web>0</codigo_pedido_web>
    <data_pedido>%s</data_pedido>
    <codigo_pdv>%s</codigo_pdv>
    <codigo_funcionario>%s</codigo_funcionario>
    <codigo_cliente>%s</codigo_cliente>
    <valor_desconto>%s</valor_desconto>
    <valor_total>%s</valor_total>
    <situacao>%s</situacao>
    <codigo_endereco_entrega>%s</codigo_endereco_entrega>
    <codigo_transportadora>%s</codigo_transportadora>
    <bloqueado_separacao>%s</bloqueado_separacao>
    <valor_frete>%s</valor_frete>
    <tipo_frete>%s</tipo_frete>
    <observacao>%s</observacao>
    <codigo_usuario>%s</codigo_usuario>
    %s
  </dados>",
  $storeno, ($update > 0 ? $update : 0), $data, $pdvno, $funcionario, $cliente, $valor_desconto,
  $valor_total, EORDSTATUS_ORCAMENTO, $endereco_entrega, $transportadora, $bloqueado_sep,
  $valor_frete, $tipo_frete, $observacao, $usuario, $produtos);

// grava log
$log->addLog(ACAO_REQUISICAO, "atualizaPedidoPorCodigoInterno", $dados, (!($update > 0) ? SEPARADOR_INICIO : false));

// monta os parametros a serem enviados
$params = array(
    'crypt' => $serail_number_cliente,
    'dados' => $dados
);

// realiza a chamada de um metodo do ws passando os paramentros
$result = $client->call('atualizaPedidoPorCodigoInterno', $params);
$res = XML2Array::createArray($result);

// grava log
$log->addLog(ACAO_RETORNO, "dadosPedido", $result);

if (isset($res['resultado']['dados']['pedido'])) {
  $ordno = $res['resultado']['dados']['pedido']['codigo_pedido'];

  $wsstatus = 1;
  $wsresult['update'] = $update > 0 ? 1 : 0;
  $wsresult['codigo'] = $ordno;
  $wsresult['data'] = $data_full;
  $wsresult['cliente'] = trim(substr($observacao, 0, 40));
}

// grava log
$log->addLog(ACAO_RETORNO, $wscallback, $wsresult, SEPARADOR_FIM);

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>
