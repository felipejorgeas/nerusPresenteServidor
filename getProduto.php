<?php

define('WService_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
require_once WService_DIR . 'lib/define.inc.php';
require_once WService_DIR . 'lib/function.inc.php';
require_once WService_DIR . 'lib/nusoap/nusoap.php';
require_once WService_DIR . 'lib/wideimage/lib/WideImage.php';
require_once WService_DIR . 'classes/XML2Array.class.php';
require_once WService_DIR . 'classes/Log.class.php';

/* LOG */
$log = new Log();

/* obtendo algumas configuracoes do sistema */
$conf = getConfig();
$wsProduto = sprintf("%s/produtows.php", $conf['SISTEMA']['saciWS']);
$wsFabricante = sprintf("%s/fabricantews.php", $conf['SISTEMA']['saciWS']);
$wsCentroLucro = sprintf("%s/categoriaws.php", $conf['SISTEMA']['saciWS']);

/* lista de lojas para produtos com e sem grades */
//$lojas = $conf['MISC']['loja'];

/* caminhos completos para a localizacao e acesso as imagens dos produtos */
$url_imgs = $conf['SISTEMA']['urlImgs'];
$dir_imgs = $conf['SISTEMA']['dirImgs'];

/* variaveis recebidas na requisicao
 * {Array}: dados(wscallback, produto(codigo))
 */
$dados = $_REQUEST['dados'];
$wscallback = $dados['wscallback'];
$produto = $dados['produto'];

/* variaveis de retorno do ws */
$wsstatus = 0;
$wsresult = array();

// url de ws
$client = new nusoap_client($wsProduto);
$client->useHTTPPersistentConnection();

// serial do cliente
$serail_number_cliente = readSerialNumber();

/* serach_type
 * 0 => numero
 * 1 => texto
*/
if($produto['searchType'] == 1){
  $produto['centro_lucro'] = explode(",", $produto['centro_lucro']);
  $dados = sprintf("<dados>"
        . "\n\t<nome_produto>%%%s%%</nome_produto>"
        . "\n\t<codigo_fabricante>%s</codigo_fabricante>"
        . "\n\t<tipo_produto>%s</tipo_produto>"
        . "\n\t<loadstk>0</loadstk>\n</dados>", 
        strtoupper($produto['produto']), $produto['codigo_fabricante'], 
        $produto['tipo_produto']);  
}

else{
  $dados = sprintf("<dados>\n\t<codigo_produto>%s</codigo_produto>\n</dados>", $produto['produto']);
}

if($produto['searchType'] && count($produto['centro_lucro']) > 0){
  
  $wsstatus = 2;
  $wsresult = array();
  
  foreach($produto["centro_lucro"] as $cls){
    $dados = sprintf("<dados>"
          . "\n\t<nome_produto>%%%s%%</nome_produto>"
          . "\n\t<codigo_fabricante>%s</codigo_fabricante>"
          . "\n\t<tipo_produto>%s</tipo_produto>"
          . "\n\t<codigo_centro_lucro>%s</codigo_centro_lucro>"            
          . "\n\t<loadstk>0</loadstk>\n</dados>", 
          strtoupper($produto['produto']), $produto['codigo_fabricante'], 
          $produto['tipo_produto'], $cls);
    
    // grava log
    $log->addLog(ACAO_REQUISICAO, "getProduto", $dados, SEPARADOR_INICIO);

    // monta os parametros a serem enviados
    $params = array(
        'crypt' => $serail_number_cliente,
        'dados' => $dados
    );

    // realiza a chamada de um metodo do ws passando os paramentros
    $result = $client->call("listar", $params);
    $res = XML2Array::createArray($result);

    // grava log
    $log->addLog(ACAO_RETORNO, "dadosProduto", $result);

    if ($res['resultado']['sucesso'] && isset($res['resultado']['dados']['produto'])) {

      $produtos = array();

      if(key_exists("0", $res['resultado']['dados']['produto']))
        $produtos = $res['resultado']['dados']['produto'];
      else
        $produtos[] = $res['resultado']['dados']['produto'];

      foreach($produtos as $prd){
        $img = "";

        /* define o caminho completo do diteretorio de imagens do produto buscado */
        $dir_full = sprintf("%s/%s/", $dir_imgs, $prd['codigo_produto']);
        $url_full = sprintf("%s/%s/", $url_imgs, $prd['codigo_produto']);

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

            if(!in_array($extensao, $extensions_enable))
              continue;

            /* verifica se a miniatura a existe */
            $fileOk = explode('_min.' . $extensao, $file);
            if(key_exists("1", $fileOk))
              continue;

            //define o nome da miniatura da imagem
            $file_min = str_replace('.' . $extensao, '_min.' . $extensao, $file);

            //gera a miniatura
            $image = WideImage::load($dir_full . $file);
            $resized = $image->resize(300, 250);
            $resized->saveToFile($dir_full . $file_min, 80);

            $img = $url_full . $file;
          }
        }

        /* dados do produto */
        $wsresult[] = array(
            'codigo' => $prd['codigo_produto'],
            'descricao' => $prd['nome_produto'] . ' ' . $prd['nome_unidade'],
            'img' => $img
        );
      }
    }
  }
}

else{

  // grava log
  $log->addLog(ACAO_REQUISICAO, "getProduto", $dados, SEPARADOR_INICIO);

  // monta os parametros a serem enviados
  $params = array(
      'crypt' => $serail_number_cliente,
      'dados' => $dados
  );

  // realiza a chamada de um metodo do ws passando os paramentros
  $result = $client->call("listar", $params);
  $res = XML2Array::createArray($result);

  // grava log
  $log->addLog(ACAO_RETORNO, "dadosProduto", $result);

  if ($res['resultado']['sucesso'] && isset($res['resultado']['dados']['produto'])) {

    /* serach_type
     * 0 => numero
     * 1 => texto
    */
    if($produto['searchType'] == 1){

      $produtos = array();

      if(key_exists("0", $res['resultado']['dados']['produto']))
        $produtos = $res['resultado']['dados']['produto'];
      else
        $produtos[] = $res['resultado']['dados']['produto'];

      $wsstatus = 2;
      $wsresult = array();

      foreach($produtos as $prd){
        $img = "";

        /* define o caminho completo do diteretorio de imagens do produto buscado */
        $dir_full = sprintf("%s/%s/", $dir_imgs, $prd['codigo_produto']);
        $url_full = sprintf("%s/%s/", $url_imgs, $prd['codigo_produto']);

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

            if(!in_array($extensao, $extensions_enable))
              continue;

            /* verifica se a miniatura a existe */
            $fileOk = explode('_min.' . $extensao, $file);
            if(key_exists("1", $fileOk))
              continue;

            //define o nome da miniatura da imagem
            $file_min = str_replace('.' . $extensao, '_min.' . $extensao, $file);

            //gera a miniatura
            $image = WideImage::load($dir_full . $file);
            $resized = $image->resize(300, 250);
            $resized->saveToFile($dir_full . $file_min, 80);

            $img = $url_full . $file;
          }
        }

        /* dados do produto */
        $wsresult[] = array(
            'codigo' => $prd['codigo_produto'],
            'descricao' => $prd['nome_produto'] . ' ' . $prd['nome_unidade'],
            'img' => $img
        );
      }
    }

    else{

      $produto = $res['resultado']['dados']['produto'];

      $wsstatus = 1;

      /* dados do produto */
      $wsresult = array(
          'codigo' => $produto['codigo_produto'],
          'descricao' => $produto['nome_produto'] . ' ' . $produto['nome_unidade'],
          'unidade' => $produto['nome_unidade'],
          'multiplicador' => $produto['multiplicador'],
          'preco' => 0,
          'centrolucro' => array(),
          'fornecedor' => array(),
          'grades' => array(),
          'estoque' => array(),
          'img' => array(),
      );

      // busca os dados do fornecedor
      $dados = sprintf("<dados>\n\t<codigo_fabricante>%d</codigo_fabricante>\n</dados>", $produto['codigo_fabricante']);

      // grava log
      $log->addLog(ACAO_REQUISICAO, "getFabricante", $dados, SEPARADOR_INICIO);

      // monta os parametros a serem enviados
      $params = array(
          'crypt' => $serail_number_cliente,
          'dados' => $dados
      );

      // realiza a chamada de um metodo do ws passando os paramentros    
      $client = new nusoap_client($wsFabricante);
      $client->useHTTPPersistentConnection();
      $result = $client->call("listar", $params);
      $res = XML2Array::createArray($result);

      // grava log
      $log->addLog(ACAO_RETORNO, "dadosFabricante", $result);

      if ($res['resultado']['sucesso'] && isset($res['resultado']['dados']['fabricante'])) {
        $wsresult['fornecedor'] = $res['resultado']['dados']['fabricante'];
      }

      // busca os dados do centro de lucro
      $dados = sprintf("<dados>\n\t<codigo_categoria>%d</codigo_categoria>\n</dados>", $produto['codigo_centro_lucro']);

      // grava log
      $log->addLog(ACAO_REQUISICAO, "getCentroLucro", $dados, SEPARADOR_INICIO);

      // monta os parametros a serem enviados
      $params = array(
          'crypt' => $serail_number_cliente,
          'dados' => $dados
      );

      // realiza a chamada de um metodo do ws passando os paramentros    
      $client = new nusoap_client($wsCentroLucro);
      $client->useHTTPPersistentConnection();
      $result = $client->call("listar", $params);
      $res = XML2Array::createArray($result);

      // grava log
      $log->addLog(ACAO_RETORNO, "dadosCentroLucro", $result);

      if ($res['resultado']['sucesso'] && isset($res['resultado']['dados']['categoria'])) {
        $wsresult['centrolucro'] = $res['resultado']['dados']['categoria'];
      }

      /* variavel de controle para verificar se possue estoque disponivel */
      $estoqueOk = false;

      /* dados de estoque do produto */
      if(!empty($produto['estoque'])){
        $estoques = array();

        if(key_exists("0", $produto['estoque']))
          $estoques = $produto['estoque'];
        else
          $estoques[] = $produto['estoque'];

        foreach($estoques as $estoque){

          // seta o preco
          // $wsresult['preco'] = number_format($estoque['preco'] / 100, 2, ',', '.');
          $wsresult['preco'] = $estoque['preco'];

          $qtty_estoque = $estoque['qtty'] - $estoque['qtty_reservada'];
          $qtty_estoque = $qtty_estoque > 0 ? $qtty_estoque : 0;
          $estoqueOk = true;

          if(!in_array($estoque['grade'], $wsresult['grades']))
            $wsresult['grades'][] = $estoque['grade'];

          $wsresult['estoque'][] = array(
              'barcode' => $estoque['codigo_barra_produto'],
              'grade' => $estoque['grade'],
              'codigo_loja' => $estoque['codigo_loja'],
              'nome_loja' => $estoque['nome_loja'],
              'qtty' => $qtty_estoque
          );
        }
      }

      /* caso nao tenha estoque */
      if(!$estoqueOk){
        /* monta o xml de retorno */
        $wsstatus = 0;
        $wsresult['wserror'] = "Produto sem estoque em nenhuma loja no momento!";
        //$wsresult['wserror'] = "N&atilde;o h&atilde; estoque cadastrado para este produto!";

        // grava log
        $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

        returnWS($wscallback, $wsstatus, $wsresult);
      }

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

          if(!in_array($extensao, $extensions_enable))
            continue;

          /* verifica se a miniatura a existe */
          $fileOk = explode('_min.' . $extensao, $file);
          if(key_exists("1", $fileOk))
            continue;

          //define o nome da miniatura da imagem
          $file_min = str_replace('.' . $extensao, '_min.' . $extensao, $file);

          //gera a miniatura
          $image = WideImage::load($dir_full . $file);
          $resized = $image->resize(300, 250);
          $resized->saveToFile($dir_full . $file_min, 80);

          array_push($wsresult['img'], array(
              'arquivo' => $url_full . $file
          ));
        }
      }
    }
  }

  else{
    /* monta o xml de retorno */
    $wsstatus = 0;
    $wsresult['wserror'] = sprintf("Produto n&atilde;o encontrado!", $produto['codigo']);

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
