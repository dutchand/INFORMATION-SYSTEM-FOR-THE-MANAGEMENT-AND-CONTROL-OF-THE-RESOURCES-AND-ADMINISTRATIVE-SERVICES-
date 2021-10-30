<?php
require_once(dirname(__FILE__) . "/Controlciroaconn.php");
require_once(dirname(__FILE__) . "/functions.inc.php");
require_once(dirname(__FILE__) . "/XmlSerializer.class.php");


/**
 * This is the main PHP file that process the HTTP parameters, 
 * performs the basic db operations (FIND, INSERT, UPDATE, DELETE) 
 * and then serialize the response in an XML format.
 * 
 * XmlSerializer uses a PEAR xml parser to generate an xml response. 
 * this takes a php array and generates an xml according to the following rules:
 * - the root tag name is called "response"
 * - if the current value is a hash, generate a tagname with the key value, recurse inside
 * - if the current value is an array, generated tags with the default value "row"
 * for example, we have the following array: 
 * 
 * $arr = array(
 * 	"data" => array(
 * 		array("id_pol" => 1, "name_pol" => "name 1"), 
 * 		array("id_pol" => 2, "name_pol" => "name 2") 
 * 	), 
 * 	"metadata" => array(
 * 		"pageNum" => 1, 
 * 		"totalRows" => 345
 * 	)
 * 	
 * )
 * 
 * we will get an xml of the following form
 * 
 * <?xml version="1.0" encoding="ISO-8859-1"?>
 * <response>
 *   <data>
 *     <row>
 *       <id_pol>1</id_pol>
 *       <name_pol>name 1</name_pol>
 *     </row>
 *     <row>
 *       <id_pol>2</id_pol>
 *       <name_pol>name 2</name_pol>
 *     </row>
 *   </data>
 *   <metadata>
 *     <totalRows>345</totalRows>
 *     <pageNum>1</pageNum>
 *   </metadata>
 * </response>
 *
 * Please notice that the generated server side code does not have any 
 * specific authentication mechanism in place.
 */
 
 

/**
 * The filter field. This is the only field that we will do filtering after.
 */
$filter_field = "Mensaje";

/**
 * we need to escape the value, so we need to know what it is
 * possible values: text, long, int, double, date, defined
 */
$filter_type = "text";

/**
 * constructs and executes a sql select query against the selected database
 * can take the following parameters:
 * $_REQUEST["orderField"] - the field by which we do the ordering. MUST appear inside $fields. 
 * $_REQUEST["orderValue"] - ASC or DESC. If neither, the default value is ASC
 * $_REQUEST["filter"] - the filter value
 * $_REQUEST["pageNum"] - the page index
 * $_REQUEST["pageSize"] - the page size (number of rows to return)
 * if neither pageNum and pageSize appear, we do a full select, no limit
 * returns : an array of the form
 * array (
 * 		data => array(
 * 			array('field1' => "value1", "field2" => "value2")
 * 			...
 * 		), 
 * 		metadata => array(
 * 			"pageNum" => page_index, 
 * 			"totalRows" => number_of_rows
 * 		)
 * ) 
 */
function MostrarCorreo() 
{
	global $conn, $filter_field, $filter_type;
	$id_usuario_conectado = $_POST['usuario_conectado']; //BuscarIdusuario($_POST['usuario_conectado']);
	$id_usuario_origen = $_POST['filter']; //BuscarIdusuario($_POST['filter']);
	
	/**
	 * the list of fields in the table. We need this to check that the sent value for the ordering is indeed correct.
	 */
	$fields = array('IDsms','IdUsuarioOrigen','IdUsuarioDestino','Asunto','Mensaje','Fecha');

	$order = "";
	if (@$_REQUEST["orderField"] != "" && in_array(@$_REQUEST["orderField"], $fields)) {
		$order = "ORDER BY " . @$_REQUEST["orderField"] . " " . (in_array(@$_REQUEST["orderDirection"], array("ASC", "DESC")) ? @$_REQUEST["orderDirection"] : "ASC");
	}
	
	//calculate the number of rows in this table
	$rscount = mysql_query("SELECT count(*) AS cnt FROM correo"); 
	$row_rscount = mysql_fetch_assoc($rscount);
	$totalrows = (int) $row_rscount["cnt"];
	
	//get the page number, and the page size
	$pageNum = (int)@$_REQUEST["pageNum"];
	$pageSize = (int)@$_REQUEST["pageSize"];
	
	//calculate the start row for the limit clause
	$start = $pageNum * $pageSize;
    //IdUsuarioOrigen
	//construct the query, using the where and order condition
		
	$Fecha = $_POST['Fecha'];
	
	$CAMPOS_SELECCIONADOS_FROM = "SELECT IDsms, Id_usuario, Asunto, Mensaje, Fecha FROM correo, usuario";
	
	$WHERE_PARA_DOS = "WHERE usuario.Id_usuario = correo.IdUsuarioOrigen AND correo.IdUsuarioDestino = '$id_usuario_conectado'";
	
	$AND_USUARIO = "";
	
	$AND_FECHA = "";
	
	if($id_usuario_origen != "") $AND_USUARIO = "AND correo.IdUsuarioOrigen = '$id_usuario_origen'";
		
	if($Fecha != "") $AND_FECHA = "AND DATE(Fecha) = '$Fecha'";
				
	$query_recordset = "$CAMPOS_SELECCIONADOS_FROM $WHERE_PARA_DOS $AND_USUARIO $AND_FECHA";
		
	//if we use pagination, add the limit clause
	if ($pageNum >= 0 && $pageSize > 0) {	
		$query_recordset = sprintf("%s LIMIT %d, %d", $query_recordset, $start, $pageSize);
	}

	$recordset = mysql_query($query_recordset, $conn);
	
	//if we have rows in the table, loop through them and fill the array
	$toret = array();
	while ($row_recordset = mysql_fetch_assoc($recordset)) {
		array_push($toret, $row_recordset);
	}
	
	//create the standard response structure
	$toret = array(
		"data" => $toret, 
		"metadata" => array (
			"totalRows" => $totalrows,
			"pageNum" => $pageNum
		)
	);
    
    /*$toret = array(
		"data" => array("error" => $id_usuario_conectado) , 
		"metadata" => array ()
	);*/
    
    
	return $toret;
}

/**
 * constructs and executes a sql count query against the selected database
 * can take the following parameters:
 * $_REQUEST["filter"] - the filter value
 * returns : an array of the form
 * array (
 * 		data => number_of_rows, 
 * 		metadata => array()
 * ) 
 */
function CantidadFilas() 
{
	global $conn, $filter_field, $filter_type;

	$where = "";
	if (@$_REQUEST['filter'] != "") {
		$where = "WHERE " . $filter_field . " LIKE " . GetSQLValueStringForSelect(@$_REQUEST["filter"], $filter_type);	
	}

	//calculate the number of rows in this table
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `correo` $where"); 
	$row_rscount = mysql_fetch_assoc($rscount);
	$totalrows = (int) $row_rscount["cnt"];
	
	//create the standard response structure
	$toret = array(
		"data" => $totalrows, 
		"metadata" => array()
	);

	return $toret;
}

/**
 * constructs and executes a sql insert query against the selected database
 * can take the following parameters:
 * $_REQUEST["field_name"] - the list of fields which appear here will be used as values for insert. 
 * If a field does not appear, null will be used.  
 * returns : an array of the form
 * array (
 * 		data => array(
 * 			"primary key" => primary_key_value, 
 * 			"field1" => "value1"
 * 			...
 * 		), 
 * 		metadata => array()
 * ) 
 */
 
function BuscarIdusuario($nombre_usuario)
{
	global $conn;
	$query_recordset = "SELECT Id_usuario FROM usuario WHERE Id_usuario = '$nombre_usuario'";
	$recordset = mysql_query($query_recordset, $conn);
	$id_usuario = mysql_fetch_array($recordset);
	return $id_usuario[0];	
} 
 
function EnviarCorreo($UsuarioDestino,$UsuarioOrigen,$Asunto, $Mensaje) 
{
	global $conn;
	
	$IdUsuarioDestino = BuscarIdusuario($UsuarioDestino);
	$IdUsuarioOrigen = BuscarIdusuario($UsuarioOrigen);
	
	$Fecha = date("y\/m\/d\:H\:i\:s"); 
	
	//build and execute the insert query
	$query_insert = "INSERT INTO `correo` (IdUsuarioOrigen,IdUsuarioDestino,Asunto,Mensaje,Fecha) VALUES ('$IdUsuarioOrigen','$IdUsuarioDestino','$Asunto','$Mensaje','$Fecha')"; 
	$ok = mysql_query($query_insert);
	
	if (!$ok)
	{
		$toret = array(
			"data" => array("error" => mysql_error()), 
			"metadata" => array() );		
	} 
	return $toret;
}
//---------------------------------------------------------------------------------------------

function Buscar_usuarios_sistema()
{
	global $conn;	
	$query_select = "SELECT Id_usuario FROM usuario;";
	$recordset = mysql_query($query_select,$conn);
	//if we have rows in the table, loop through them and fill the array
	$toret = array();
	while ($row_recordset = mysql_fetch_assoc($recordset)) {
		array_push($toret, $row_recordset);
	}
	$toret = array(
		"data" => $toret, 
		"metadata" => array ());
	return $toret;
	
} 
 
/**
 * constructs and executes a sql update query against the selected database
 * can take the following parameters:
 * $_REQUEST[primary_key] - thethe value of the primary key
 * returns : an array of the form
 * array (
 * 		data => deleted_row_primary_key_value, 
 * 		metadata => array()
 * ) 
 */
function EliminarCorreo($IDsms) 
{
	global $conn;

	// check to see if the record actually exists in the database
	$query_recordset = "SELECT * FROM correo WHERE IDsms = '$IDsms'";
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);

	if ($num_rows > 0) 
	{
		$row_recordset = mysql_fetch_assoc($recordset);
		$query_delete = "DELETE FROM `correo` WHERE IDsms = '$IDsms'"; 
			
		$ok = mysql_query($query_delete);
		if ($ok) 
		{
			$toret = array(
				"data" => $row_recordset["IDsms"], 
				"metadata" => array() );
		} 
		else 
		{
			$toret = array(
				"data" => array("error" => mysql_error()), 
				"metadata" => array() );
		}

	}
	else 
	{
		$toret = array(
			"data" => array("error" => "Ninguna fila encontrado"), 
			"metadata" => array() );
	}
	return $toret;
}

/**
 * we use this as an error response, if we do not receive a correct method
 * 
 */
$ret = array(
	"data" => array("error" => "Ninguna operación"), 
	"metadata" => array()
);

/**
 * check for the database connection 
 * 
 * 
 */
if ($conn === false) {
	$ret = array(
		"data" => array("error" => "Error con la base de datos. Ver Configuración !"), 
		"metadata" => array()
	);
} else {
	mysql_select_db($database_conn, $conn);
	/**
	 * simple dispatcher. The $_REQUEST["method"] parameter selects the operation to execute. 
	 * must be one of the values findAll, insert, update, delete, Count
	 */
	// execute the necessary function, according to the operation code in the post variables
	switch (@$_REQUEST["method"]) {
		case "FindAll":
			$ret = MostrarCorreo();
		break;
		case "Insert": 
			$ret = EnviarCorreo($_POST['UsuarioDestino'],$_POST['UsuarioOrigen'],
								$_POST['Asunto'], $_POST['Mensaje']);
		break;
		case "Delete": 
			$ret = EliminarCorreo($_POST['IDsms']);
		break;
		case "Count":
			$ret = CantidadFilas();
		break;
		case "Usuarios":
			$ret = Buscar_usuarios_sistema();
		break;
	}
}


$serializer = new XmlSerializer();
echo $serializer->serialize($ret);
die();
?>
