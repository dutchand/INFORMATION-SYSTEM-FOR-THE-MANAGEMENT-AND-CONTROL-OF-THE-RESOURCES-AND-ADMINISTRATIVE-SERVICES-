<?php
require_once(dirname(__FILE__) . "/Controlciroaconn.php");
require_once(dirname(__FILE__) . "/functions.inc.php");
require_once(dirname(__FILE__) . "/XmlSerializer.class.php");
require_once(dirname(__FILE__) . "/TAuditoria.php");
require_once(dirname(__FILE__) . "/CalcularCostoSalida.php");
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
$filter_field = "Id_pedido";

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
       $gastro='Ambas';
	/**
	 * the list of fields in the table. We need this to check that the sent value for the ordering is indeed correct.
	 */
	$fields = array('Id_pedido','Id_centro','nombre','apellidos','num_personas');

       $CAMPOS_SELECCIONADOS = "a.Id_pedido,a.Id_centro,p.nombre, p.apellidos,a.num_personas";

       $WHERE_AND = "where p.CI = a.CI  and a.tipo ='$gastro' and (a.clasificacion = 'Confirmado' or a.clasificacion = 'Confirmado y pagado')";
          
      $FROM = " FROM  pedido_cliente a, persona p";
//       
//	$where = "";
//	if (@$_REQUEST['filter'] != "") {
//		$where = "WHERE " . $filter_field . " LIKE " . GetSQLValueStringForSelect(@$_REQUEST["filter"], $filter_type);	
//	}

	$order = "";
	if (@$_REQUEST["orderField"] != "" && in_array(@$_REQUEST["orderField"], $fields)) {
		$order = "ORDER BY " . @$_REQUEST["orderField"] . " " . (in_array(@$_REQUEST["orderDirection"], array("ASC", "DESC")) ? @$_REQUEST["orderDirection"] : "ASC");
	}
	
	//calculate the number of rows in this table
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `platos_del_pedido` $where"); 
	$row_rscount = mysql_fetch_assoc($rscount);
	$totalrows = (int) $row_rscount["cnt"];
	
	//get the page number, and the page size
	$pageNum = (int)@$_REQUEST["pageNum"];
	$pageSize = (int)@$_REQUEST["pageSize"];
	
	//calculate the start row for the limit clause
	$start = $pageNum * $pageSize;

	//construct the query, using the where and order condition
		$query_recordset = "SELECT $CAMPOS_SELECCIONADOS $FROM $WHERE_AND $order";
	
	//$query_recordset = "SELECT Id_platos_pedido,Id_pedido,Id_plato,cant_raciones FROM `platos_del_pedido` $where $order";
	
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
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `platos_del_pedido` $where"); 
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
		$auditoria = new TAuditoria;
	
   $cost1 = new CalcularCostoSalida;
   
            $query_recordset = sprintf("SELECT Id_pedido,Id_plato FROM `platos_del_pedido` WHERE Id_pedido = %s AND Id_plato=%s ",
		GetSQLValueString($_REQUEST["Id_pedido"], "text"),
		GetSQLValueString($_REQUEST["Id_plato"], "text")
	);
	 $recordset = mysql_query($query_recordset, $conn);
		$num_rows = mysql_num_rows($recordset);
	
	//-----------------------------------------------------------------------------------------------------
	 $query_recordset2 = sprintf("SELECT precioventa FROM `plato` WHERE Id_plato = %s",
		GetSQLValueString($_REQUEST["Id_plato"], "text")
	);
	
	 $recordset2 =mysql_query($query_recordset2, $conn);
	 $arreglo = mysql_fetch_array($recordset2);
	 $costo_por_plato =  $arreglo[0] * $_REQUEST["cant_raciones"];
//--------------------------------------------------------------------------------------------------------		
	

	if ($num_rows == 1)
	{ 
		$ok=false;
	}
      
    else
         {
	//build and execute the insert query
	$query_insert = sprintf("INSERT INTO `platos_del_pedido` (Id_pedido,Id_plato,cant_raciones,costo) VALUES (%s,%s,%s,'$costo_por_plato')" ,			GetSQLValueString($_REQUEST["Id_pedido"], "text"), # 
			GetSQLValueString($_REQUEST["Id_plato"], "text"), # 
			GetSQLValueString($_REQUEST["cant_raciones"], "int") # 
	);
	$ok = mysql_query($query_insert);
	
	//-----------------------------para la calculacion de los cobros por plato-------------------------------------------------------------------------
	   
	    $query_recordset3 = sprintf("SELECT Id_pedido FROM `cobro` WHERE Id_pedido = %s",
		GetSQLValueString($_REQUEST["Id_pedido"], "text")
	);
	   $recordset3 =mysql_query($query_recordset3, $conn);
	   $num_rows3 = mysql_num_rows($recordset3);
	   
	    if($num_rows3 == 1)
	    {
	    	$query_update3 = sprintf("UPDATE `cobro` SET  cantidad = cantidad + '$costo_por_plato' WHERE Id_pedido= %s",
			GetSQLValueString($_REQUEST["Id_pedido"], "text")
			
		);
		$ok *= mysql_query($query_update3);	
	$auditoria->agregar_a_auditoria($_REQUEST["username"],"Insertó datos del pedido  " .$_REQUEST["Id_pedido"]." al sistema.","0");
	    	
	    }
	      else 
	         if($num_rows3 != 1)
	      {
	          $type = 'Gastronomía';	
	          $moneda = 'MN';    
	           $query_insert2 = sprintf("INSERT INTO `cobro` ( Id_pedido,tipo,moneda,cantidad) VALUES (%s,'$type','$moneda','$costo_por_plato')" ,	
		    	GetSQLValueString($_REQUEST["Id_pedido"], "text") # 
			//GetSQLValueString($_REQUEST["tipo"], "text") # 
	           );
	           $ok = mysql_query($query_insert2);	
	      }
	   
	
//-----------------------------------------------------------------------------------------------------------------
       }
          
	if ($ok) {
		// return the new entry, using the insert id
		$toret = array(
			"data" => array(
				array(
					"Id_platos_pedido" => mysql_insert_id(), 
					"Id_pedido" => $_REQUEST["Id_pedido"], # 
					"Id_plato" => $_REQUEST["Id_plato"], # 
					"cant_raciones" => $_REQUEST["cant_raciones"]# 
				)
			), 
			"metadata" => array()
		);
	}
	
	 else {
		// we had an error, return it
		$toret = array(
		"data" => array(), 
			"data" => array("error" => "Ya existe el plato en el pedido."), 
			"metadata" => array()
		);
	}
	
	
	return $toret;
}
//------------------------------------------------------------------


  function  mostrar_plato()
{  
	//$centro=$_REQUEST["Nada"];
	global $conn;
			
			$query_select = "SELECT Id_plato FROM plato ";
			$query_recordset = mysql_query($query_select,$conn);
			$toret = array();	
			while ($row_recordset = mysql_fetch_assoc($query_recordset)) {
				array_push($toret, $row_recordset);
			}	
			//create the standard response structure
			$toret = array( "data" => $toret,
							"metadata" => array() );
			return $toret;			
}

//----------------------------------------------------------------------------llenar datagrid


//----------------------------------------------------------------------------------------	
function mostrar_platos_grid()
{
		global $conn;
			//GetSQLValueString($_REQUEST["Id_pedido"], "text");			
			$grid = $_POST['Id_pedido'];
		$query_select = mysql_query ("SELECT Id_platos_pedido,Id_pedido,Id_plato,cant_raciones,costo FROM platos_del_pedido WHERE Id_pedido = '$grid'",$conn);
	//GetSQLValueString($_REQUEST["Id_pedido"], "text")
	//$query_select = mysql_query ("SELECT Id_pedido,Id_plato,normatecnica,cant_raciones FROM platos_del_pedido, plato WHERE Id_pedido = '$grid' ",$conn);

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
   	$auditoria = new TAuditoria;
   
	// check to see if the record actually exists in the database
	$query_recordset = sprintf("SELECT * FROM `platos_del_pedido` WHERE Id_platos_pedido = %s",
		GetSQLValueString($_REQUEST["Id_platos_pedido"], "int")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);
	
	if ($num_rows > 0) {

		// build and execute the update query
		$row_recordset = mysql_fetch_assoc($recordset);
		$query_update = sprintf("UPDATE `platos_del_pedido` SET Id_pedido = %s,Id_plato = %s,cant_raciones = %s WHERE Id_platos_pedido = %s", 
			GetSQLValueString($_REQUEST["Id_pedido"], "text"), 
			GetSQLValueString($_REQUEST["Id_plato"], "text"), 
			GetSQLValueString($_REQUEST["cant_raciones"], "int"), 
			GetSQLValueString($row_recordset["Id_platos_pedido"], "int")
		);
		$ok = mysql_query($query_update);
		if ($ok) {
			// return the updated entry
			$toret = array(
				"data" => array(
					array(
						"Id_platos_pedido" => $row_recordset["Id_platos_pedido"], 
						"Id_pedido" => $_REQUEST["Id_pedido"], #
						"Id_plato" => $_REQUEST["Id_plato"], #
						"cant_raciones" => $_REQUEST["cant_raciones"]#
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
function delete() {
	global $conn;
		$auditoria = new TAuditoria;

	// check to see if the record actually exists in the database
	$query_recordset = sprintf("SELECT * FROM `platos_del_pedido` WHERE Id_platos_pedido = %s",
		GetSQLValueString($_REQUEST["Id_platos_pedido"], "int")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);

	if ($num_rows > 0) {
		$row_recordset = mysql_fetch_assoc($recordset);
		$query_delete = sprintf("DELETE FROM `platos_del_pedido` WHERE Id_platos_pedido = %s", 
			GetSQLValueString($row_recordset["Id_platos_pedido"], "int")
		);
		$ok = mysql_query($query_delete);
//------------------------------------------------actualizar el cobro total-------------------------------------------------------
	    	$query_update3 = sprintf("UPDATE `cobro` SET  cantidad = cantidad - %s WHERE Id_pedido= %s",
	    	GetSQLValueString($_REQUEST["costo"], "text"),
			GetSQLValueString($_REQUEST["Id_pedido"], "text")
			
		);
		$ok *= mysql_query($query_update3);	
	    	
//-------------------------------------------------------------------------------------------------------
		
		
		if ($ok) {
			// delete went through ok, return OK
			$toret = array(
				"data" => $row_recordset["Id_platos_pedido"], 
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
		case "MostrarPlatos":
			$ret =  mostrar_plato();
		break;
		case "mostrar_platos_engrid":
			$ret =  mostrar_platos_grid();
		break;
	}
}


$serializer = new XmlSerializer();
echo $serializer->serialize($ret);
die();
?>
