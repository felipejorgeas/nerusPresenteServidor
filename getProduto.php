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
$ws = sprintf("%s/produtows.php", $conf['SISTEMA']['saciWS']);

/* lista de lojas para produtos com e sem grades */
$lojas_com_grade = $conf['MISC']['lojaComGrade'];
$lojas_sem_grade = $conf['MISC']['lojaSemGrade'];
$grade_encomenda = $conf['MISC']['gradeEncomend'];

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
$client = new nusoap_client($ws);
$client->useHTTPPersistentConnection();

// serial do cliente
$serail_number_cliente = readSerialNumber();

/* serach_type
 * 0 => numero
 * 1 => texto
*/
if($produto['search_type'] == 1){
  $produto['codigo'] = removerAcentos($produto['codigo']);
  $dados = sprintf("<dados>\n\t<nome_produto>%%%s%%</nome_produto>\n\t<loadstk>0</loadstk>\n</dados>", strtoupper($produto['codigo']));
}
else{
  $dados = sprintf("<dados>\n\t<codigo_produto>%s</codigo_produto>\n</dados>", $produto['codigo']);
}

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
  if($produto['search_type'] == 1){

    $produtos = array();

    if(key_exists("0", $res['resultado']['dados']['produto']))
      $produtos = $res['resultado']['dados']['produto'];
    else
      $produtos[] = $res['resultado']['dados']['produto'];

    $wsstatus = 2;
    $wsresult = array();

    foreach($produtos as $prd){
      /* dados do produto */
      $wsresult[] = array(
          'codigo' => $prd['codigo_produto'],
          'descricao' => $prd['nome_produto'] . ' ' . $prd['nome_unidade']
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
        'estoque' => array(),
        'img' => array(),
    );

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
        $wsresult['preco'] = number_format($estoque['preco'] / 100, 2, ',', '.');

        $lojas = array();
        $insertStk = false;

        /* verifica se o produto possui grade */
        if(!empty($estoque['grade'])){
          /* se o produto possuir grade, lista apenas os produtos de determinadas lojas */
          $lojas = explode(",", str_replace(" ", "", $lojas_com_grade));
        }
        else{
          /* se o produto nao possuir grade, lista apenas os produtos de determinadas loas */
          $lojas = explode(",", str_replace(" ", "", $lojas_sem_grade));
        }

        /* obtem apenas o estoque da lista de lojas definidas */
        if(in_array($estoque['codigo_loja'], $lojas)){
          $qtty_estoque = $estoque['qtty'] - $estoque['qtty_reservada'];
          $qtty_estoque = $qtty_estoque > 0 ? $qtty_estoque : 0;
          $estoqueOk = true;

          $wsresult['estoque'][] = array(
              'barcode' => $estoque['codigo_barra_produto'],
              'grade' => $estoque['grade'],
              'codigo_loja' => $estoque['codigo_loja'],
              'nome_loja' => $estoque['nome_loja'],
              'qtty' => $qtty_estoque
          );
        }
      }
    }

    /* caso nao tenha estoque */
    if(!$estoqueOk){
      /* monta o xml de retorno */
      $wsstatus = 0;
      //$wsresult['wserror'] = "Produto sem estoque em nenhuma loja no momento!";
      $wsresult['wserror'] = "N&atilde;o h&atilde; estoque cadastrado para este produto!";

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

        $wsresult['img'][] = array(
            'arquivo' => $url_full . $file
        );
      }
    }
  }
}

else{
  /* monta o xml de retorno */
  $wsstatus = 0;
  $wsresult['wserror'] = sprintf("Produto <strong>%s</strong> n&atilde;o encontrado!", $produto['codigo']);

  // grava log
  $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

  returnWS($wscallback, $wsstatus, $wsresult);
}

// grava log
$log->addLog(ACAO_RETORNO, $wscallback, $wsresult, SEPARADOR_FIM);

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>
