<?php

// Report all errors except E_NOTICE
// This is the default value set in php.ini
error_reporting(E_ALL ^ E_NOTICE);
date_default_timezone_set('America/Sao_Paulo');

require_once WService_DIR . '/lib/function.inc.php';
require_once WService_DIR . '/adodb5/adodb.inc.php';
require_once WService_DIR . '/adodb5/adodb-active-record.inc.php';

class EacConnect {

  private static $db;

  public static function &getInstance() {
    //se não existe uma instancia do objeto
    if (EacConnect::$db === null) {
      $conf = getConfig("DATABASE");
      EacConnect::$db = & ADONewConnection($conf['driver']);
      EacConnect::$db->Connect($conf['hostname'], $conf['username'], $conf['password'], $conf['database']);
      EacConnect::$db->SetFetchMode(ADODB_FETCH_ASSOC);
      EacConnect::$db->Execute("SET SQL_BIG_SELECTS = 1");
      ADOdb_Active_Record::SetDatabaseAdapter(EacConnect::$db);
    }
    return EacConnect::$db;
  }

}

?>
