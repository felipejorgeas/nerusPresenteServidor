<?php

define('WService_DIR', dirname(__FILE__) . DIRECTORY_SEPARATOR);
require_once WService_DIR . 'lib/define.inc.php';
require_once WService_DIR . 'lib/function.inc.php';
require_once WService_DIR . 'lib/nusoap/nusoap.php';
require_once WService_DIR . 'lib/html2pdf/html2pdf.class.php';
require_once WService_DIR . 'lib/wideimage/lib/WideImage.php';
require_once WService_DIR . 'lib/phpmailer/class.phpmailer.php';
require_once WService_DIR . 'classes/XML2Array.class.php';
require_once WService_DIR . 'classes/Log.class.php';

/* LOG */
$log = new Log();

/* obtendo algumas configuracoes do sistema */
$conf = getConfig();
$wsLista = sprintf("%s/listaws.php", $conf['SISTEMA']['saciWS']);
$wsFuncionario = sprintf("%s/funcionariows.php", $conf['SISTEMA']['saciWS']);
$wsCliente = sprintf("%s/clientews.php", $conf['SISTEMA']['saciWS']);

/* caminhos completos para a localizacao e acesso as imagens dos produtos */
$url_imgs = $conf['SISTEMA']['urlImgs'];
$dir_imgs = $conf['SISTEMA']['dirImgs'];

/* obtendo os dados da empresa */
$empresa_nome = $conf['EMPRESA']['nome'];
$empresa_slogan = $conf['EMPRESA']['slogan'];
$empresa_email = $conf['EMPRESA']['email'];
$empresa_site = $conf['EMPRESA']['site'];
$empresa_tel = $conf['EMPRESA']['tel'];

/* dados do pdf a ser gerado */
$pdf_dir = WService_DIR . "pdf/";
if(!file_exists($pdf_dir)){
  exec("mkdir " . $pdf_dir);
  exec("chmod 777 " . $pdf_dir);
}

/* variaveis recebidas na requisicao
 * {Array}: dados(
 *            wscallback, 
 *            lista(
 *              codigo_cliente,
 *              codigo_tipo
 *            )
 *          )
 */
$dados = $_REQUEST['dados'];
$wscallback = $dados['wscallback'];
$lista = $dados['lista'];

/* variaveis de retorno do ws */
$wsstatus = 0;
$wsresult = array();

// url de ws
$client = new nusoap_client($wsLista);
$client->useHTTPPersistentConnection();

// serial do cliente
$serail_number_cliente = readSerialNumber();

$dados = sprintf("
  <dados>
    <codigo_cliente>%s</codigo_cliente>
    <tipo>%s</tipo>
  </dados>",
  $lista['codigo_cliente'], $lista['codigo_tipo']);

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

$prds = array();
if (isset($res['resultado']['dados']['lista'])) {
  
  $obj_lista = $res['resultado']['dados']['lista'];

  // obtem os dados do retorno
  $cliente = $obj_lista["codigo_cliente"];
  $tipo = $obj_lista["tipo"];
  $tipo_nome = $obj_lista["tipo_name"];
  $storeno = $obj_lista["loja"];
  $data_criacao = $obj_lista["data_criacao"];
  $data_evento = $obj_lista["data_evento"];
  $funcionario = $obj_lista["funcionario"];  

  $data_criacao = sprintf("%s/%s/%s", substr($data_criacao, 6, 2), substr($data_criacao, 4, 2), substr($data_criacao, 0, 4));
  $data_evento = sprintf("%s/%s/%s", substr($data_evento, 6, 2), substr($data_evento, 4, 2), substr($data_evento, 0, 4));
  
  //define o nome do pdf a ser gerado
  $pdf_name = sprintf("lista_%d_%d.pdf", $cliente, $tipo);

  // obtem todos os produtos ja existentes no orcamento
  if (key_exists('0', $obj_lista['lista_produtos']['produto']))
    $prds = $obj_lista['lista_produtos']['produto'];
  else
    $prds[] = $obj_lista['lista_produtos']['produto'];
}

else {
  /* monta o xml de retorno */
  $wsstatus = 0;
  $wsresult['wserror'] = "Lista de presentes n&atilde;o encontrada!";

  // grava log
  $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

  returnWS($wscallback, $wsstatus, $wsresult);
}

// obtem os dados do funcionario
$client = new nusoap_client($wsFuncionario);
$client->useHTTPPersistentConnection();

$dados = sprintf("
  <dados>
    <codigo_funcionario>%d</codigo_funcionario>
  </dados>",
  $funcionario);

// monta os parametros a serem enviados
$params = array(
    'crypt' => $serail_number_cliente,
    'dados' => $dados
);

// realiza a chamada de um metodo do ws passando os paramentros
$result = $client->call('listar', $params);
$res = XML2Array::createArray($result);

if ($res['resultado']['sucesso'] && isset($res['resultado']['dados']['funcionario'])) {
  $funcionario = $res['resultado']['dados']['funcionario'];
  $func_name = $funcionario['nome_funcionario'];
  $func_email = $funcionario['email'];
}

// obtem os dados do cliente
$client = new nusoap_client($wsCliente);
$client->useHTTPPersistentConnection();

$dados = sprintf("
  <dados>
    <codigo_cliente>%d</codigo_cliente>
  </dados>",
  $cliente);

// monta os parametros a serem enviados
$params = array(
    'crypt' => $serail_number_cliente,
    'dados' => $dados
);

// realiza a chamada de um metodo do ws passando os paramentros
$result = $client->call('listar', $params);
$res = XML2Array::createArray($result);

if ($res['resultado']['sucesso'] && isset($res['resultado']['dados']['cliente'])) {
  $cliente = $res['resultado']['dados']['cliente'];
  $cliente_name = $cliente['nome_cliente'];
  $cliente_email = $cliente['email'];
}

$content = '<style type="text/css">
<!--
    table.page_header {width: 100%; background: #FFF; border-bottom: 1px solid #333; padding: 10px 0; }
    table.page_footer {width: 100%; background: #FFF; border-top: 1px solid #333; padding: 10px 0; }
    div.content{ margin: 80px 0 70px 0; background: #FFF; }
    div.ambiente{ font-weight: bold; display: block; padding: 5px; font-size: 12px; border-top: solid 1px #333; float: left;  }
    div.produtos{ float: left; margin-top: 10px; width: 100%; }
    div.produtos div.produto{ float: left; border-top: 1px solid #333; padding: 10px 0; }
    div.produtos div.produto img{ border: 1px solid #333; float: left; margin: 0 20px 0 5px; }
    div.produtos div.produto strong{ margin-top: 10px; }
    div.produtos div.produto span{ margin-top: 10px; }
    div.produtos div.produto table.dados{ margin: 10px 0 0 -3px; display: block; padding-top: -5px; float: left; };
    div.produtos div.produto table.dados tr td{ margin-top: 10px; }
    h1 { text-align: center; font-size: 20mm}
    h3 { text-align: center; font-size: 14mm}
-->
</style>
<page backtop="10px" backbottom="10px" backleft="10px" backright="10px" style="font-size: 12px">
    <page_header>
        <table class="page_header">
            <tr>
                <td style="width: 50%; text-align: left">
                    <img src="img/logo_cliente.png" style="width: 150px">
                </td>
                <td style="width: 50%; text-align: right">
                    ' . $data_criacao . '
                    <br/><br/><span style="font-size: 20px;">Lista de ' . $tipo_nome . '</span>
                </td>
            </tr>
        </table>
    </page_header>
    <page_footer>
        <table class="page_footer">
            <tr>
                <td style="width: 33%; text-align: left;">
                    <strong>' . $empresa_nome . '</strong>
                    <br/>' . $empresa_slogan . '
                </td>
                <td style="width: 34%; text-align: center">
                    ' . $empresa_site . '
                    <br/>' . $empresa_tel . '
                </td>
                <td style="width: 33%; text-align: right">
                    <span style="margin-top: -10px; font-size: 10px;">NÉRUS - EAC Software</span>
                    <br/>[[page_cu]]/[[page_nb]]
                </td>
            </tr>
        </table>
    </page_footer>
    <div class="content">';

$content .= '
      <table style="width: 100%">
        <tr>
          <td style="width: 50%"><strong>Vendedor:</strong></td>
          <td style="width: 50%"><strong>Cliente:</strong></td>
        </tr>
        <tr>
          <td style="width: 50%">' . $func_name . '<br/>' . $func_email . '</td>
          <td style="width: 50%">' . $cliente_name . '</td>
        </tr>
      </table>
      <div class="produtos">';

$page = 1;
$i = 0;

foreach ($prds as $produto) {
  if(!$produto['codigo_produto'] > 0)
    continue;

  if (($i > 0) && ($i == 4)) {
    $content .= '  </div>
                  </div>
                </page>
                <page pageset="old">
                  <div class="content">
                    <div class="produtos">';
    $i = 0;
  }

  $miniatura = 'img/logo_nophoto.jpg';

  /* define o caminho completo do diteretorio de imagens do produto buscado */
  $dir_full = sprintf("%s/%s/", $dir_imgs, $produto['codigo_produto']);
  $url_full = sprintf("%s/%s/", $url_imgs, $produto['codigo_produto']);

  /* verifica se o diretorio existe */
  if (file_exists($dir_full)) {
    /* se o diretorio existir, percorre o diretorio buscando as imagens */
    $handle = opendir($dir_full);
    while ((false !== ($file = readdir($handle)))) {
      $file = trim($file);

      if (in_array($file, array(".", "..")) || empty($file))
        continue;

      //obtem a extensao do anexo
      $filepath = explode(".", $file);
      $extensao = end($filepath);

      if(!in_array($extensao, $extensions_enable))
        continue;

      //define o nome da miniatura da imagem
      $file_min = str_replace('.' . $extensao, '_min.' . $extensao, $file);

      /* verifica se a miniatura a existe */
      $fileOk = explode('_min.' . $extensao, $file);
      if (!key_exists("1", $fileOk)) {
        //gera a miniatura
        $image = WideImage::load($dir_full . $file);
        $resized = $image->resize(300, 250);
        $resized->saveToFile($dir_full . $file_min);
        $miniatura = $dir_full . $file_min;
      } else {
        $miniatura = $dir_full . $file;
      }
      break;
    }
  }

  $content .= '<div class="produto">';
  $content .= sprintf('<img style="width: 230px; height: 150px;" src="%s" />', $miniatura);
  $content .= sprintf('<strong>%s</strong>', $produto['nome_real_produto']);
  $content .= '<table class="dados">';
  $content .= sprintf('<tr><td style="width: 70px;"><strong>Código:</strong></td><td><span>%s</span></td></tr>', $produto['codigo_produto']);
  
  if (!empty($produto['grade']))
    $content .= sprintf('<tr><td><strong>Grade:</strong></td><td><span>%s</span></td></tr>', $produto['grade']);
  
  $content .= sprintf('<tr><td><strong>Qtde.:</strong></td><td><span>%s</span></td></tr>', ($produto['quantidade_listada'] / 1000));
  $content .= '</table>';
  $content .= '</div>';

  $i++;
}

$content .= '</div></div></page>';
//echo $content; exit;
// init HTML2PDF
$html2pdf = new HTML2PDF('P', 'A4', 'pt', false, 'UTF-8', array(20, 15, 20, 10));

// display the full page
$html2pdf->pdf->SetDisplayMode('fullpage');

// convert
$html2pdf->writeHTML($content);

// generate pdf
//$html2pdf->Output($pdf_name);
$html2pdf->Output($pdf_dir . $pdf_name, 'F');

// send pdf to email
$mail = new PHPMailer();
$mail->IsSendmail();

$mail->SetFrom($empresa_email, sprintf("%s - %s", $empresa_nome, $empresa_slogan), 1);
$mail->AddAddress($func_email, $func_name);

$mail->Subject = sprintf("NÉRUS Presente - Lista de %s - %s", $tipo_nome, $cliente_name);
$mail->MsgHTML("Segue lista de presente em anexo.");
$mail->AddAttachment($pdf_dir . $pdf_name);

if (!$mail->Send()) {
  /* monta o xml de retorno */
  $wsstatus = 0;
  $wsresult['wserror'] = "N&atilde;o foi poss&iacute;vel enviar o PDF da lista de presentes por e-mail!<br/>O PDF foi '" . $pdf_name . "'salvo no servidor.";

  // grava log
  $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

  returnWS($wscallback, $wsstatus, $wsresult);
}

$wsstatus = 1;

// grava log
$log->addLog(ACAO_RETORNO, $wscallback, $wsresult, SEPARADOR_FIM);

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>