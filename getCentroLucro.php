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
$ws = sprintf("%s/categoriaws.php", $conf["SISTEMA"]["saciWS"]);
 
/* centros de lucro */
$centro_lucro = explode(",", str_replace(" ", "", $conf["MISC"]["centrosLucro"]));
$centro_lucro = array_filter($centro_lucro);

/* variaveis recebidas na requisicao
 * {Array}: dados(wscallback, produto(codigo))
 */
$dados = $_REQUEST["dados"];
$wscallback = $dados["wscallback"];
$categoria = $dados["categoria"];

/* variaveis de retorno do ws */
$wsstatus = 0;
$wsresult = array();

// url de ws
$client = new nusoap_client($ws);
$client->useHTTPPersistentConnection();

// serial do cliente
$serail_number_cliente = readSerialNumber();

$dados = sprintf("<categoria>\n\t<codigo_categoria>%s</codigo_categoria><nome_categoria>%%%s%%</nome_categoria>\n</categoria>", 
        $categoria["codigo_categoria"], $categoria["nome_categoria"]);

// grava log
$log->addLog(ACAO_REQUISICAO, "getCentroLucro", $dados, SEPARADOR_INICIO);

// monta os parametros a serem enviados
$params = array(
    "crypt" => $serail_number_cliente,
    "dados" => $dados
);

// realiza a chamada de um metodo do ws passando os paramentros
$result = $client->call("listar", $params);

// grava log
$log->addLog(ACAO_RETORNO, "dadosCentroLucro", $result);

// converte o xml em um array
$res = XML2Array::createArray($result);

if ($res["resultado"]["sucesso"] && isset($res["resultado"]["dados"]["categoria"])) {

  $categorias = array();
  $categorias_arr = array();

  if(key_exists("0", $res["resultado"]["dados"]["categoria"]))
    $categorias = $res["resultado"]["dados"]["categoria"];
  else
    $categorias[] = $res["resultado"]["dados"]["categoria"];

  $wsstatus = 1;
  $wsresult = array();

  // percorre todas as categorias para montar o json de retorno
  foreach($categorias as $categoria){
    
    // ajusta a quantidade de caracteres do codigo da categoria (sempre 6 digitos)
    $categoria["codigo_categoria"] = str_pad($categoria["codigo_categoria"], 6, "0", STR_PAD_LEFT);
    
    // obtem os 2 digitos do grupo
    $grupo = substr($categoria["codigo_categoria"], 0, 2);
    
    // obtem os 2 digitos do departamento
    $departamento = substr($categoria["codigo_categoria"], 2, 2);
    
    // obtem os 2 digitos do centro de lucro
    $centroLucro = substr($categoria["codigo_categoria"], 4, 2);

    // ajusta o nome da categoria
    $nome_categoria = explode("/", $categoria["nome_categoria"]);
    
//    $ok = true;
//    
//    // verifica nas configuracoes definidas se e para eniar este centro de lucro
//    // para o aplicativo    
//    if(is_array($centro_lucro) && count($centro_lucro) > 0){
//      
//      $item = $grupo . $departamento . $centroLucro;
//
//      if(!in_array($item, $centro_lucro)){
//
//        $item = $grupo . $departamento . "00";
//
//        if(!in_array($item, $centro_lucro)){
//
//          $item = $grupo . "0000";
//
//          if(!in_array($item, $centro_lucro))
//            $ok = false;
//        }
//      }
//    }
//
//    if(!$ok)
//      continue;
    
    // variaveis de controle
    $existsGrpOk = false;
    $existsDptOk = false;
    $existsClOk = false;

    /**
     * Para cada categoria vinda do ws eh necessario percorrer o array auxiliar 
     * para verificar se a categoria ja existe ou nao.
     */
    foreach($categorias_arr["grupos"] as &$grp){
      
      // verifica se o grupo da categoria ja existe no array auxiliar
      if($grp["codigo"] == $grupo){
        $existsGrpOk = true;
        
        // caso exista percorre os departamentos do grupo
        foreach($grp["departamentos"] as &$dpt){
          
          // verifica se o departamento da categoria ja existe no array auxiliar
          if($dpt["codigo"] == $departamento){
            $existsDptOk = true;
            
            // caso exista percorre os centros de lucro do grupo
            foreach($dpt["centrolucros"] as &$cl){
              
              // verifica se o centro de lucro da categoria ja existe no array auxiliar
              if($cl["codigo"] == $centroLucro)
                $existsClOk = true;
            }
            
            // caso o centro de lucro nao exista no array auxiliar adiciona-o
            if(!$existsClOk && $centroLucro > 0){
              
              // cria o centro de lucro para esta categoria
              $newCl = array(
                  "codigo" => $centroLucro, 
                  "nome" => $nome_categoria[2],
                  "full" => $grupo . $departamento . $centroLucro
              );

              // adiciona o centro de lucro ao departamento
              $dpt["centrolucros"][] = $newCl;
            }
          }
        }
        
        // caso o departamento nao exista no array auxiliar adiciona-o
        if(!$existsDptOk && $departamento > 0){
          
          // cria o departamento para esta categoria            
          $newDpt = array(
            "codigo" => $departamento, 
            "nome" => $nome_categoria[1],
            "full" => $grupo . $departamento . "00",
            "centrolucros" => array()
          );

          // verifica se esta categoria possui informacao de um centro de lucro valido
          if($centroLucro > 0){

            // cria o centro de lucro para esta categoria
            $newCl = array(
              "codigo" => $centroLucro, 
              "nome" => $nome_categoria[2],
              "full" => $grupo . $departamento . $centroLucro
            );

            // adiciona o centro de lucro ao departamento criado
            $newDpt["centrolucros"][] = $newCl;
          }          

          // adiciona o departamento ao grupo
          $grp["departamentos"][] = $newDpt;
        }
      }
    }
    
    /**
     * Caso a categoria ainda nao exista no array auxiliar
     * realizamos a inclusao dela
     */
    if(!$existsGrpOk && $grupo > 0){

      // cria o grupo para esta categoria
      $newGrp = array(
        "codigo" => $grupo, 
        "nome" => $nome_categoria[0],
        "full" => $grupo . "0000",
        "departamentos" => array()
      );

      // verifica se esta categoria possui informacao de um departamento valido
      if($departamento > 0){

        // cria o departamento para esta categoria
        $newDpt = array(
          "codigo" => $departamento, 
          "nome" => $nome_categoria[1],
          "full" => $grupo . $departamento . "00",
          "centrolucros" => array()
        );

        // verifica se esta categoria possui informacao de um centro de lucro valido
        if($centroLucro > 0){

          // cria o centro de lucro para esta categoria
          $newCl = array(
            "codigo" => $centroLucro, 
            "nome" => $nome_categoria[2],
            "full" => $grupo . $departamento . $centroLucro
          );

          // adiciona o centro de lucro ao departamento criado
          $newDpt["centrolucros"][] = $newCl;
        }

        // adiciona o departamento ao grupo criado
        $newGrp["departamentos"][] = $newDpt;
      }

      // adiciona o grupo criado ao array auxiliar
      $categorias_arr["grupos"][] = $newGrp;      
    }
  }
  
  $wsresult = $categorias_arr;
}

else{
  /* monta o xml de retorno */
  $wsstatus = 0;
  $wsresult["wserror"] = "Nenhum centro de lucro encontrado!";

  // grava log
  $log->addLog(ACAO_RETORNO, "", $wsresult, SEPARADOR_FIM);

  returnWS($wscallback, $wsstatus, $wsresult);
}

// grava log
$log->addLog(ACAO_RETORNO, $wscallback, $wsresult, SEPARADOR_FIM);

/* retorna o resultado */
returnWS($wscallback, $wsstatus, $wsresult);
?>
