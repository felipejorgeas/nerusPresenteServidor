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
$ws = sprintf("%s/pedidows.php", $conf['SISTEMA']['saciWS']);

/* lista de lojas para produtos com e sem grades */
$lojas_com_grade = $conf['MISC']['lojaComGrade'];
$lojas_sem_grade = $conf['MISC']['lojaSemGrade'];

/* caminhos completos para a localizacao e acesso as imagens dos produtos */
$url_imgs = $conf['SISTEMA']['urlImgs'];
$dir_imgs = $conf['SISTEMA']['dirImgs'];

/* obtendo os dados da empresa */
$empresa_nome = $conf['EMPRESA']['nome'];
$empresa_frase = $conf['EMPRESA']['frase'];
$empresa_email = $conf['EMPRESA']['email'];
$empresa_site = $conf['EMPRESA']['site'];
$empresa_tel = $conf['EMPRESA']['tel'];

/* dados do pdf a ser gerado */
$pdf_dir = WService_DIR . "pdfs/";
if(!file_exists($pdf_dir))
  exec("mkdir " . $pdf_dir);

// status default 'Orcamento'
$status = EORDSTATUS_ORCAMENTO;

/* variaveis recebidas na requisicao
 * {Array}: dados(wscallback, orcamento(codigo, func_name, func_email))
 */
$dados = $_REQUEST['dados'];
$wscallback = $dados['wscallback'];
$orcamento = $dados['orcamento'];

/* variaveis de retorno do ws */
$wsstatus = 0;
$wsresult = array();

//define o nome do pdf a ser gerado
$pdf_name = sprintf("orcamento_%s.pdf", $orcamento['codigo']);

// url de ws
$client = new nusoap_client($ws);
$client->useHTTPPersistentConnection();

// serial do cliente
$serail_number_cliente = readSerialNumber();

$dados = sprintf("
  <dados>
    <codigo_pedido>%s</codigo_pedido>
  </dados>",
  $orcamento['codigo']);

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

$prds = array();
if (isset($res['resultado']['dados']['pedido'])) {

  if ($res['resultado']['dados']['pedido']['situacao'] != $status) {
    /* monta o xml de retorno */
    $wsstatus = 0;
    $wsresult['wserror'] = "O pedido informado n&atilde;o &eacute; um or&ccedil;amento!";

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
  $valor_total += $res['resultado']['dados']['pedido']['valor_total'];
  $transportadora = $res['resultado']['dados']['pedido']['codigo_transportadora'];
  $status = $res['resultado']['dados']['pedido']['situacao'];
  $valor_frete = $res['resultado']['dados']['pedido']['valor_frete'];
  $bloqueado_sep = $res['resultado']['dados']['pedido']['bloqueado_separacao'];
  $tipo_frete = $res['resultado']['dados']['pedido']['tipo_frete'];
  $endereco_entrega = $res['resultado']['dados']['pedido']['codigo_endereco_entrega'];
  $observacao = $res['resultado']['dados']['pedido']['observacao'];
  $observacao = empty($observacao) ? "" : trim(removerAcentos(utf8_decode($observacao)));

  $data = sprintf("%s/%s/%s", substr($data, 6, 2), substr($data, 4, 2), substr($data, 0, 4));

  // obtem todos os produtos ja existentes no orcamento
  if (key_exists('0', $res['resultado']['dados']['pedido']['lista_produtos']['produto']))
    $prds = $res['resultado']['dados']['pedido']['lista_produtos']['produto'];
  else
    $prds[] = $res['resultado']['dados']['pedido']['lista_produtos']['produto'];
}

else {
  /* monta o xml de retorno */
  $wsstatus = 0;
  $wsresult['wserror'] = "Or&ccedil;amento n&atilde;o encontrado!";

  // grava log
  $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

  returnWS($wscallback, $wsstatus, $wsresult);
}

$ws_func = sprintf("%s/funcionariows.php", $conf['SISTEMA']['saciWS']);
$client_func = new nusoap_client($ws_func);

$dados = sprintf("
  <dados>
    <codigo_funcionario>%d</codigo_funcionario>
  </dados>",
  $funcionario);

// monta os parametros a serem enviados
$params_func = array(
    'crypt' => $serail_number_cliente,
    'dados' => $dados
);

// realiza a chamada de um metodo do ws passando os paramentros
$result_func = $client_func->call('listar', $params_func);
$res_func = XML2Array::createArray($result_func);

$funcionario = "";
if ($res_func['resultado']['sucesso'] && isset($res_func['resultado']['dados']['funcionario'])) {
  $funcionario = $res_func['resultado']['dados']['funcionario'];
  $func_name_create = $funcionario['nome_funcionario'];
  $func_email_create = $funcionario['email'];
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
                    <img src="img/logo_cliente.png" alt="BelLar" style="width: 150px">
                </td>
                <td style="width: 50%; text-align: right">
                    ' . $data . '
                    <br/><br/><span style="font-size: 20px;">Memorial Descritivo #' . $orcamento['codigo'] . '</span>
                </td>
            </tr>
        </table>
    </page_header>
    <page_footer>
        <table class="page_footer">
            <tr>
                <td style="width: 33%; text-align: left;">
                    <strong>' . $empresa_nome . '</strong>
                    <br/>' . $empresa_frase . '
                </td>
                <td style="width: 34%; text-align: center">
                    ' . $empresa_site . '
                    <br/>' . $empresa_tel . '
                </td>
                <td style="width: 33%; text-align: right">
                    <span style="margin-top: -10px; font-size: 10px;">SACI - EAC Software</span>
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
          <td style="width: 50%">' . $func_name_create . '<br/>' . $func_email_create . '</td>
          <td style="width: 50%">' . $observacao . '</td>
        </tr>
      </table>
      <div class="produtos">';

// ordenando o array por ambiente
function cmp($a, $b) {
  if ($a['ambiente'] == $b['ambiente'])
    return 0;
  return ($a['ambiente'] < $b['ambiente']) ? -1 : 1;
}
usort($prds, "cmp");

$page = 1;
$i = 0;

$ambiente_anterior = "";

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

  $ws_amb = sprintf("%s/ambientews.php", $conf['SISTEMA']['saciWS']);
  $client_amb = new nusoap_client($ws_amb);

  $dados = sprintf("
    <dados>
      <codigo_ambiente>%d</codigo_ambiente>
    </dados>",
    $produto['ambiente']);

  // monta os parametros a serem enviados
  $params_amb = array(
      'crypt' => $serail_number_cliente,
      'dados' => $dados
  );

  // realiza a chamada de um metodo do ws passando os paramentros
  $result_amb = $client_amb->call('listar', $params_amb);
  $res_amb = XML2Array::createArray($result_amb);

  $ambiente = "";
  if ($res_amb['resultado']['sucesso'] && isset($res_amb['resultado']['dados']['ambiente'])) {
    $ambiente = $res_amb['resultado']['dados']['ambiente']['nome_ambiente'];
  }

  if ($produto['ambiente'] != $ambiente_anterior) {
    $amb = $produto['ambiente'] > 0 ? $ambiente : 'SEM AMBIENTE';
    $content .= sprintf('<div class="ambiente">Ambiente: #%d - %s</div>', $produto['ambiente'], $amb);
    $ambiente_anterior = $produto['ambiente'];
  }

  $content .= '<div class="produto">';
  $content .= sprintf('<img style="width: 230px; height: 150px;" src="%s" />', $miniatura);
  $content .= sprintf('<strong>%s</strong>', $produto['nome_produto']);
  $content .= '<table class="dados">';
  $content .= sprintf('<tr><td style="width: 70px;"><strong>Código:</strong></td><td><span>%s</span></td></tr>', $produto['codigo_produto']);
  if (!empty($produto['grade_produto']))
    $content .= sprintf('<tr><td><strong>Grade:</strong></td><td><span>%s</span></td></tr>', $produto['grade_produto']);
  $content .= sprintf('<tr><td><strong>Qtde.:</strong></td><td><span>%s</span></td></tr>', ($produto['quantidade'] / 1000));
  $content .= sprintf('<tr><td><strong>Ambiente:</strong></td><td><span>%s</span></td></tr>', $amb);
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

$mail->SetFrom($empresa_email, sprintf("%s - %s", $empresa_nome, $empresa_frase), 1);
$mail->AddAddress($orcamento['func_email'], $orcamento['func_name']);

$mail->Subject = sprintf("SACI Orçamento #%s - %s", $orcamento['codigo'], $observacao);
$mail->MsgHTML("Segue orçamento em anexo.");
$mail->AddAttachment($pdf_dir . $pdf_name);

if (!$mail->Send()) {
  /* monta o xml de retorno */
  $wsstatus = 0;
  $wsresult['wserror'] = "N&atilde;o foi poss&iacute;vel enviar o PDF do or&ccedil;amento por e-mail!<br/>O PDF foi salvo no servidor.";

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