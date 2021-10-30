<?php
require_once(dirname(__FILE__) . "/Controlciroaconn.php");
require_once(dirname(__FILE__) . "/functions.inc.php");
require_once(dirname(__FILE__) . "/XmlSerializer.class.php");
require_once(dirname(__FILE__) . "/Ccentro.php");
require_once(dirname(__FILE__) . "/CPlato.php");
require_once(dirname(__FILE__) . "/TAuditoria.php");


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
$filter_field = "Id_centro";

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
function findAll() {
	global $conn, $filter_field, $filter_type;

	/**
	 * the list of fields in the table. We need this to check that the sent value for the ordering is indeed correct.
	 */
	$fields = array('Id_carta_menu','Id_plato','Id_centro','precioventa','normatecnica','unidad_medida','cantidad','fecha','total');

	$where = "";
	if (@$_REQUEST['filter'] != "") {
		$where = "WHERE " . $filter_field . " LIKE " . GetSQLValueStringForSelect(@$_REQUEST["filter"], $filter_type);	
	}

	$order = "";
	if (@$_REQUEST["orderField"] != "" && in_array(@$_REQUEST["orderField"], $fields)) {
		$order = "ORDER BY " . @$_REQUEST["orderField"] . " " . (in_array(@$_REQUEST["orderDirection"], array("ASC", "DESC")) ? @$_REQUEST["orderDirection"] : "ASC");
	}
	$lugar=$_REQUEST["centro"];
	$fecha = $_REQUEST["fecha"];
   
	
	//calculate the number of rows in this table
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `carta_menu` $where"); 
	$row_rscount = mysql_fetch_assoc($rscount);
	$totalrows = (int) $row_rscount["cnt"];
	
	//get the page number, and the page size
	$pageNum = (int)@$_REQUEST["pageNum"];
	$pageSize = (int)@$_REQUEST["pageSize"];
	
	//calculate the start row for the limit clause
	$start = $pageNum * $pageSize;

	//construct the query, using the where and order condition
	
    $query_recordset = "SELECT  cm.Id_carta_menu,cm.Id_centro,cm.Id_plato,cm.cantidad,cm.fecha,cm.total,pl.precioventa,pl.normatecnica,pl.unidad_medida
         FROM carta_menu cm, plato pl where cm.Id_plato = pl.Id_plato and  (Id_centro='$lugar' and fecha='$fecha') $where $order";
	
	//$query_recordset = "SELECT Id_carta_menu,Id_centro,Id_plato,fecha FROM `carta_menu` $where $order";
	
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
function rowCount() {
	global $conn, $filter_field, $filter_type;

	$where = "";
	if (@$_REQUEST['filter'] != "") {
		$where = "WHERE " . $filter_field . " LIKE " . GetSQLValueStringForSelect(@$_REQUEST["filter"], $filter_type);	
	}

	//calculate the number of rows in this table
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `carta_menu` $where"); 
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
 
 
function Existe($plato,$code)
{	
	if($plato)
	{	
		global $conn;
		$query_select_usuario = "SELECT * FROM carta_menu WHERE Id_carta_menu = '$code' and Id_plato ='$plato'";	
		$recordset = mysql_query($query_select_usuario, $conn);
		$num_rows = mysql_num_rows($recordset);
		return $num_rows;	
	}
	return 0;
}

//------------------------------------------------------------------------------------

function precio($plato)
{	
	if($plato)
	{	
		global $conn;
		$query_select_usuario = "SELECT precioventa FROM plato WHERE  Id_plato ='$plato'";	
		$query_result = mysql_query($query_select_usuario, $conn);
		$saldo = mysql_fetch_array($query_result);
	    return $saldo[0];		
		
	}
	return 0;
}
 //-----------------------------------------------------------------------------------
function insert() {
	global $conn;
    	$auditoria = new TAuditoria;
    
	//build and execute the insert query
	$plato = $_REQUEST["Id_plato"];
	$code = $_REQUEST["cod_carta"];
	$cant = $_REQUEST["cant"];
	$total_plato= precio($plato);
	
	if (Existe($plato,$code) > 0) 
	{
	   $toret = array(
			"data" => array("error" => "Lo sentimos ya existe el plato: $plato"), 
			"metadata" => array() );
	}
	else 
	{
		$total = $cant * $total_plato;
	$query_insert = sprintf("INSERT INTO `carta_menu` (Id_carta_menu,Id_centro,Id_plato,cantidad,total,fecha) VALUES (%s,%s,%s,%s,'$total',%s)" ,			
	        GetSQLValueString($_REQUEST["cod_carta"], "text"), # 
	        GetSQLValueString($_REQUEST["Id_centro"], "text"), # 
			GetSQLValueString($_REQUEST["Id_plato"], "text"), # 
			GetSQLValueString($_REQUEST["cant"], "text"),
			GetSQLValueString($_REQUEST["fecha"], "text")
	);
	$ok = mysql_query($query_insert);
	
	if ($ok) {
		// return the new entry, using the insert id
		
  	    $auditoria->agregar_a_auditoria($_REQUEST["username"],"Agregó una nueva carta menú " .$_REQUEST["cod_carta"]." al sistema.","0");
		$toret = array(
			"data" => array(
				array(
					"Id_carta_menu" => mysql_insert_id(), 
					"Id_centro" => $_REQUEST["Id_centro"], # 
					"Id_plato" => $_REQUEST["Id_plato"], # 
					"fecha" => $_REQUEST["fecha"]# 
				)
			), 
			"metadata" => array()
		);
	} else {
		// we had an error, return it
		$toret = array(
			"data" => array("error" => mysql_error()), 
			"metadata" => array()
		);
	}
	
	}
	return $toret;
}
//----------------------------------------------------------------------------
function modify_carta() {
	global $conn;

	//build and execute the insert query
	$plato = $_REQUEST["Id_plato"];
	$code = $_REQUEST["cod_carta"];
	$auditoria = new TAuditoria;
	$cant = $_REQUEST["cant2"];
	$total_plato= precio($plato);
	
	$total = $cant * $total_plato;
	if (Existe($plato,$code) > 0) 
	{
	   $toret = array(
			"data" => array("error" => "Lo sentimos ya existe el plato: $plato"), 
			"metadata" => array() );
	}
	else 
	{
	$query_insert = sprintf("INSERT INTO `carta_menu` (Id_carta_menu,Id_centro,Id_plato,cantidad,total,fecha) VALUES (%s,%s,%s,%s,'$total',%s)" ,			
	        GetSQLValueString($_REQUEST["cod_carta"], "text"), # 
	        GetSQLValueString($_REQUEST["Id_centro"], "text"), # 
			GetSQLValueString($_REQUEST["Id_plato"], "text"), # 
			GetSQLValueString($_REQUEST["cant2"], "text"), # 
			GetSQLValueString($_REQUEST["fecha2"], "text") # 
			
			
	);
	$ok = mysql_query($query_insert);
	
	if ($ok) {
		// return the new entry, using the insert id
   	    $auditoria->agregar_a_auditoria($_REQUEST["username"],"Modificó la carta menú " .$_REQUEST["cod_carta"]." .","0");
 		
		$toret = array(
			"data" => array(
				array(
					"Id_carta_menu" => mysql_insert_id(), 
					"Id_centro" => $_REQUEST["Id_centro"], # 
					"Id_plato" => $_REQUEST["Id_plato"] # 
					
				)
			), 
			"metadata" => array()
		);
	} else {
		// we had an error, return it
		$toret = array(
			"data" => array("error" => mysql_error()), 
			"metadata" => array()
		);
	}
	
	}
	return $toret;
}

//------------------------------------------------------------------


 function  mostrar_centros()
{
	$centro= new Ccentro;
	$toret = $centro->mostrar_centros();	
	return $toret;		
}


//------------------------------------------------------------------

  function  mostrar_plato()
{
	$plato= new CPlato;
	$toret = $plato->mostrar_platos();	
	return $toret;		
}
/**
 * constructs and executes a sql update query against the selected database
 * can take the following parameters:
 * $_REQUEST[primary_key] - thethe value of the primary key
 * $_REQUEST[field_name] - the list of fields which appear here will be used as values for update. 
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
function update() {
	global $conn;

	// check to see if the record actually exists in the database
	$query_recordset = sprintf("SELECT * FROM `carta_menu` WHERE Id_carta_menu = %s",
		GetSQLValueString($_REQUEST["Id_carta_menu"], "int")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);
	
	if ($num_rows > 0) {

		// build and execute the update query
		$row_recordset = mysql_fetch_assoc($recordset);
		$query_update = sprintf("UPDATE `carta_menu` SET Id_centro = %s,Id_plato = %s,fecha = %s WHERE Id_carta_menu = %s", 
			GetSQLValueString($_REQUEST["Id_centro"], "text"), 
			GetSQLValueString($_REQUEST["Id_plato"], "text"), 
			GetSQLValueString($_REQUEST["fecha"], "text"), 
			GetSQLValueString($row_recordset["Id_carta_menu"], "int")
		);
		$ok = mysql_query($query_update);
		if ($ok) {
			// return the updated entry
			$toret = array(
				"data" => array(
					array(
						"Id_carta_menu" => $row_recordset["Id_carta_menu"], 
						"Id_centro" => $_REQUEST["Id_centro"], #
						"Id_plato" => $_REQUEST["Id_plato"], #
						"fecha" => $_REQUEST["fecha"]#
					)
				), 
				"metadata" => array()
			);
		} else {
			// an update error, return it
			$toret = array(
				"data" => array("error" => mysql_error()), 
				"metadata" => array()
			);
		}
	} else {
		$toret = array(
			"data" => array("error" => "Ninguna fila encontrado"), 
			"metadata" => array()
		);
	}
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
 

function Mostrarfechas() 
{
	$centro =$_REQUEST["centro"];
	
	global $conn;	
	$query_select = "SELECT DISTINCT fecha FROM  carta_menu where Id_centro= '$centro'";
	$query_recordset = mysql_query($query_select,$conn); 
	
	$toret = array();
	while ($row_recordset = mysql_fetch_assoc($query_recordset)) {
		array_push($toret, $row_recordset);
	}
	
	//create the standard response structure
	$toret = array(
		"data" => $toret, 
		"metadata" => array()
	);

	return $toret;
}
//-----------------------------------------------------------------------------------------------

function Mostrar_code() 
{
	$fecha = @$_REQUEST['fecha'];
	global $conn;	
	$query_select = "SELECT DISTINCT Id_carta_menu FROM  carta_menu where fecha= '$fecha'";
	$query_recordset = mysql_query($query_select,$conn); 
	
	$toret = array();
	while ($row_recordset = mysql_fetch_assoc($query_recordset)) {
		array_push($toret, $row_recordset);
	}
	
	//create the standard response structure
	$toret = array(
		"data" => $toret, 
		"metadata" => array()
	);

//            $toret = array(
//				"data" => array("error" =>$fecha), 
//				"metadata" => array()
//			);

	return $toret;
}

 ///----------------------------------------------------------------------------------------------
function delete() {
	global $conn;
  $auditoria = new TAuditoria;
	// check to see if the record actually exists in the database
	$query_recordset = sprintf("SELECT * FROM `carta_menu` WHERE Id_carta_menu = %s and Id_plato = %s",
		GetSQLValueString($_REQUEST["Id_carta_menu"], "text"),
		GetSQLValueString($_REQUEST["plato"], "text")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);

	if ($num_rows > 0) {
		//$row_recordset = mysql_fetch_assoc($recordset);
		$query_delete = sprintf("DELETE  FROM `carta_menu` WHERE Id_carta_menu = %s  and Id_plato = %s", 
			GetSQLValueString($_REQUEST["Id_carta_menu"], "text"),
			GetSQLValueString($_REQUEST["plato"], "text")
		);
		$ok = mysql_query($query_delete);
		if ($ok) {
			// delete went through ok, return OK
	$auditoria->agregar_a_auditoria($_REQUEST["username"]," Eliminó el plato ".$_REQUEST["plato"]." de la carta menú " .$_REQUEST["Id_carta_menu"]." .","0");
			
			$toret = array(
				"data" => $_REQUEST["Id_carta_menu"], 
				"metadata" => array()
			);
		} else {
			$toret = array(
				"data" => array("error" => mysql_error()), 
				"metadata" => array()
			);
		}

	} else {
		// no row found, return an error
		$toret = array(
			"data" => array("error" => "Ninguna fila encontrado"), 
			"metadata" => array()
		);
	}
	return $toret;
}
//------------------------------------------------------------------------
function delete_carta() {
	global $conn;
  $auditoria = new TAuditoria;
	// check to see if the record actually exists in the database
	$query_recordset = sprintf("SELECT * FROM `carta_menu` WHERE Id_carta_menu = %s ",
		GetSQLValueString($_REQUEST["Id_carta_menu"], "text")
		
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);

	if ($num_rows > 0) {
		//$row_recordset = mysql_fetch_assoc($recordset);
		$query_delete = sprintf("DELETE  FROM `carta_menu` WHERE Id_carta_menu = %s ", 
			GetSQLValueString($_REQUEST["Id_carta_menu"], "text")			
		);
		$ok = mysql_query($query_delete);
		if ($ok) {
			// delete went through ok, return OK
	$auditoria->agregar_a_auditoria($_REQUEST["username"],"Eliminó la carta menú " .$_REQUEST["Id_carta_menu"]." del sistema.","0");
			
			$toret = array(
				"data" => $_REQUEST["Id_carta_menu"], 
				"metadata" => array()
			);
		} else {
			$toret = array(
				"data" => array("error" => mysql_error()), 
				"metadata" => array()
			);
		}

	} else {
		// no row found, return an error
		$toret = array(
			"data" => array("error" => "Ninguna fila encontrado"), 
			"metadata" => array()
		);
	}
	return $toret;
}



function CalcularImporte($centro, $fecha)
{
	 global $conn;
	 $query_select = "SELECT  SUM(cm.total) FROM carta_menu cm, plato pl where cm.Id_plato = pl.Id_plato
                         and Id_centro='$centro' and fecha='$fecha';";
     $query_recordset = mysql_query($query_select,$conn); 
     $importe = mysql_fetch_array($query_recordset);
	 $toret = array(
			"data" => array("error" => $importe[0]), 
			"metadata" => array()
	 );
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
			$ret = findAll();
		break;
		case "Insert": 
			$ret = insert();
		break;
		case "Update": 
			$ret = update();
		break;
		case "Delete": 
			$ret = delete();
		break;
		case "Delete_carta": 
			$ret = 	delete_carta();
		break;
	
		case "Count":
			$ret = rowCount();
		break;
		 case "MostrarCentros":
			$ret = mostrar_centros();
		break;
		case "MostrarPlatos":
			$ret = mostrar_plato();
		break;
		case "Mostrarfecha":
			$ret = Mostrarfechas();
		break;  
		case "modify":
			$ret = modify_carta();
		break;  
		case "mostrar_code":
			$ret =  Mostrar_code();
		break; 
		case "CalcularImporte":
		  $ret = CalcularImporte($_POST['Centro'], $_POST['Fecha']);
	}
}


$serializer = new XmlSerializer();
echo $serializer->serialize($ret);
die();
?>
