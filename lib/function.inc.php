<?php

error_reporting(E_ERROR);
date_default_timezone_set('America/Sao_Paulo');

/**
 * Monta o json de resposta e executa a funÁ„o de retorno
 *
 * @param String $wscallback
 * @param int $wsstatus
 * @param array $wsresult
 */
function returnWS($wscallback, $wsstatus, $wsresult) {
  /* monta o retorno */
  $retorno = array();
  $retorno['wsstatus'] = $wsstatus;

  if (key_exists('wserror', $wsresult))
    $retorno['wserror'] = $wsresult['wserror'];
  else
    $retorno['wsresult'] = $wsresult;

  /* escapa aspas simples e duplas */
  scapeQuotes($retorno);

  /* converte o array para json */
  $retorno = json_encode($retorno);

  //header('Access-Control-Allow-Origin: "*"');
  echo sprintf("%s('%s');", $wscallback, $retorno);
  exit;
}

function scapeQuotes(&$arr) {
  foreach ($arr as $key => &$val) {
    if (is_array($val))
      scapeQuotes($val);
    else
      $val = str_replace(array("'", '"'), array("&#039;", "&quot;"), $val);
  }
}

function mergeProdutosPedido($produtos) {

  $produtos_aux = $produtos;
  $q = count($produtos);

  for ($i = 0; $i < $q; $i++) {

    for ($j = 0; $j < $q; $j++) {

      $prdno = $produtos[$i]['codigo_produto'];
      $grade = $produtos[$i]['grade_produto'];
      $ambiente = $produtos[$i]['ambiente'];
      $prdno_aux = $produtos_aux[$j]['codigo_produto'];
      $grade_aux = $produtos_aux[$j]['grade_produto'];
      $ambiente_aux = $produtos_aux[$j]['ambiente'];

      if (($i != $j) && ($prdno == $prdno_aux) && ($grade == $grade_aux) && ($ambiente == $ambiente_aux)) {

        $produtos[$i]['quantidade'] += $produtos_aux[$j]['quantidade'];

        unset($produtos_aux[$j]);
        unset($produtos[$j]);
      }
    }
  }
  return $produtos;
}

function mergeProdutosLista($produtos) {

  $produtos_aux = $produtos;
  $q = count($produtos);

  for ($i = 0; $i < $q; $i++) {

    for ($j = 0; $j < $q; $j++) {

      $prdno = $produtos[$i]['codigo_produto'];
      $grade = $produtos[$i]['grade'];
      $prdno_aux = $produtos_aux[$j]['codigo_produto'];
      $grade_aux = $produtos_aux[$j]['grade'];

      if (($i != $j) && ($prdno == $prdno_aux) && ($grade == $grade_aux)) {

        $produtos[$i]['quantidade_listada'] += $produtos_aux[$j]['quantidade_listada'];

        unset($produtos_aux[$j]);
        unset($produtos[$j]);
      }
    }
  }
  return $produtos;
}

/**
 * Grava um vetor em um arquivo INI
 */
function setIni($content) {
  $linhas = '';
  foreach ($content as $key => $content) {
    if (is_array($content)) {
      $linhas .= sprintf("[%s]\n", $key);
      $linhas .= setIni($content);
    } else
      $linhas .= sprintf("%s = %s\n", $key, $content);
  }
  return $linhas;
}

/**
 * retorna o arquivo de configura√ß√£o
 *
 * @param <type> $modulo
 * @param <type> $file
 * @return array()
 */
function getConfig($modulo = false, $file = false) {
  if (!$file)
    $conf = parse_ini_file(WService_DIR . "/config/config.php", true);
  else
    $conf = parse_ini_file(WService_DIR . $file, true);
  return (is_null($modulo) || !isset($conf[$modulo])) ? $conf : $conf[$modulo];
}

/**
 * seta o relacionamento no arquivo de configura√ß√£o
 *
 * @param <type> $key
 * @param <type> $val
 * @param <type> $modulo
 */
function setConfigRelFuncUser($key, $val, $modulo = false) {
  $file = WService_DIR . FILE_REL_FUNC_USER;

  $conf = parse_ini_file($file, true);

  if (!is_null($modulo)) {
    $conf[$modulo][$key] = $val;
    ksort($conf[$modulo]);
  } else {
    $conf[$key] = $val;
  }

  ksort($conf);

  $content = sprintf(';<?php die(); /* DO NOT REMOVE THIS LINE! SERIOUS SECURITY RISK IF REMOVED! */ ?>%s', chr(10));
  $content .= sprintf('; Relacionamento entre funcionario e usuario do SACI%s%s', chr(10) . chr(10), setIni($conf));

  file_put_contents($file, $content);
}

/**
 *
 * @param type $fi
 * @param type $key
 * @param type $keylen
 * @param type $cipher
 * @param type $clen
 * @param int $coff
 * @return type
 */
function cr4de_decrypt($fi, $key, $keylen, &$cipher, &$clen, &$coff) {

  $ch = -1;

  if ($clen == 0) {

    $cipher = fread($fi, $keylen);
    $clen = strlen($cipher);
    $coff = 0;
  }

  if ($clen > 0) {

    $ch = $cipher[$coff] ^ $key[$coff];
    $coff++;
    --$clen;
  }

  return $ch;
}

/* * ****************************
 *       cr4de                 *
 * ******************************
  decrypt a file, better than DES
  Usage: cr4en    keyvalue infile outfile */

function cr4de($kkey, $fi_name, $fo_name) {

  $runct = 0;
  $clen = 0;
  $coff = 0;
  $true_kkey = $kkey;

  $cipher = "";
  $key = $true_kkey;
  $keylen = strlen($key);

  if ($fi = @fopen($fi_name, "rb")) {

    if ($fo = @fopen($fo_name, "wb")) {

      /* scan the file */
      for (;;) {

        $ch = cr4de_decrypt($fi, $key, $keylen, $cipher, $clen, $coff);

        if ($ch < 0 && $runct == 0)
          break;

        /* first comes the repeat counter; do not write yet */
        if ($runct == 0)
          $runct = Ord($ch);

        /* must be a character now */
        else {

          /* write as many times as the repeat asks for */
          while ($runct--)
            if (!fputs($fo, $ch))
              return false;

          /* reset the run counter so that we know the next
            element coming in will be a run lenght counter */
          $runct = 0;
        }
      } /* end for */

      /* close file */
      fclose($fo);
    } /* end if */

    fclose($fi);
  }
}

/**
 *
 * @param type $file_in
 * @param type $file_out
 * @param type $key
 */
function createDdp($file_in, $file_out, $key = "Q'mHp7|&# @~oM.?={+f:)g`lr(&6@!a") {
  cr4de($key, $file_in, $file_out);
}

/**
 *
 * @param type $line
 * @param string $file1
 * @param string $file2
 * @return type
 */
function readCrpt($line, $file1 = "", $file2 = "") {
  $conf = getConfig();
  $cr = sprintf("%s/crypt.dat", $conf['SISTEMA']['dirCrypt']);
  $kcr = sprintf("%s/kcrypt.dat", $conf['SISTEMA']['dirCrypt']);

  /* se o arquivo 1 nao for escolhido, busca o padrao */
  if ($file1 == "") {
    $file1 = $cr;
  }

  /* se o arquivo 2 nao for escolhido, busca o padrao */
  if ($file2 == "") {
    $file2 = $kcr;
  }

  /* arquivos temporarios */
  $tmpk = "/tmp/.webCrk" . time() . rand() . ".tmp";
  $tmpc = "/tmp/.webCr" . time() . rand() . ".tmp";

  /* cria o kcrypt */
  createDdp($file2, $tmpk);

  /* abre o arquivo para leitura */
  $fp = @fopen($tmpk, "r");

  /* nao conseguiu ler arquivo com a chave do crypt */
  if (!$fp)
    return false;

  /* pega os dados */
  $k = trim(fgets($fp, 1024));

  fclose($fp);
  @unlink($tmpk);

  /* le as informacoes do arquivos */
  createDdp($file1, $tmpc, $k);

  /* le parametros */
  $fp = @fopen($tmpc, "r");

  if (!$fp)
    return false;

  /* retorna o conteudo da linha solicitada */
  for ($i = 0; $i < $line; $i++)
    $content = trim(fgets($fp, 1024));

  fclose($fp);
  @unlink($tmpc);

  return $content;
}

/**
 *
 * @return type
 */
function readSerialNumber() {

  /* nome de serie do crypt */
  $serialNumber = readCrpt(3);

  /* extrai o numero de serie */
  for ($i = 0; $i < 3; $i++)
    $serialNumber = trim(substr($serialNumber, strpos($serialNumber, " ")));

  return $serialNumber;
}

/**
 * retorna o codigo do produto formatado para o padrao do SACI:
 * - se inteiro, alinhado a direita
 * - se string, alinhado a esquerda
 *
 * @param <type> $prdno
 * @return char(16)
 */
function format_prdno($prdno) {
  $prdno = mysql_real_escape_string(trim($prdno));
  $sizeOfPrdNo = 16;

  if (preg_match("/^\d+$/", $prdno))
    $prdno = str_pad($prdno, $sizeOfPrdNo, ' ', STR_PAD_LEFT);
  else
    $prdno = str_pad($prdno, $sizeOfPrdNo, ' ', STR_PAD_RIGHT);

  return($prdno);
}

/**
 * retorna o codigo do cpf_cgc formatado para o padrao Brasileiro:
 * - se tamanho igual 11 posi√ß√µes √© um cpf
 * - se tamanho igual 14 posi√ß√µes √© um cgc
 *
 * @param <type> $cpfCgc
 * @return char(14 or 15)
 */
function format_CpfCgc($cpfCgc) {

  //$cpfCgc = ereg_replace("[^0-9]", "", $cpfCgc);
  $cpfCgc = preg_replace("#[^0-9]#", "", $cpfCgc);

  //Example: 38.743.738/0001-11
  if (strlen($cpfCgc) == 14) {
    $p1 = substr($cpfCgc, 0, 2);
    $p2 = substr($cpfCgc, 2, 3);
    $p3 = substr($cpfCgc, 5, 3);
    $p4 = substr($cpfCgc, 8, 4);
    $p5 = substr($cpfCgc, 12, 2);
    $cpfCgc = sprintf("%s.%s.%s/%s-%s", $p1, $p2, $p3, $p4, $p5);
  }
  //Example: 562.341.556-34
  elseif (strlen($cpfCgc) == 11) {
    $p1 = substr($cpfCgc, 0, 3);
    $p2 = substr($cpfCgc, 3, 3);
    $p3 = substr($cpfCgc, 6, 3);
    $p4 = substr($cpfCgc, 9, 2);
    $cpfCgc = sprintf("%s.%s.%s-%s", $p1, $p2, $p3, $p4);
  }

  return $cpfCgc;
}

/**
 *
 * @param type $value
 */
function normalizeChars($value) {

  $normalizeChars = array(
      '≈†' => 'S', '≈°' => 's', '√ê' => 'D', '≈Ω' => 'Z', '≈æ' => 'z', '√Ä' => 'A', '√Å' => 'A', '√Ç' => 'A', '√É' => 'A', '√Ñ' => 'A',
      '√Ö' => 'A', '√Ü' => 'A', '√á' => 'C', '√à' => 'E', '√â' => 'E', '√ä' => 'E', '√ã' => 'E', '√å' => 'I', '√ç' => 'I', '√é' => 'I',
      '√è' => 'I', '√ë' => 'N', '√í' => 'O', '√ì' => 'O', '√î' => 'O', '√ï' => 'O', '√ñ' => 'O', '√ò' => 'O', '√ô' => 'U', '√ö' => 'U',
      '√õ' => 'U', '√ú' => 'U', '√ù' => 'Y', '√û' => 'B', '√ü' => 'S', '√†' => 'a', '√°' => 'a', '√¢' => 'a', '√£' => 'a', '√§' => 'a',
      '√•' => 'a', '√¶' => 'a', '√ß' => 'c', '√®' => 'e', '√©' => 'e', '√™' => 'e', '√´' => 'e', '√¨' => 'i', '√≠' => 'i', '√Æ' => 'i',
      '√Ø' => 'i', '√∞' => 'o', '√±' => 'n', '√≤' => 'o', '√≥' => 'o', '√¥' => 'o', '√µ' => 'o', '√∂' => 'o', '√∏' => 'o', '√π' => 'u',
      '√∫' => 'u', '√ª' => 'u', '√Ω' => 'y', '√Ω' => 'y', '√æ' => 'b', '√ø' => 'y', '∆í' => 'f', '|' => '', '#' => '');

  return strtr($value, $normalizeChars);
}

/* * ************************
 *          setMask        *
 * **************************
  Retorna a mascara para o bit em questao. */

function setMask($bitno) {
  return (1 << $bitno);
}

/**
 *
 * @param type $bits
 * @param type $bitno
 * @return type
 */
function bitOk($bits, $bitno) {
  /* mascara binaria para o bit */
  $mask = setMask($bitno);

  /* verifica se o bit esta ativo ou nao */
  return ((($bits & $mask) == $mask) ? 1 : 0);
}

/* * ************************
 *          setBit         *
 * **************************
  Altera o valor do bit (bitno) presente no
  argumento bits para 0 (set == 0) ou para
  1 (set == 1). */

function setBit($bits, $bitno, $set) {
  /* mascara binaria para o bit */
  $mask = setMask($bitno);

  /* altera o valor do bit para 0 */
  if (!$set)
    return ((($bits | $mask) - $mask));

  /* altera o valor do bit para 1 */
  else
    return ($bits | $mask);
}

/**
 *
 * @param type $time
 * @return type
 */
function timeToSecond($time = "") {
  if (empty($time))
    $time = date("H:i:s");

  /* quebra as partes da hora */
  $time = explode(":", $time);

  $sec = intval(trim($time[0])) * 3600;
  $sec += intval(trim($time[1])) * 60;
  $sec += intval(trim($time[2]));

  return $sec;
}

function encryptPswdLogin($pswd) {
  $ascii = 0;
  $j = 0;
  $cript = "";
  $senha = sprintf("%-8.8s", $pswd);
  $frase = "&%#!@$*+";

  // calcula o caracter criptografado
  for ($i = 0; $i < strlen($senha); $i++) {
    $ascii = ord($senha[$i]) - 33;
    $ascii += (($j++ % 3) * 3);
    $ascii += ord($frase[$i]);
    $cript .= chr($ascii);
  }

  return $cript;
}

/**
 * Formata o barcode de acordo com o cliente sendo utilizado
 *
 * @param String $barcode
 * @return FormatedString
 */
function formatBarCode($barcode) {
  $barcode = trim($barcode);
  $sizeOfBarCode = 16;

  if (preg_match("/^\d+$/", $barcode))
    $barcode = str_pad($barcode, $sizeOfBarCode, ' ', STR_PAD_LEFT);
  else
    $barcode = str_pad($barcode, $sizeOfBarCode, ' ', STR_PAD_RIGHT);

  return($barcode);
}

function getPrdBarCode2Codigo($barcode, $db, $conf) {

  $conf = $conf['DATABASE'];
  $db->Connect($conf['hostname'], $conf['username'], $conf['password'], $conf['database']);

  $where = sprintf("barcode='%s'", formatBarCode($barcode));
  $where22 = sprintf("barcode48='%s'", formatBarCode(substr($barcode, 0, 22)));

  //alterado do sql_prdbar
  $SqlQuery = sprintf(" (SELECT prdbar.prdno,
                                prdbar.grade,
                                prd.name,
                                prd.mfno,
                                prd.mfno_ref,
                                prd.taxno,
                                prd.mult,
                                prd.class,
                                prd.grade_l,
                                prd.discount,
                                prd.clno,
                                prd.weight,
                                prd.weight_g
                          FROM prdbar
                            LEFT JOIN
                               prd ON (prd.no = prdbar.prdno)
                          WHERE prdbar.%s
                        )
                       UNION
                        (SELECT prdbar.prdno,
                                prdbar.grade,
                                prd.name,
                                prd.mfno,
                                prd.mfno_ref,
                                prd.taxno,
                                prd.mult,
                                prd.class,
                                prd.grade_l,
                                prd.discount,
                                prd.clno,
                                prd.weight,
                                prd.weight_g
                          FROM prdbar
                            LEFT JOIN
                               prd ON (prd.no = prdbar.prdno)
                          WHERE prdbar.bits&1=1
                            AND prdbar.%s
                        )
                       LIMIT 1", $where, $where22);
  $result = $db->GetRow($SqlQuery);

  //echo $db->ErrorMsg();

  if ($result && is_array($result) && !empty($result)) {
    return ($result);
  }

  //tirado de 'sql_prdFromBarcode'
  $SqlQuery = sprintf("SELECT no as prdno,
                              prd.name,
                              prd.mfno,
                              prd.mfno_ref,
                              prd.taxno,
                              prd.mult,
                              prd.class,
                              prd.grade_l,
                              prd.discount,
                              prd.clno,
                              prd.weight,
                              prd.weight_g
                       FROM prd
                       WHERE %s", $where);
  $result = $db->GetRow($SqlQuery);

  if ($result && is_array($result) && !empty($result)) {
    //grade nao pode ser vazia
    $result['grade'] = '';
    return ($result);
  }

  return false;
}

function removerAcentos($txt) {
  if (function_exists('selective_utf8_decode')) {
    $txt = selective_utf8_decode($txt);
  }
  $txt = preg_replace("/[¡¿¬√]/", "A", $txt);
  $txt = preg_replace("/[·‡‚„™]/", "a", $txt);
  $txt = preg_replace("/[…» ]/", "E", $txt);
  $txt = preg_replace("/[ÈËÍ]/", "e", $txt);
  $txt = preg_replace("/[ÕÃŒ]/", "I", $txt);
  $txt = preg_replace("/[ÌÏÓ]/", "i", $txt);
  $txt = preg_replace("/[”“‘’]/", "O", $txt);
  $txt = preg_replace("/[ÛÚÙı∫]/", "o", $txt);
  $txt = preg_replace("/[⁄Ÿ€]/", "U", $txt);
  $txt = preg_replace("/[˙˘˚]/", "u", $txt);
  $txt = str_replace("«", "C", $txt);
  $txt = str_replace("Á", "c", $txt);
  return ($txt);
}

function ordenarClientes($a, $b) {
  return $a["cliente_nome"] > $b["cliente_nome"];
}

function ordenarFabricantes($a, $b) {
  return $a["nome_fabricante"] > $b["nome_fabricante"];
}

?>
