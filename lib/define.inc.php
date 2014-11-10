<?php

// versao do sistema
define('VERSAO', '1.1');

// Cargo do Funcionario 'empfnc.type' ON (emp.funcao = empfnc.no)
define('EMPTYPE_INDEFINIDO',  0);
define('EMPTYPE_COMPRADOR',   1);
define('EMPTYPE_VENDEDOR',    2);
define('EMPTYPE_COBRADOR',    3);
define('EMPTYPE_CREDIARISTA', 4);
define('EMPTYPE_FATURISTA',   5);
define('EMPTYPE_ANALISTA',    6);
define('EMPTYPE_ESTOQUISTA',  7);
define('EMPTYPE_CAIXA',       8);
define('EMPTYPE_OUTROS',      9);
define('EMPTYPE_GERENTE',     10);
define('EMPTYPE_DIRETOR',     11);
define('EMPTYPE_ADICIONAL',   12);
define('EMPTYPE_ADICIONAL2',  13);
define('EMPTYPE_OPERADORPA',  14);
define('EMPTYPE_MONTADOR',    15);
define('EMPTYPE_SUPERVISOR',  16);
define('EMPTYPE_MOTORISTA',   17);
define('EMPTYPE_AJUDANTE',    18);
define('EMPTYPE_CONFERENTE',  19);

// Status dos pedidos de Cliente 'status'
define('EORDSTATUS_INCLUIDO',       0);
define('EORDSTATUS_ORCAMENTO',      1);
define('EORDSTATUS_RESERVADO',      2);
define('EORDSTATUS_VENDIDO',        3);
define('EORDSTATUS_EXPIRADO',       4);
define('EORDSTATUS_CANCELADO',      5);
define('EORDSTATUS_RESERVAB',       6);
define('EORDSTATUS_TRANSITO',       7);
define('EORDSTATUS_ENTREGAFUTURA',  8);
define('EORDSTATUS_TODOS',          9);

define('EORD_RELACIONADO_LISTA_DE_PRESENTE',  1);

// Arquivo de relacionamento entre funcionario e usuario do SACI
define('FILE_REL_FUNC_USER', 'config/relFuncUser.php');

$extensions_enable = array();
$extensions_enable[] = "jpg";
$extensions_enable[] = "JPG";
$extensions_enable[] = "jpeg";
$extensions_enable[] = "JPEG";

?>