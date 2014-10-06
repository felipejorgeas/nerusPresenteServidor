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

$lista = (array) json_decode($content);
$produtos = $lista['produtos'];

// converte cada posicao object para array
foreach ($produtos as &$prd)
  $prd = (array) $prd;

/* variaveis de retorno do ws */
$wsstatus = 0;
$wsresult = array();

$nome_noivo = "";
$pai_noivo = "";
$mae_noivo = "";
$cep_noivo = "";
$telefone_noivo = "";
$endereco_noivo = "";
$bairro_noivo = "";
$cidade_noivo = "";
$estado_noivo = "";
$observacoes = "";
$outras_observacoes = "";

// obtem o codigo do funcionario que criou o orcamento
$funcionario = $lista['funcionarioCodigo'];

// obtem o codigo do usuario que criou o orcamento
$usuario = $lista['usuarioCodigo'];

// controle para atualizar ou nao
$update = $lista['update'];

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

$produtos_novos = array();

foreach($produtos as $pd){
  $p = array(
    "codigo_produto" => $pd["produtoCodigo"],
    "grade" => $pd["produtoGrade"],
    "quantidade_listada" => $pd["produtoQuantidade"],
    "quantidade_vendida" => 0
  );
  $produtos_novos[] = $p;
}

$produtos = "";

// verifica se ira atualizar alguma lista e busca todos os seus dados
if ($update > 0) {
  $dados = sprintf("
    <dados>
      <codigo_cliente>%s</codigo_cliente>
      <tipo>%s</tipo>
    </dados>",
    $lista['clienteCodigo'], $lista['tipoListaCodigo']);

  // grava log
  $log->addLog(ACAO_REQUISICAO, "getLista", $dados, SEPARADOR_INICIO);

  // monta os parametros a serem enviados
  $params = array(
      'crypt' => $serail_number_cliente,
      'dados' => $dados
  );

  // realiza a chamada de um metodo do ws passando os paramentros
  $result = $client->call('listar', $params);
  $res = XML2Array::createArray($result);

  // grava log
  $log->addLog(ACAO_RETORNO, "dadosLista", $result);

  $produtos_existentes = array();
  if (isset($res['resultado']['dados']['lista'])) {
    
    // verifica se o criador da lista eh o mesmo que esta tentando atualizar
    if ($res['resultado']['dados']['lista']['funcionario'] != $funcionario) {
      /* monta o xml de retorno */
      $wsstatus = 0;
      $wsresult['wserror'] = "A lista informada foi criada por outro funcion&aacute;rio! Apenas ele tem acesso.";

      // grava log
      $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

      returnWS($wscallback, $wsstatus, $wsresult);
    }

    // obtem os dados da lista a ser atualizada
    $nome_noivo = $res['resultado']['dados']['lista']['nome_noivo'];
    $pai_noivo = $res['resultado']['dados']['lista']['pai_noivo'];
    $mae_noivo = $res['resultado']['dados']['lista']['mae_noivo'];
    $cep_noivo = $res['resultado']['dados']['lista']['cep_noivo'];
    $telefone_noivo = $res['resultado']['dados']['lista']['telefone_noivo'];
    $endereco_noivo = $res['resultado']['dados']['lista']['endereco_noivo'];
    $bairro_noivo = $res['resultado']['dados']['lista']['bairro_noivo'];
    $cidade_noivo = $res['resultado']['dados']['lista']['cidade_noivo'];
    $estado_noivo = $res['resultado']['dados']['lista']['estado_noivo'];
    $observacoes = $res['resultado']['dados']['lista']['observacoes'];
    $outras_observacoes = $res['resultado']['dados']['lista']['outras_observacoes'];

    // obtem todos os produtos ja existentes no orcamento
    if (key_exists('0', $res['resultado']['dados']['lista']['lista_produtos']['produto']))
      $produtos_existentes = $res['resultado']['dados']['lista']['lista_produtos']['produto'];
    else
      $produtos_existentes[] = $res['resultado']['dados']['lista']['lista_produtos']['produto'];

  }

  else {
    /* monta o xml de retorno */
    $wsstatus = 0;
    $wsresult['wserror'] = "Pedido n&atilde;o encontrado!";

    // grava log
    $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

    returnWS($wscallback, $wsstatus, $wsresult);
  }
    
  /* mescla os produtos do xml com os ja existentes */
  $prds = array_merge($produtos_existentes, $produtos_novos);

  /* mescla as quantidades de produtos duplicados */
  $prds = mergeProdutosLista($prds);
    
  // concatena cada produto ao xml de produtos
  foreach ($prds as $produto) {

    if (!($produto['codigo_produto'] > 0))
      continue;

    $produtos .= sprintf("
      <lista_produto>
        <codigo_produto>%s</codigo_produto>
        <grade>%s</grade>
        <quantidade_listada>%s</quantidade_listada>
        <quantidade_vendida>%s</quantidade_vendida>
      </lista_produto>",
      $produto['codigo_produto'], $produto['grade'], 
      $produto['quantidade_listada'], $produto['quantidade_vendida']);
  }
}

else {
  // concatena cada produto ao xml de produtos
  foreach ($produtos_novos as $produto) {
    $produtos .= sprintf("
      <lista_produto>
        <codigo_produto>%s</codigo_produto>
        <grade>%s</grade>
        <quantidade_listada>%s</quantidade_listada>
        <quantidade_vendida>%s</quantidade_vendida>
      </lista_produto>",
      $produto['codigo_produto'], $produto['grade'], 
      $produto['quantidade_listada'], $produto['quantidade_vendida']);
  }
}

// ajustando a quantidade de caracteres do campo outras observacoes
$outras_observacoes = str_pad($outras_observacoes, 480, " ", STR_PAD_RIGHT);
    
// monta o xml de atualizacao de pedido
$dados = sprintf("
  <dados>
    <codigo_cliente>%s</codigo_cliente>
    <tipo>%s</tipo>
    <loja>%s</loja>
    <funcionario>%s</funcionario>
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
  </dados>", 
  $lista['clienteCodigo'], $lista['tipoListaCodigo'], $lista['lojaCodigo'], 
  $funcionario, $lista['dataEvento'], $nome_noivo, $pai_noivo, $mae_noivo, 
  $cep_noivo, $telefone_noivo, $endereco_noivo, $bairro_noivo, $cidade_noivo, 
  $estado_noivo, $observacoes, $outras_observacoes, $produtos);

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
