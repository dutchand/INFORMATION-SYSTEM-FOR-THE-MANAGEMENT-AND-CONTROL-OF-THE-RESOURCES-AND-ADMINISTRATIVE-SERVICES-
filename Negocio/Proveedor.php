<?php
require_once(dirname(__FILE__) . "/Controlciroaconn.php");
require_once(dirname(__FILE__) . "/functions.inc.php");
require_once(dirname(__FILE__) . "/XmlSerializer.class.php");
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
$filter_field = "codigo_proveedor";

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
	$fields = array('codigo_proveedor','razon_social','estado','organizacion','direccion','telefono','entidad_bancaria','sucursal','digito_control','cuenta');

//	$where = "";
//	if (@$_REQUEST['filter'] != "") {
//		$where = "WHERE " . $filter_field . " LIKE " . GetSQLValueStringForSelect(@$_REQUEST["filter"], $filter_type);	
//	}

//     $CAMPOS_SELECCIONADOS = "pr.codigo_proveedor, pr.razon_social,pr.organizacion, pr.direccion, pr.telefono,
//     				          pr.estado,dbs.entidad_bancaria , dbs.sucursal, dbs.digito_control, dbs.cuenta "; 
//	
//	  $WHERE_AND = " dbs.codigo_proveedor = pr.codigo_proveedor";
//
//      $FROM = "proveedor pr, datos_bancarios dbs";
//        
       
	$order = "";
	if (@$_REQUEST["orderField"] != "" && in_array(@$_REQUEST["orderField"], $fields)) {
		$order = "ORDER BY " . @$_REQUEST["orderField"] . " " . (in_array(@$_REQUEST["orderDirection"], array("ASC", "DESC")) ? @$_REQUEST["orderDirection"] : "ASC");
	}
	
	//calculate the number of rows in this table
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `proveedor` $where"); 
	$row_rscount = mysql_fetch_assoc($rscount);
	$totalrows = (int) $row_rscount["cnt"];
	
	//get the page number, and the page size
	$pageNum = (int)@$_REQUEST["pageNum"];
	$pageSize = (int)@$_REQUEST["pageSize"];
	
	//calculate the start row for the limit clause
	$start = $pageNum * $pageSize;

	//construct the query, using the where and order condition
      $query_recordset = "select pr.codigo_proveedor, pr.razon_social,pr.estado,pr.organizacion, pr.direccion, pr.telefono,
                dbs.entidad_bancaria , dbs.sucursal, dbs.digito_control, dbs.cuenta
                FROM proveedor pr,datos_bancarios dbs WHERE pr.codigo_proveedor = dbs.codigo_proveedor  $order";
	//$query_recordset = " $order";
	
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

 //-----------------------------------------------------------------------------------
function Existe($codeProveedor)
{	
	if($codeProveedor)
	{	
		global $conn;
		$query_select_proveedor = "SELECT * FROM proveedor WHERE codigo_proveedor = '$codeProveedor'";	
		$recordset = mysql_query($query_select_proveedor, $conn);
		$num_rows = mysql_num_rows($recordset);
		return $num_rows;	
	}
	return 0;
}

function Existe_name($name)
{	
	if($name)
	{	
		global $conn;
		$query_select_proveedor = "SELECT * FROM proveedor WHERE organizacion = '$name'";	
		$recordset = mysql_query($query_select_proveedor, $conn);
		$num_rows = mysql_num_rows($recordset);
		return $num_rows;	
	}
	return 0;
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
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `proveedor` $where"); 
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
function insert() {
	global $conn;

	//build and execute the insert query
        $codigo= $_REQUEST["codigo_proveedor"];
        $name = $_REQUEST["nombre_empresa"];
   		$auditoria = new TAuditoria;
  
if (Existe($codigo) > 0 && Existe_name($name) > 0) 
	{
	   $toret = array(
			"data" => array("error" => "Lo sentimos ya existe el proveedor: $Usuario"), 
			"metadata" => array() );
	}
	 else 
	
	{
	$query_insert = sprintf("INSERT INTO `proveedor` (codigo_proveedor,razon_social,organizacion,direccion,telefono,estado) VALUES (%s,%s,%s,%s,%s,%s)" ,		
		    GetSQLValueString($_REQUEST["codigo_proveedor"], "text"), # 
			GetSQLValueString($_REQUEST["razon_social"], "text"), # 
			GetSQLValueString($_REQUEST["nombre_empresa"], "text"), # 
			GetSQLValueString($_REQUEST["direccion"], "text"), # 
			GetSQLValueString($_REQUEST["telefono"], "text"),# 
			GetSQLValueString($_REQUEST["estado"], "text")# 
	);
	$ok = mysql_query($query_insert);
	//-------------------------------------------------------------------------------------------
	$query_insert3 = sprintf("INSERT INTO `datos_bancarios` (codigo_proveedor,entidad_bancaria,sucursal,digito_control,cuenta) VALUES (%s,%s,%s,%s,%s)" ,			
	        GetSQLValueString($_REQUEST["codigo_proveedor"], "text"), # 
			GetSQLValueString($_REQUEST["entidad_bancaria"], "text"), # 
			GetSQLValueString($_REQUEST["sucursal"], "text"), # 
			GetSQLValueString($_REQUEST["digito_control"], "text"),# 
			GetSQLValueString($_REQUEST["cuenta"], "text") #
	);
	$ok *= mysql_query($query_insert3);
	//-------------------------------------------------------------------------------------------
	  $query_insert2= sprintf("INSERT INTO `contrato` (codigo_proveedor,fecha_desde,fecha_hasta,descripcion,estado) VALUES (%s,%s,%s,%s,%s)" ,			
	        GetSQLValueString($_REQUEST["codigo_proveedor"], "text"), # 
			GetSQLValueString($_REQUEST["feshain"], "text"), # 
			GetSQLValueString($_REQUEST["fechafin"], "text"), # 
			GetSQLValueString($_REQUEST["descripc"], "text"),# 
			GetSQLValueString($_REQUEST["estadoc"], "text") #
	);
	$ok *= mysql_query($query_insert2);
	//-------------------------------------------------------------------------------------------
	if ($ok) {
		// return the new entry, using the insert id
 $auditoria->agregar_a_auditoria($_REQUEST["username"],"Insertó datos del proveedor " .$_REQUEST["codigo_proveedor"]." en el sistema.","0");
		
		$toret = array(
			"data" => array(
				array()
			), 
			"metadata" => array()
		   );
	    } 
	}
	
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
 
 
function mostrar_contratos()
{
		global $conn;
			//GetSQLValueString($_REQUEST["Id_pedido"], "text");			
			
		$query_select = mysql_query ("select pr.codigo_proveedor, pr.razon_social,pr.organizacion,
                 c.Id_contrato, c.fecha_desde, c.fecha_hasta,c.descripcion,c.estado
                FROM proveedor pr, contrato c WHERE  pr.codigo_proveedor = c.codigo_proveedor",$conn);
			$toret = array();	
			while ($row_recordset = mysql_fetch_assoc($query_select)) {
				array_push($toret, $row_recordset);
			}	
			//create the standard response structure
			$toret = array( "data" => $toret,
							"metadata" => array( )
							);
			return $toret;		
		}
	
 /////////////----------------------------------------------------
 
 function modificar_proveedor()
 	{
 		global $conn;
 		$auditoria = new TAuditoria;
 		//$x=$_REQUEST["codigo_proveedor2"];
 		$y=$_REQUEST["razon_social2"];
 		$z=$_REQUEST["nombre_empresa2"];
 		$a=$_REQUEST["direccion2"];
 		$b=$_REQUEST["telefono2"];
 		$c=$_REQUEST["estado2"];		
 		$codigo = 'Desactivo';
 		//$_REQUEST["codigo_proveedor2"];
// 		
//	if (Existe($codigo) > 0) 
//	{
//	   $toret = array(
//			"data" => array("error" => "Lo sentimos ya existe el proveedor: $codigo"), 
//			"metadata" => array() );
//	}
//	 else 
//		
//	{ 	
	
	  //------------------------------------------------------------------------------------------------codigo_proveedor=%s,			
	    $query_update = sprintf("UPDATE `proveedor` SET codigo_proveedor='$codigo', razon_social='$y',organizacion='$z',direccion='$a',telefono='$b',estado='$c' ");
		$ok = mysql_query($query_update);	
	//----------------------------------------------------------------------------------------------
		$query_update2 = sprintf("UPDATE `datos_bancarios` SET entidad_bancaria='$codigo',sucursal=%s,digito_control=%s,cuenta=%s", 
			//GetSQLValueString($_REQUEST["codigo_proveedor2"], "text"), 
			GetSQLValueString($_REQUEST["entidad_bancaria2"], "text"),
			GetSQLValueString($_REQUEST["sucursal2"], "text"),
			GetSQLValueString($_REQUEST["digito_control2"], "text"),
			GetSQLValueString($_REQUEST["cuenta2"], "text")		
			);
	    	$ok *= mysql_query($query_update2);	
		
	//----------------------------------------------------------------------------------------------codigo_proveedor=%s,

	
	//-----------------------------------------------------------------------------------------------    	
	    		
 		// build and execute the update query
    $auditoria->agregar_a_auditoria($_REQUEST["username"],"Modificó datos del proveedor " .$_REQUEST["codigo_proveedor2"]." en el sistema.","0");
 	
 					
	//}
 	
 	
 return $ok;	
 	
 }	
 //--------------------------------------------------------------------------------------

 
 //--------------------------------------------------------------------------------------
function update() {
	global $conn;
 		$auditoria = new TAuditoria;

	// check to see if the record actually exists in the database
	$query_recordset = sprintf("SELECT * FROM `proveedor` WHERE codigo_proveedor = %s",
		GetSQLValueString($_REQUEST["codigo_proveedor"], "text")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);
	
	if ($num_rows > 0) {

		// build and execute the update query
		$row_recordset = mysql_fetch_assoc($recordset);
		$query_update = sprintf("UPDATE `proveedor` SET razon_social = %s,organizacion = %s,direccion=%s,telefono=%s,estado = %s WHERE codigo_proveedor = %s", 
			GetSQLValueString($_REQUEST["razon_social"], "text"), 
			GetSQLValueString($_REQUEST["nombre_empresa"], "text"), 
			GetSQLValueString($_REQUEST["direccion"], "text"), 
			GetSQLValueString($_REQUEST["telefono"], "text"), 
			GetSQLValueString($_REQUEST["estado"], "text"), 
			GetSQLValueString($row_recordset["codigo_proveedor"], "text")
		);
		$ok = mysql_query($query_update);
//--------------------------------------------------------------------------------
		$query_update2 = sprintf("UPDATE `datos_bancarios` SET entidad_bancaria=%s,sucursal=%s,digito_control=%s,cuenta=%s WHERE codigo_proveedor = %s", 
			GetSQLValueString($_REQUEST["entidad_bancaria"], "text"),
			GetSQLValueString($_REQUEST["sucursal"], "text"),
			GetSQLValueString($_REQUEST["digito_control"], "text"),
			GetSQLValueString($_REQUEST["cuenta"], "text"),
			GetSQLValueString($_REQUEST["codigo_proveedor"], "text")	
			);
	    	$ok *= mysql_query($query_update2);	
		
//--------------------------------------------------------------------------------
         $query_update3 = sprintf("UPDATE `contrato` SET fecha_desde=%s,fecha_hasta=%s,descripcion=%s,estado=%s WHERE codigo_proveedor = %s", 
			GetSQLValueString($_REQUEST["feshainc"], "text"),
			GetSQLValueString($_REQUEST["fechafinc"], "text"),
			GetSQLValueString($_REQUEST["descripco"], "text"),
			GetSQLValueString($_REQUEST["estadoco"], "text"),
			GetSQLValueString($_REQUEST["codigo_proveedor"], "text")	
			);
	    	$ok *= mysql_query($query_update3);	

//--------------------------------------------------------------------------------
		//$ok = mysql_query($query_update);
		if ($ok) {
			// return the updated entry
   $auditoria->agregar_a_auditoria($_REQUEST["username"],"Modificó datos del proveedor " .$_REQUEST["codigo_proveedor"]." en el sistema.","0");
			
			$toret = array(
				"data" => array(array()), 
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

function Update_contrato()
{	 
	
	$auditoria = new TAuditoria;
	 $query_update3 = sprintf("UPDATE `contrato` SET fecha_desde=%s,fecha_hasta=%s,descripcion=%s,estado=%s WHERE codigo_proveedor = %s", 
			GetSQLValueString($_REQUEST["feshainc"], "text"),
			GetSQLValueString($_REQUEST["fechafinc"], "text"),
			GetSQLValueString($_REQUEST["descripco"], "text"),
			GetSQLValueString($_REQUEST["estadoco"], "text"),
			GetSQLValueString($_REQUEST["codigo_proveedor"], "text")	
			);
	    	$ok = mysql_query($query_update3);	
	    		
	    $auditoria->agregar_a_auditoria($_REQUEST["username"],"Modificó datos del proveedor " .$_REQUEST["codigo_proveedor"]." en el sistema.","0");
	
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
function delete() {
	global $conn;
 		$auditoria = new TAuditoria;

	// check to see if the record actually exists in the database
	$query_recordset = sprintf("SELECT * FROM `proveedor` WHERE codigo_proveedor = %s",
		GetSQLValueString($_REQUEST["codigo_proveedor"], "text")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);

	if ($num_rows > 0) {
		$row_recordset = mysql_fetch_assoc($recordset);
		$query_delete = sprintf("DELETE FROM `proveedor` WHERE codigo_proveedor = %s", 
			GetSQLValueString($row_recordset["codigo_proveedor"], "text")
		);
		$ok = mysql_query($query_delete);
		if ($ok) {
			// delete went through ok, return OK
	$auditoria->agregar_a_auditoria($_REQUEST["username"],"Eliminó datos del proveedor " .$_REQUEST["codigo_proveedor"]." en el sistema.","0");
			
			$toret = array(
				"data" => $row_recordset["codigo_proveedor"], 
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
		case "Count":
			$ret = rowCount();
		break;
		case "Modificar_proveedor":
			$ret = modificar_proveedor();
		break;
		case "Mostrar_contratos":
			$ret = mostrar_contratos();
		break;
		case "Update_contrato":
			$ret = Update_contrato();
		break;
	}
}


$serializer = new XmlSerializer();
echo $serializer->serialize($ret);
die();
?>
