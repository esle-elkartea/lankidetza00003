<?php // $Id: do_install_db.php,v 1.23.2.4 2006/03/27 11:08:41 Attest sw-libre@attest.es Exp $
// $Id: do_install_db.php,v 1.23.2.3 2005/04/09 04:50:27 ajdonnison Exp $

if ($_POST['mode'] == 'install' && is_file( "../includes/config.php" ) )
 die("Security Check: dotProject seems to be already configured. Communication broken for Security Reasons!");

######################################################################################################################

$baseDir = dirname(dirname(__FILE__));
$baseUrl = ( isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] != 'off') ? 'https://' : 'http://';
$baseUrl .= isset($_SERVER['HTTP_HOST']) ? $_SERVER['HTTP_HOST'] : getenv('HTTP_HOST');
$baseUrl .= isset($_SERVER['SCRIPT_NAME']) ? dirname(dirname($_SERVER['SCRIPT_NAME'])) : dirname(dirname(getenv('SCRIPT_NAME')));

require_once "$baseDir/install/install.inc.php";

$AppUI =& new InstallerUI; // Fake AppUI class to appease the db_connect utilities.

$dbMsg = "";
$cFileMsg = "Not Created";
$dbErr = false;
$cFileErr = false;

$dbtype = trim( dPInstallGetParam( $_POST, 'dbtype', 'mysql' ) );
$dbhost = trim( dPInstallGetParam( $_POST, 'dbhost', '' ) );
$dbname = trim( dPInstallGetParam( $_POST, 'dbname', '' ) );
$dbuser = trim( dPInstallGetParam( $_POST, 'dbuser', '' ) );
$dbpass = trim( dPInstallGetParam( $_POST, 'dbpass', '' ) );
$dbdrop = dPInstallGetParam( $_POST, 'dbdrop', false );
$mode = dPInstallGetParam( $_POST, 'mode', 'upgrade' );
$dbpersist = dPInstallGetParam( $_POST, 'dbpersist', false );
$dobackup = isset($_POST['dobackup']);
$do_db = isset($_POST['do_db']);
$do_db_cfg = isset($_POST['do_db_cfg']);
$do_cfg = isset($_POST['do_cfg']);

// Create a dPconfig array for dependent code
$dPconfig = array(
 'dbtype' => $dbtype,
 'dbhost' => $dbhost,
 'dbname' => $dbname,
 'dbpass' => $dbpass,
 'dbuser' => $dbuser,
 'dbpersist' => $dbpersist,
 'root_dir' => $baseDir,
 'base_url' => $baseUrl
);

// Version array for moving from version to version.
$versionPath = array(
	'1.0.2',
	'2.0-alpha',
	'2.0-beta',
	'2.0',
	'2.0.1'
);

$lastDBUpdate = '';

require_once( "$baseDir/lib/adodb/adodb.inc.php" );
@include_once "$baseDir/includes/version.php";

$db = NewADOConnection($dbtype);

if(!empty($db)) {
  $dbc = $db->Connect($dbhost,$dbuser,$dbpass);
  if ($dbc)
    $existing_db = $db->SelectDB($dbname);
} else { $dbc = false; }


$current_version = $dp_version_major . '.' . $dp_version_minor;
$current_version .= isset($dp_version_patch) ? ".$dp_version_patch" : '';
$current_version .= isset($dp_version_prepatch) ? "-$dp_version_prepatch" : '';

if ($dobackup){

 if( $dbc ) {
  require_once( "$baseDir/lib/adodb/adodb-xmlschema.inc.php" );

  $schema = new adoSchema( $db );

  $sql = $schema->ExtractSchema(true);

  header('Content-Disposition: attachment; filename="dPdbBackup'.date("Ymd").date("His").'.xml"');
  header('Content-Type: text/xml');
  echo $sql;
	exit;
 } else {
  $backupMsg = "ERROR: No Database Connection available! - Backup not performed!";
 }
}

?>
<html>
<head>
 <title>Instalador</title>
 <meta name="Description" content="Instalador">
  <link rel="stylesheet" type="text/css" href="../style/default/main.css">
</head>
<body>
<h1><img src="dp.png" align="middle" alt="dotProject Logo"/>&nbsp;Instalador</h1>
<table cellspacing="0" cellpadding="3" border="0" class="tbl" width="100%" align="left">
<tr class='title'><td>Progreso:</td></tr>
<tr><td><pre>
<?php

if ($dobackup)
 dPmsg($backupMsg);

if ($dbc && ($do_db || $do_db_cfg)) {

 if ($mode == 'install') {

  if ($dbdrop) { 
   dPmsg("Dropping previous database");
   $db->Execute("DROP DATABASE IF EXISTS ".$dbname); 
	 $existing_db = false;
  }

  if (! $existing_db) {
		dPmsg("Creating new Database");
		$db->Execute("CREATE DATABASE ".$dbname);
         $dbError = $db->ErrorNo();
 
         if ($dbError <> 0 && $dbError <> 1007) {
                 $dbErr = true;
                $dbMsg .= "Error. La base de datos no se ha creado! Los datos de la base de datos introducidos probablemente son incorrectos<br>".$db->ErrorMsg()."<br>";

         }
   }
 }

 // For some reason a db->SelectDB call here doesn't work.
 $db->Execute('USE ' . $dbname);
 $db_version = InstallGetVersion($mode, $db);

 if ($mode == 'upgrade') {
  dPmsg("Aplicando actualización");
  $last_version = $db_version['code_version'];
  // Convert the code version to a version string.
  if ($last_version != $current_version) {
    // Check for from and to versions
    $from_key = array_search($last_version, $versionPath);
    $to_key = array_search($current_version, $versionPath);
    for ($i = $from_key; $i < $to_key; $i++) {
      $from_version = str_replace(array('.','-'), '', $versionPath[$i]);
      $to_version = str_replace(array('.','-'), '', $versionPath[$i+1]);
      InstallLoadSql("$baseDir/db/upgrade_{$from_version}_to_{$to_version}.sql");
    }
  } else if (file_exists("$baseDir/db/upgrade_latest.sql")) {
    // Need to get the installed version again, as it should have been
    // updated by the from/to stuff.
    InstallLoadSql("$baseDir/db/upgrade_latest.sql", $db_version['last_db_update']);
  }
 } else {
  dPmsg("Installing database");
  InstallLoadSql("$baseDir/db/dotproject.sql");
  // After all the updates, find the new version information.
  $new_version = InstallGetVersion($mode, $db);
  $lastDBUpdate = $new_version['last_db_update'];
 }

				$dbError = $db->ErrorNo();
        if ($dbError <> 0 && $dbError <> 1007) {
  $dbErr = true;
                $dbMsg .= "A Database Error occurred. Database has probably not been populated completely!<br>".$db->ErrorMsg()."<br>";
        }
 if ($dbErr) {
  $dbMsg = "Instalación incompleta :<br>".$dbMsg;
 } else {
  $dbMsg = "Base de Datos configurada correctamente.<br>";
 }

 $code_updated = '';
 if ($mode == 'upgrade') {
  dPmsg("Aplicando modificaciones");
  // Check for an upgrade script and run it if necessary.
  $to_version = str_replace(array('-', '.'), '', $current_version);
  if ($last_version != $current_version) {
	  if (file_exists("$baseDir/db/upgrade_to_{$to_version}.php")) {
		 include_once "$baseDir/db/upgrade_to_{$to_version}.php";
		 
		 $code_updated = dPupgrade($db_version['code_version'], $current_version, $db_version['last_code_update']);
		}
  } else if (file_exists("$baseDir/db/upgrade_latest.php")) {
   include_once "$baseDir/db/upgrade_latest.php";
   $code_updated = dPupgrade($db_version['code_version'], $current_version, $db_version['last_code_update']);
  } else {
		dPmsg("No data updates required");
	}
 } else {
  include_once "$baseDir/db/upgrade_permissions.php"; // Always required on install.
 }

 dPmsg("Updating version information");
 // No matter what occurs we should update the database version in the dpversion table.
 $sql = "UPDATE dpversion
 SET db_version = '$dp_version_major',
 last_db_update = '$lastDBUpdate',
 code_version = '$current_version',
 last_code_update = '$code_updated'
 WHERE 1";
 $db->Execute($sql);

} else {
	$dbMsg = "No creado";
	if (! $dbc) {
		$dbErr=1;
		$dbMsg .= "<br/>Imposible conectar a la Base de Datos "  . ($db ? $db->ErrorMsg() : '');
	}
}

// always create the config file content

 dPmsg("Creando configuración");
 $config = "<?php \n";
 $config .= "### Copyright (c) 2004, The dotProject Development Team dotproject.net and sf.net/projects/dotproject ###\n";
 $config .= "### All rights reserved. Released under BSD License. For further Information see ./includes/config-dist.php ###\n";
 $config .= "\n";
 $config .= "### CONFIGURATION FILE AUTOMATICALLY GENERATED BY THE DOTPROJECT INSTALLER ###\n";
 $config .= "### FOR INFORMATION ON MANUAL CONFIGURATION AND FOR DOCUMENTATION SEE ./includes/config-dist.php ###\n";
 $config .= "\n";
 $config .= "\$dPconfig['dbtype'] = \"$dbtype\";\n";
 $config .= "\$dPconfig['dbhost'] = \"$dbhost\";\n";
 $config .= "\$dPconfig['dbname'] = \"$dbname\";\n";
 $config .= "\$dPconfig['dbuser'] = \"$dbuser\";\n";
 $config .= "\$dPconfig['dbpass'] = \"$dbpass\";\n";
 $config .= "\$dPconfig['dbpersist'] = " . ($dbpersist ? 'true' : 'false') . ";\n";
 $config .= "\$dPconfig['root_dir'] = \$baseDir;\n";
 $config .= "\$dPconfig['base_url'] = \$baseUrl;\n";
 $config .= "?>";
 $config = trim($config);

if ($do_cfg || $do_db_cfg){
 if ( (is_writable("../includes/config.php")  || ! is_file("../includes/config.php") ) && ($fp = fopen("../includes/config.php", "w"))) {
  fputs( $fp, $config, strlen( $config ) );
  fclose( $fp );
  $cFileMsg = "Archivo de configuración creado correctamente\n";
 } else {
  $cFileErr = true;
  $cFileMsg = "No se ha podido crear el archivo de configuración.\n";
 }
}

//echo $msg;
?>
</pre></td></tr>
<tr><td>
<table cellspacing="0" cellpadding="3" border="0" class="tbl" width="100%" align="left">
        <tr>
            <td class="title" valign="top">Instalación de la Base de Datos:</td>
     <td class="item"><b style="color:<?php echo $dbErr ? 'red' : 'green'; ?>"><?php echo $dbMsg; ?></b><?php if ($dbErr) { ?> <br />
		   Please note that errors relating to dropping indexes during upgrades are <b>NORMAL</b> and do not indicate a problem.
			 <?php } ?>
			 </td>
         <tr>
  <tr>
            <td class="title">Creación del archivo de configuración:</td>
     <td class="item" align="left"><b style="color:<?php echo $cFileErr ? 'red' : 'green'; ?>"><?php echo $cFileMsg; ?></b></td>
  </tr>
<?php if(($do_cfg || $do_db_cfg) && $cFileErr){ ?>
 <tr>
     <td class="item" align="left" colspan="2">The following Content should go to ./includes/config.php. Create that text file manually and copy the following lines in by hand. Delete all empty lines and empty spaces after '?>' and save. This file should be readable by the webserver.</td>
  </tr>
         <tr>
            <td align="center" colspan="2"><textarea class="button" name="dbhost" cols="100" rows="20" title="Content of config.php for manual creation." /><?php echo $msg.$config; ?></textarea></td>
         </tr>
<?php } ?>
 <tr>
     <td class="item" align="center" colspan="2"><br/><b><a href="<?php echo $baseUrl.'/index.php?m=system&a=systemconfig';?>">Login and Configure the dotProject System Environment</a></b></td>
  </tr>
<?php if ($mode == 'install') { ?>
	<!--<tr>
		<td class="item" align="center" colspan="2"><p>The Administrator login has been set to <b>admin</b> with a password of <b>passwd</b>. It is a good idea to change this password when you first log in</p></td>
	</tr>-->
<?php } ?>
        </table>
</td></tr></table>
</body>
</html>
