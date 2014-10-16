<?php

define("WService_DIR", dirname(__FILE__) . DIRECTORY_SEPARATOR);
require_once WService_DIR . "lib/define.inc.php";
require_once WService_DIR . "lib/function.inc.php";
require_once WService_DIR . "lib/nusoap/nusoap.php";
require_once WService_DIR . "lib/wideimage/lib/WideImage.php";
require_once WService_DIR . "classes/XML2Array.class.php";
require_once WService_DIR . "classes/Log.class.php";

/* LOG */
$log = new Log();

/* obtendo algumas configuracoes do sistema */
$conf = getConfig();
$wsLista = sprintf("%s/listaws.php", $conf["SISTEMA"]["saciWS"]);
$wsCliente = sprintf("%s/clientews.php", $conf["SISTEMA"]["saciWS"]);
$wsFabricante = sprintf("%s/fabricantews.php", $conf['SISTEMA']['saciWS']);
$wsProduto = sprintf("%s/produtows.php", $conf['SISTEMA']['saciWS']);

/* cliente para listas default */
$clienteListaDefault = $conf["MISC"]["clienteListaDefault"];

/* caminhos completos para a localizacao e acesso as imagens dos produtos */
$url_imgs = $conf["SISTEMA"]["urlImgs"];
$dir_imgs = $conf["SISTEMA"]["dirImgs"];

/* variaveis recebidas na requisicao
 * {Array}: dados(wscallback, produto(codigo))
 */
$dados = $_REQUEST["dados"];
$wscallback = $dados["wscallback"];
$lista = $dados["lista"];

/* variaveis de retorno do ws */
$wsstatus = 0;
$wsresult = array();

// serial do cliente
$serail_number_cliente = readSerialNumber();

// busca por listas default
if ($lista['listaDefault'] == 1) {
  $dados = sprintf("<lista><codigo_cliente>%s</codigo_cliente><tipo>%s</tipo></lista>", $clienteListaDefault, $lista["tipo"]);

  // url de ws
  $client = new nusoap_client($wsLista);
  $client->useHTTPPersistentConnection();

// grava log
  $log->addLog(ACAO_REQUISICAO, "getLista", $dados, SEPARADOR_INICIO);

// monta os parametros a serem enviados
  $params = array(
      "crypt" => $serail_number_cliente,
      "dados" => $dados
  );

// realiza a chamada de um metodo do ws passando os paramentros
  $result = $client->call("listar", $params);

// remove acentos dos dados
  $result = removerAcentos($result);

// grava log
  $log->addLog(ACAO_RETORNO, "dadosLista", $result);

// converte o xml em um array
  $res = XML2Array::createArray($result);

  if ($res["resultado"]["sucesso"] && isset($res["resultado"]["dados"]["lista"])) {

    $lista = $res["resultado"]["dados"]["lista"];

    $wsstatus = 1;
    $wsresult = array();
    
    $prds = array();

    if (key_exists("0", $lista['lista_produtos']['produto']))
      $prds = $lista['lista_produtos']['produto'];
    else
      $prds[] = $lista['lista_produtos']['produto'];

    foreach ($prds as &$produto) {

      $produto["codigo"] = $produto['codigo_produto'];
      $produto["descricao"] = $produto['nome_real_produto'] . " " . $produto['unidade'];

      /* define o caminho completo do diteretorio de imagens do produto buscado */
      $dir_full = sprintf("%s/%s/", $dir_imgs, $produto['codigo_produto']);
      $url_full = sprintf("%s/%s/", $url_imgs, $produto['codigo_produto']);

      /* verifica se o diretorio existe */
      if (file_exists($dir_full)) {
        /* se o diretorio existir, percorre o diretorio buscando as imagens */
        $handle = opendir($dir_full);
        while (false !== ($file = readdir($handle))) {
          if (in_array($file, array(".", "..")))
            continue;

          //obtem a extensao do anexo
          $filepath = explode(".", $file);
          $extensao = end($filepath);

          if (!in_array($extensao, $extensions_enable))
            continue;

          /* verifica se a miniatura a existe */
          $fileOk = explode('_min.' . $extensao, $file);
          if (key_exists("1", $fileOk))
            continue;

          //define o nome da miniatura da imagem
          $file_min = str_replace('.' . $extensao, '_min.' . $extensao, $file);

          //gera a miniatura
          $image = WideImage::load($dir_full . $file);
          $resized = $image->resize(300, 250);
          $resized->saveToFile($dir_full . $file_min, 80);

          $produto["img"][] = array(
            'arquivo' => $url_full . $file
          );
        }
      }
    }

    /* dados do produto */
    $wsresult = array(
        "cliente_codigo" => $lista["codigo_cliente"],
        "cliente_nome" => $lista["name_cliente"],
        "tipo_codigo" => $lista["tipo"],
        "tipo_nome" => $lista["tipo_name"],
        "data_evento" => $lista["data_evento"],
        "noivo_nome" => $lista['nome_noivo'],
        "noivo_pai" => $lista['pai_noivo'],
        "noivo_mae" => $lista['mae_noivo'],
        "noivo_cep" => $lista['cep_noivo'],
        "noivo_telefone" => $lista['telefone_noivo'],
        "noivo_endereco" => $lista['endereco_noivo'],
        "noivo_bairro" => $lista['bairro_noivo'],
        "noivo_cidade" => $lista['cidade_noivo'],
        "noivo_estado" => $lista['estado_noivo'],
        "observacoes" => $lista['observacoes'],
        "outras_observacoes" => $lista['outras_observacoes'],
        "produtos" => $prds
    );
  }
}

// busca pelo codigo do cliente
else if ($lista['searchType'] == 0) {
// url de ws
  $client = new nusoap_client($wsLista);
  $client->useHTTPPersistentConnection();

  $dados = sprintf("<lista><codigo_cliente>%s</codigo_cliente></lista>", $lista["codigo_cliente"]);

// grava log
  $log->addLog(ACAO_REQUISICAO, "getLista", $dados, SEPARADOR_INICIO);

// monta os parametros a serem enviados
  $params = array(
      "crypt" => $serail_number_cliente,
      "dados" => $dados
  );

// realiza a chamada de um metodo do ws passando os paramentros
  $result = $client->call("listar", $params);

// remove acentos dos dados
  $result = removerAcentos($result);

// grava log
  $log->addLog(ACAO_RETORNO, "dadosLista", $result);

// converte o xml em um array
  $res = XML2Array::createArray($result);

  if ($res["resultado"]["sucesso"] && isset($res["resultado"]["dados"]["lista"])) {

    $listas = array();

    if (key_exists("0", $res["resultado"]["dados"]["lista"]))
      $listas = $res["resultado"]["dados"]["lista"];
    else
      $listas[] = $res["resultado"]["dados"]["lista"];

    $wsstatus = 1;
    $wsresult = array();

    usort($listas, 'ordenarLista');

    foreach ($listas as $lista) {
      
      $prds = array();

      if (key_exists("0", $lista['lista_produtos']['produto']))
        $prds = $lista['lista_produtos']['produto'];
      else
        $prds[] = $lista['lista_produtos']['produto'];

      foreach ($prds as &$produto) {

        $produto["codigo"] = $produto['codigo_produto'];
        $produto["descricao"] = $produto['nome_real_produto'] . " " . $produto['unidade'];

        /* define o caminho completo do diteretorio de imagens do produto buscado */
        $dir_full = sprintf("%s/%s/", $dir_imgs, $produto['codigo_produto']);
        $url_full = sprintf("%s/%s/", $url_imgs, $produto['codigo_produto']);

        /* verifica se o diretorio existe */
        if (file_exists($dir_full)) {
          /* se o diretorio existir, percorre o diretorio buscando as imagens */
          $handle = opendir($dir_full);
          while (false !== ($file = readdir($handle))) {
            if (in_array($file, array(".", "..")))
              continue;

            //obtem a extensao do anexo
            $filepath = explode(".", $file);
            $extensao = end($filepath);

            if (!in_array($extensao, $extensions_enable))
              continue;

            /* verifica se a miniatura a existe */
            $fileOk = explode('_min.' . $extensao, $file);
            if (key_exists("1", $fileOk))
              continue;

            //define o nome da miniatura da imagem
            $file_min = str_replace('.' . $extensao, '_min.' . $extensao, $file);

            //gera a miniatura
            $image = WideImage::load($dir_full . $file);
            $resized = $image->resize(300, 250);
            $resized->saveToFile($dir_full . $file_min, 80);

            $produto["img"][] = array(
              'arquivo' => $url_full . $file
            );
          }
        }
      }

      /* dados do produto */
      $wsresult[] = array(
          "cliente_codigo" => $lista["codigo_cliente"],
          "cliente_nome" => $lista["name_cliente"],
          "tipo_codigo" => $lista["tipo"],
          "tipo_nome" => $lista["tipo_name"],
          "data_evento" => $lista["data_evento"],
          "noivo_nome" => $lista['nome_noivo'],
          "noivo_pai" => $lista['pai_noivo'],
          "noivo_mae" => $lista['mae_noivo'],
          "noivo_cep" => $lista['cep_noivo'],
          "noivo_telefone" => $lista['telefone_noivo'],
          "noivo_endereco" => $lista['endereco_noivo'],
          "noivo_bairro" => $lista['bairro_noivo'],
          "noivo_cidade" => $lista['cidade_noivo'],
          "noivo_estado" => $lista['estado_noivo'],
          "observacoes" => $lista['observacoes'],
          "outras_observacoes" => $lista['outras_observacoes'],
          "produtos" => $prds
      );
    }
  }

  else {
    /* monta o xml de retorno */
    $wsstatus = 0;
    $wsresult["wserror"] = "Nenhuma lista encontrada!";

    // grava log
    $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

    returnWS($wscallback, $wsstatus, $wsresult);
  }
}

// caso tenha preenchido o cliente
else if (!empty($lista['cliente'])) {

  //url de ws
  $client = new nusoap_client($wsCliente);
  $client->useHTTPPersistentConnection();

  // busca pelo nome do cliente
  if ($lista['searchType'] == 1)
    $dados = sprintf("<dados><nome_cliente>%%%s%%</nome_cliente></dados>", $lista['cliente']);

  // busca pelo cpf do cliente
  else
    $dados = sprintf("<dados><cpf_cgc>%s</cpf_cgc></dados>", $lista['cliente']);

// grava log
  $log->addLog(ACAO_REQUISICAO, "getCliente", $dados, SEPARADOR_INICIO);

// monta os parametros a serem enviados
  $params = array(
      "crypt" => $serail_number_cliente,
      "dados" => $dados
  );

// realiza a chamada de um metodo do ws passando os paramentros
  $result = $client->call("listar", $params);

// remove acentos dos dados
  $result = removerAcentos($result);

// grava log
  $log->addLog(ACAO_RETORNO, "dadosCliente", $result);

// converte o xml em um array
  $res = XML2Array::createArray($result);

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

      $client = new nusoap_client($wsLista);
      $client->useHTTPPersistentConnection();

      $dados = sprintf("<lista><codigo_cliente>%s</codigo_cliente>"
              . "<tipo>%s</tipo>"
              . "<data_evento>%s</data_evento></lista>", $cliente['codigo_cliente'], $lista['tipo'], $lista['data_evento']);

// grava log
      $log->addLog(ACAO_REQUISICAO, "getLista", $dados, SEPARADOR_INICIO);

// monta os parametros a serem enviados
      $params = array(
          "crypt" => $serail_number_cliente,
          "dados" => $dados
      );

// realiza a chamada de um metodo do ws passando os paramentros
      $result = $client->call("listar", $params);

// remove acentos dos dados
      $result = removerAcentos($result);

// grava log
      $log->addLog(ACAO_RETORNO, "dadosLista", $result);

// converte o xml em um array
      $res = XML2Array::createArray($result);
//            echo "<pre>";
//            print_r($res);

      if ($res["resultado"]["sucesso"] && isset($res["resultado"]["dados"]["lista"])) {

        $listas = array();

        if (key_exists("0", $res["resultado"]["dados"]["lista"]))
          $listas = $res["resultado"]["dados"]["lista"];
        else
          $listas[] = $res["resultado"]["dados"]["lista"];

        foreach ($listas as $list) {

          $prds = array();
          
          if (key_exists("0", $list['lista_produtos']['produto']))
            $prds = $list['lista_produtos']['produto'];
          else
            $prds[] = $list['lista_produtos']['produto'];
          
          foreach ($prds as &$produto) {

            $produto["codigo"] = $produto['codigo_produto'];
            $produto["descricao"] = $produto['nome_real_produto'] . " " . $produto['unidade'];

            /* define o caminho completo do diteretorio de imagens do produto buscado */
            $dir_full = sprintf("%s/%s/", $dir_imgs, $produto['codigo_produto']);
            $url_full = sprintf("%s/%s/", $url_imgs, $produto['codigo_produto']);

            /* verifica se o diretorio existe */
            if (file_exists($dir_full)) {
              /* se o diretorio existir, percorre o diretorio buscando as imagens */
              $handle = opendir($dir_full);
              while (false !== ($file = readdir($handle))) {
                if (in_array($file, array(".", "..")))
                  continue;

                //obtem a extensao do anexo
                $filepath = explode(".", $file);
                $extensao = end($filepath);

                if (!in_array($extensao, $extensions_enable))
                  continue;

                /* verifica se a miniatura a existe */
                $fileOk = explode('_min.' . $extensao, $file);
                if (key_exists("1", $fileOk))
                  continue;

                //define o nome da miniatura da imagem
                $file_min = str_replace('.' . $extensao, '_min.' . $extensao, $file);

                //gera a miniatura
                $image = WideImage::load($dir_full . $file);
                $resized = $image->resize(300, 250);
                $resized->saveToFile($dir_full . $file_min, 80);

                $produto["img"][] = array(
                  'arquivo' => $url_full . $file
                );
              }
            }
          }

          /* dados do produto */
          $wsresult[] = array(
              "cliente_codigo" => $list["codigo_cliente"],
              "cliente_nome" => $list["name_cliente"],
              "tipo_codigo" => $list["tipo"],
              "tipo_nome" => $list["tipo_name"],
              "data_evento" => $list["data_evento"],
              "noivo_nome" => $list['nome_noivo'],
              "noivo_pai" => $list['pai_noivo'],
              "noivo_mae" => $list['mae_noivo'],
              "noivo_cep" => $list['cep_noivo'],
              "noivo_telefone" => $list['telefone_noivo'],
              "noivo_endereco" => $list['endereco_noivo'],
              "noivo_bairro" => $list['bairro_noivo'],
              "noivo_cidade" => $list['cidade_noivo'],
              "noivo_estado" => $list['estado_noivo'],
              "observacoes" => $list['observacoes'],
              "outras_observacoes" => $list['outras_observacoes'],
              "produtos" => $prds
          );
        }
      }
    }
  } else {
    /* monta o xml de retorno */
    $wsstatus = 0;
    $wsresult["wserror"] = "Nenhuma lista encontrada!";

    // grava log
    $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

    returnWS($wscallback, $wsstatus, $wsresult);
  }
}

// caso nao tenha preenchido o cliente
else {
  
  $client = new nusoap_client($wsLista);
  $client->useHTTPPersistentConnection();

  $dados = sprintf(""
              . "<lista>"
              . "<codigo_cliente>%s</codigo_cliente>"
              . "<tipo>%s</tipo>"
              . "<data_evento>%s</data_evento>"
              . "<pai_noivo>%%%s%%</pai_noivo>"
              . "<mae_noivo>%%%s%%</mae_noivo>"
              . "</lista>", 
          $cliente['codigo_cliente'], $lista['tipo'], $lista['data_evento'], 
          $lista['pai_noivo'], $lista['mae_noivo']);
  
// grava log
  $log->addLog(ACAO_REQUISICAO, "getLista", $dados, SEPARADOR_INICIO);

// monta os parametros a serem enviados
  $params = array(
      "crypt" => $serail_number_cliente,
      "dados" => $dados
  );

// realiza a chamada de um metodo do ws passando os paramentros
  $result = $client->call("listar", $params);

// remove acentos dos dados
  $result = removerAcentos($result);

// grava log
  $log->addLog(ACAO_RETORNO, "dadosLista", $result);

// converte o xml em um array
  $res = XML2Array::createArray($result);
//            echo "<pre>";
//            print_r($res);

  if ($res["resultado"]["sucesso"] && isset($res["resultado"]["dados"]["lista"])) {

    $wsstatus = 1;
    $wsresult = array();
    
    $listas = array();

    if (key_exists("0", $res["resultado"]["dados"]["lista"]))
      $listas = $res["resultado"]["dados"]["lista"];
    else
      $listas[] = $res["resultado"]["dados"]["lista"];

    foreach ($listas as $list) {

      $prds = array();
      
      if (key_exists("0", $list['lista_produtos']['produto']))
        $prds = $list['lista_produtos']['produto'];
      else
        $prds[] = $list['lista_produtos']['produto'];

      foreach ($prds as &$produto) {

        $produto["codigo"] = $produto['codigo_produto'];
        $produto["descricao"] = $produto['nome_real_produto'] . " " . $produto['unidade'];

        /* define o caminho completo do diteretorio de imagens do produto buscado */
        $dir_full = sprintf("%s/%s/", $dir_imgs, $produto['codigo_produto']);
        $url_full = sprintf("%s/%s/", $url_imgs, $produto['codigo_produto']);

        /* verifica se o diretorio existe */
        if (file_exists($dir_full)) {
          /* se o diretorio existir, percorre o diretorio buscando as imagens */
          $handle = opendir($dir_full);
          while (false !== ($file = readdir($handle))) {
            if (in_array($file, array(".", "..")))
              continue;

            //obtem a extensao do anexo
            $filepath = explode(".", $file);
            $extensao = end($filepath);

            if (!in_array($extensao, $extensions_enable))
              continue;

            /* verifica se a miniatura a existe */
            $fileOk = explode('_min.' . $extensao, $file);
            if (key_exists("1", $fileOk))
              continue;

            //define o nome da miniatura da imagem
            $file_min = str_replace('.' . $extensao, '_min.' . $extensao, $file);

            //gera a miniatura
            $image = WideImage::load($dir_full . $file);
            $resized = $image->resize(300, 250);
            $resized->saveToFile($dir_full . $file_min, 80);

            $produto["img"][] =  array(
              'arquivo' => $url_full . $file
            );
          }
        }
      }

      /* dados do produto */
      $wsresult[] = array(
          "cliente_codigo" => $list["codigo_cliente"],
          "cliente_nome" => $list["name_cliente"],
          "tipo_codigo" => $list["tipo"],
          "tipo_nome" => $list["tipo_name"],
          "data_evento" => $list["data_evento"],
          "noivo_nome" => $list['nome_noivo'],
          "noivo_pai" => $list['pai_noivo'],
          "noivo_mae" => $list['mae_noivo'],
          "noivo_cep" => $list['cep_noivo'],
          "noivo_telefone" => $list['telefone_noivo'],
          "noivo_endereco" => $list['endereco_noivo'],
          "noivo_bairro" => $list['bairro_noivo'],
          "noivo_cidade" => $list['cidade_noivo'],
          "noivo_estado" => $list['estado_noivo'],
          "observacoes" => $list['observacoes'],
          "outras_observacoes" => $list['outras_observacoes'],
          "produtos" => $prds
      );
    }
  } else {
    /* monta o xml de retorno */
    $wsstatus = 0;
    $wsresult["wserror"] = "Nenhuma lista encontrada!";

    // grava log
    $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

    returnWS($wscallback, $wsstatus, $wsresult);
  }
}

// grava log
$log->addLog(ACAO_RETORNO, $wscallback, $wsresult, SEPARADOR_FIM);

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>
