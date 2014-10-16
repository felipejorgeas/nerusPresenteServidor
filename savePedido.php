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

/* variaveis de retorno do ws */
$wsstatus = 0;
$wsresult = array();

// valores default
$data_expira = 0;
$paymno = 0;
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

// obtem o codigo do funcionario que criou o orcamento
$funcionario = $pedido['funcionarioCodigo'];

// obtem o codigo do usuario que criou o orcamento
$usuario = $pedido['usuarioCodigo'];

// obtem o numero de orcamento caso seja para atualizar
$update = $pedido['pedidoCodigo'];

// url de ws
$client = new nusoap_client($ws);
$client->useHTTPPersistentConnection();

// serial do cliente
$serail_number_cliente = readSerialNumber();

$produtos_novos = array();

foreach($produtos as $pd){
  $p = array(
    "codigo_produto" => $pd["produtoCodigo"],
    "grade_produto" => $pd["produtoGrade"],
    "quantidade" => $pd["produtoQuantidade"],
    "ambiente" => $pd["produtoAmbiente"]
  );
  $produtos_novos[] = $p;
}

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
    $data_expira = $res['resultado']['dados']['pedido']['data_expira'];
    $paymno = $res['resultado']['dados']['pedido']['paymno'];
    $valor_desconto = $res['resultado']['dados']['pedido']['valor_desconto'];
    $valor_total = $res['resultado']['dados']['pedido']['valor_total'];
    $transportadora = $res['resultado']['dados']['pedido']['codigo_transportadora'];
    $valor_frete = $res['resultado']['dados']['pedido']['valor_frete'];
    $bloqueado_sep = $res['resultado']['dados']['pedido']['bloqueado_separacao'];
    $tipo_frete = $res['resultado']['dados']['pedido']['tipo_frete'];
    $endereco_entrega = $res['resultado']['dados']['pedido']['codigo_endereco_entrega'];
    $obs = $res['resultado']['dados']['pedido']['observacao'];
    $observacao = empty($obs) ? $observacao : removerAcentos(utf8_decode($obs));
    
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
  $prds = array_merge($produtos_existentes, $produtos_novos);

  /* mescla as quantidades de produtos duplicados */
  $prds = mergeProdutosPedido($prds);
  
  // concatena cada produto ao xml de produtos
  foreach ($prds as $produto) {

    if (!($produto['codigo_produto'] > 0))
      continue;

    $produtos .= sprintf("
      <produto>
        <prdno>%s</prdno>
        <grade>%s</grade>
        <qtty>%s</qtty>
        <preco_unitario>0</preco_unitario>
        <codigo_endereco_entrega>0</codigo_endereco_entrega>
        <loja_retira>0</loja_retira>
        <ambiente>0</ambiente>
      </produto>",
      $produto['codigo_produto'], $produto['grade_produto'], $produto['quantidade']);
  }
}

else {
  // concatena cada produto ao xml de produtos
  foreach ($produtos_novos as $produto) {
    $produtos .= sprintf("
      <produto>
        <prdno>%s</prdno>
        <grade>%s</grade>
        <qtty>%s</qtty>
        <preco_unitario>0</preco_unitario>
        <codigo_endereco_entrega>0</codigo_endereco_entrega>
        <loja_retira>0</loja_retira>
        <ambiente>0</ambiente>
      </produto>",
      $produto['codigo_produto'], $produto['grade_produto'], $produto['quantidade']);
  }
}

// ajustando a quantidade de caracteres do campo de observacao
$observacao = str_pad($observacao, 480, " ", STR_PAD_RIGHT);

// monta o xml de atualizacao de pedido
$dados = sprintf("
  <dados>
    <codigo_loja>%s</codigo_loja>
    <codigo_pdv>%s</codigo_pdv>
    <codigo_pedido>%s</codigo_pedido>
    <data_pedido>%s</data_pedido>
    <codigo_funcionario>%s</codigo_funcionario>
    <codigo_usuario>%s</codigo_usuario>
    <codigo_cliente>%s</codigo_cliente>
    <situacao>%s</situacao>
    <data_expira>%s</data_expira>
    <paymno>%s</paymno>
    <valor_desconto>%s</valor_desconto>
    <valor_total>%s</valor_total>
    <codigo_transportadora>%s</codigo_transportadora>
    <valor_frete>%s</valor_frete>
    <bloqueado_separacao>%s</bloqueado_separacao>
    <tipo_frete>%s</tipo_frete>
    <codigo_endereco_entrega>%s</codigo_endereco_entrega>    
    <lista>%d</lista>
    <codigo_cliente_lista>%d</codigo_cliente_lista>
    <tipo_lista>%d</tipo_lista>
    <observacao>%s</observacao>
    %s
  </dados>", 
  $pedido['lojaCodigo'], $pedido['pdvCodigo'], $pedido['pedidoCodigo'], $data, 
  $funcionario, $usuario, $pedido["clienteCodigo"], 
  EORDSTATUS_ORCAMENTO, $data_expira, $paymno, $valor_desconto, $valor_total,
  $transportadora, $valor_frete, $bloqueado_sep, $tipo_frete, $endereco_entrega,
  EORD_RELACIONADO_LISTA_DE_PRESENTE, $pedido['clienteListaCodigo'], $pedido['tipoListaCodigo'],
  $observacao, $produtos);

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
$log->addLog(ACAO_RETORNO, "dadosPedido", $result);

if (isset($res['resultado']['dados']['pedido'])) {
  $wsstatus = 1;
  $wsresult = $res['resultado']['dados']['pedido'];
}

// grava log
$log->addLog(ACAO_RETORNO, $wscallback, $wsresult, SEPARADOR_FIM);

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>
