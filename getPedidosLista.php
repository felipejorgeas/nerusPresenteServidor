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
$wsPedidosLista = sprintf("%s/listaws.php", $conf["SISTEMA"]["saciWS"]);
$wsPedido = sprintf("%s/pedidows.php", $conf["SISTEMA"]["saciWS"]);
$wsCliente = sprintf("%s/clientews.php", $conf["SISTEMA"]["saciWS"]);
 
/* loja padrao */
//$loja = $conf["MISC"]["loja"];

/* variaveis recebidas na requisicao
 * {Array}: dados(wscallback, produto(codigo))
 */
$dados = $_REQUEST["dados"];
$wscallback = $dados["wscallback"];
$pedidosLista = $dados["pedidosLista"];

/* variaveis de retorno do ws */
$wsstatus = 0;
$wsresult = array();

// url de ws
$client = new nusoap_client($wsPedidosLista);
$client->useHTTPPersistentConnection();

// serial do cliente
$serail_number_cliente = readSerialNumber();

$dados = sprintf("<produto>\n\t<codigo_cliente_lista>%s</codigo_cliente_lista><tipo_lista>%s</tipo_lista><codigo_loja>%s</codigo_loja>\n</produto>", 
        $pedidosLista["cliente_lista"], $pedidosLista["tipo_lista"], $pedidosLista["codigo_loja"]);

// grava log
$log->addLog(ACAO_REQUISICAO, "getPedidosLista", $dados, SEPARADOR_INICIO);

// monta os parametros a serem enviados
$params = array(
    "crypt" => $serail_number_cliente,
    "dados" => $dados
);

// realiza a chamada de um metodo do ws passando os paramentros
$result = $client->call("listaProdutosDePedidosRelacionadosAListas", $params);

// grava log
$log->addLog(ACAO_RETORNO, "dadosPedidosLista", $result);

// converte o xml em um array
$res = XML2Array::createArray($result);

if ($res["resultado"]["sucesso"] && isset($res["resultado"]["dados"]["produto"])) {

  $produtos = array();

  if(key_exists("0", $res["resultado"]["dados"]["produto"]))
    $produtos = $res["resultado"]["dados"]["produto"];
  else
    $produtos[] = $res["resultado"]["dados"]["produto"];

  $wsstatus = 1;
  $wsresult = array();

  foreach($produtos as $produto){
    
    $existsOk = false;
    foreach($pedidos as $pedido){
      if($pedido["pedido_codigo"] == $produto["codigo_pedido"])
        $existsOk = true;
    }
    
    if(!$existsOk){

      // obtem todos os dados do pedido
      $dados = sprintf("<dados>\n\t<codigo_pedido>%s</codigo_pedido>\n</dados>", $produto["codigo_pedido"]);

      // grava log
      $log->addLog(ACAO_REQUISICAO, "getPedido", $dados, SEPARADOR_INICIO);

      // monta os parametros a serem enviados
      $params = array(
          'crypt' => $serail_number_cliente,
          'dados' => $dados
      );
      
      // url de ws
      $client = new nusoap_client($wsPedido);
      $client->useHTTPPersistentConnection();

      // realiza a chamada de um metodo do ws passando os paramentros
      $result = $client->call('listar', $params);
      $res = XML2Array::createArray($result);

      // grava log
      $log->addLog(ACAO_RETORNO, "dadosPedido", $result);
      
      if (isset($res['resultado']['dados']['pedido'])) {

        $pedido = $res['resultado']['dados']['pedido'];
        
        // obtem os dados do cliente
        $dados = sprintf("<cliente><codigo_cliente>%s</codigo_cliente></cliente>", $pedido['codigo_cliente']);
        
        // url de ws
        $client = new nusoap_client($wsCliente);
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

          $cliente = $res["resultado"]["dados"]["cliente"];
        
          $pedidos[] = array(
            "pedido_codigo" => $pedido["codigo_pedido"],
            "cliente_codigo" => $cliente["codigo_cliente"],
            "cliente_nome" => $cliente["nome_cliente"]
          );
        }
      }
    }
  }
  
  $wsresult = $pedidos;
}

else{
  /* monta o xml de retorno */
  $wsstatus = 0;
  $wsresult["wserror"] = "Nenhum pedido encontrado!";

  // grava log
  $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

  returnWS($wscallback, $wsstatus, $wsresult);
}

// grava log
$log->addLog(ACAO_RETORNO, $wscallback, $wsresult, SEPARADOR_FIM);

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>
