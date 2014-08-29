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
$ws = sprintf("%s/funcionariows.php", $conf['SISTEMA']['saciWS']);

/* variaveis recebidas na requisicao
 * {Array}: dados(wscallback, usuario(apelido, senha))
 */
$dados = $_REQUEST['dados'];
$wscallback = $dados['wscallback'];
$usuario = $dados['usuario'];

/* variaveis de retorno do ws */
$wsstatus = 0;
$wsresult = array();

// url de ws
$client = new nusoap_client($ws);
$client->useHTTPPersistentConnection();

// serial do cliente
$serail_number_cliente = readSerialNumber();

$dados = sprintf("<dados>\n\t<apelido>%s</apelido>\n\t<senha>%s</senha>\n</dados>", $usuario['apelido'], $usuario['senha']);

// grava log
$log->addLog(ACAO_REQUISICAO, "getFuncionario", $dados, SEPARADOR_INICIO);

// monta os parametros a serem enviados
$params = array(
    'crypt' => $serail_number_cliente,
    'dados' => $dados
);

// realiza a chamada de um metodo do ws passando os paramentros
$result = $client->call('listar', $params);
$res = XML2Array::createArray($result);

// grava log
$log->addLog(ACAO_RETORNO, "dadosFuncionario", $result);

if ($res['resultado']['sucesso'] && isset($res['resultado']['dados']['funcionario'])) {
  $funcionario = $res['resultado']['dados']['funcionario'];

  /* obtendo algumas configuracoes do sistema */
  $relfuncuser = getConfig('RELACAO_FUNC_USER', FILE_REL_FUNC_USER);
  $codigo_usuario = $relfuncuser[$funcionario['codigo_funcionario']];

  if(!($codigo_usuario > 0)){
    /* monta o xml de retorno */
    $wsstatus = 0;
    $wsresult['wserror'] = "O funcion&aacute;rio n&atilde;o est&aacute; relacionado a nenhum usu&aacute;rio.";

    // grava log
    $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

    returnWS($wscallback, $wsstatus, $wsresult);
  }

  // verifica o nivel de permissao do usuario
  // 0 - usuario sem permissao para usar o app
  // 1 - usuario com permissao para usar o app
  // 2 - usuario com permissao para usar o app e alterar configuracoes
  switch($funcionario['codigo_cargo']){
    case EMPTYPE_VENDEDOR:
      $permissao = 1;
      break;
    case EMPTYPE_GERENTE:
      $permissao = 2;
      break;
    case EMPTYPE_DIRETOR:
      $permissao = 2;
      break;
    default:
      $permissao = 0;
  }

  if($permissao > 0){
    $wsstatus = 1;
    $wsresult = array(
        'codigo' => $funcionario['codigo_funcionario'],
        'nome' => $funcionario['nome_funcionario'],
        'email' => $funcionario['email'],
        'loja' => $funcionario['codigo_loja'],
        'usuario' => $codigo_usuario,
        'permissao' => $permissao
    );
  }

  else{
    /* monta o xml de retorno */
    $wsstatus = 0;
    $wsresult['wserror'] = "Permiss&atilde;o negada! Apenas vendedores, gerentes e diretores.";

    // grava log
    $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

    returnWS($wscallback, $wsstatus, $wsresult);
  }
}

else{
   /* monta o xml de retorno */
   $wsstatus = 0;
   $wsresult['wserror'] = "Funcion&aacute;rio n&atilde;o encontrado.";

   // grava log
   $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

   returnWS($wscallback, $wsstatus, $wsresult);
}

// grava log
$log->addLog(ACAO_RETORNO, $wscallback, $wsresult, SEPARADOR_FIM);

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>