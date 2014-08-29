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

/* loja padrao */
$loja = $conf["MISC"]["loja"];

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

if (empty($lista['nome_cliente'])) {
// url de ws
    $client = new nusoap_client($wsLista);
    $client->useHTTPPersistentConnection();

    $dados = "<lista>";

    foreach ($lista as $key => $value) {

        if (!empty($value) || is_numeric($value)) {
            $dados .= sprintf("<%s>%s</%s>\n", $key, $value, $key);
        } else {
            $dados .= sprintf("<%s/>\n", $key);
        }
    }

    $dados .= "</lista>";

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
                "lista_produtos" => $lista['lista_produtos']['produto']
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
} else {

    $lista['nome_cliente'] = sprintf("%%%s%%", $lista['nome_cliente']);
    
    $lista['nome_cliente'] = removerAcentos($lista['nome_cliente']);

    //url de ws
    $client = new nusoap_client($wsCliente);
    $client->useHTTPPersistentConnection();

    $dados = sprintf("<dados><nome_cliente>%s</nome_cliente></dados>", $lista['nome_cliente']);

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
                        "lista_produtos" => $list['lista_produtos']['produto']
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

// grava log
$log->addLog(ACAO_RETORNO, $wscallback, $wsresult, SEPARADOR_FIM);

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>
