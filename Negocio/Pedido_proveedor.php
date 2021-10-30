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
	$fields = array('Id_pedido_proveedor','codigo_proveedor','cantidad','producto_surtido','organizacion','organismo_pertenece','fecha_pedido','orden_compra','tipo_movimiento','notificacion_almacen');

	$where_code = "";
	if (@$_REQUEST['filter'] != "") {
		$where = "AND  p.codigo_proveedor  LIKE " . GetSQLValueStringForSelect(@$_REQUEST["filter"], $filter_type);	
	}

    $CAMPOS_SELECCIONADOS= "p.Id_pedido_proveedor,p.codigo_proveedor, pr.organizacion, p.cantidad,p.producto_surtido, p.organismo_pertenece,p.fecha_pedido,p.orden_compra,p.tipo_movimiento,p.notificacion_almacen";

    $WHERE_AND="where p.codigo_proveedor = pr.codigo_proveedor";
    
     $FROM = " FROM pedido_proveedor p, proveedor pr";
    
	$order = "";
	if (@$_REQUEST["orderField"] != "" && in_array(@$_REQUEST["orderField"], $fields)) {
		$order = "ORDER BY " . @$_REQUEST["orderField"] . " " . (in_array(@$_REQUEST["orderDirection"], array("ASC", "DESC")) ? @$_REQUEST["orderDirection"] : "ASC");
	}
	
	//calculate the number of rows in this table
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `pedido_proveedor` $where"); 
	$row_rscount = mysql_fetch_assoc($rscount);
	$totalrows = (int) $row_rscount["cnt"];
	
	//get the page number, and the page size
	$pageNum = (int)@$_REQUEST["pageNum"];
	$pageSize = (int)@$_REQUEST["pageSize"];
	
	//calculate the start row for the limit clause
	$start = $pageNum * $pageSize;

	//construct the query, using the where and order condition
			$query_recordset = "SELECT $CAMPOS_SELECCIONADOS $FROM $WHERE_AND  $order";
	
	
	//$query_recordset = "SELECT Id_pedido_proveedor,codigo_proveedor,organismo_pertenece,fecha_pedido,orden_compra,tipo_movimiento,notificacion_almacen FROM `pedido_proveedor` $where $order";
	
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
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `pedido_proveedor` $where"); 
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
 
function Mostrar_producto() 
{
	global $conn;	
	$query_select = "SELECT  producto_surtido FROM  tarjeta_estiba";
	$query_recordset = mysql_query($query_select,$conn); 
	//$recordset = mysql_query($query_recordset, $conn);
	
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
 //----------------------------------------------------------------------------------------------------
 

  function mostrar_code_nombre()
{
			global $conn;
			$producto= $_REQUEST["producto"];
			$query_select = "SELECT  p.codigo_proveedor, p.organizacion from tarjeta_estiba t, proveedor p 
					 where  t.producto_surtido= '$producto' and p.codigo_proveedor= t.codigo_proveedor";
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
 //---------------------------------------------------------------------------------------------------------
function insert() {
	global $conn;

	//build and execute the insert query
	$query_insert = sprintf("INSERT INTO `pedido_proveedor` (codigo_proveedor,organismo_pertenece,fecha_pedido,cantidad,producto_surtido,orden_compra,tipo_movimiento,notificacion_almacen) VALUES (%s,%s,%s,%s,%s,%s,%s,%s)" ,			GetSQLValueString($_REQUEST["codigo_proveedor"], "text"), # 
			GetSQLValueString($_REQUEST["organismo_pertenece"], "text"), # 
			GetSQLValueString($_REQUEST["fecha_pedido"], "text"), # 
			GetSQLValueString($_REQUEST["cantidad"], "text"), # 
			GetSQLValueString($_REQUEST["producto"], "text"), # 
			GetSQLValueString($_REQUEST["orden_compra"], "int"), # 
			GetSQLValueString($_REQUEST["tipo_movimiento"], "text"), # 
			GetSQLValueString($_REQUEST["notificacion_almacen"], "text")# 
	);
	$ok = mysql_query($query_insert);
	
	if ($ok) {
		// return the new entry, using the insert id
		$toret = array(
			"data" => array(
				array(
					"Id_pedido_proveedor" => mysql_insert_id(), 
					"codigo_proveedor" => $_REQUEST["codigo_proveedor"], # 
					"organizacion" => $_REQUEST["organizacion"], # 
					"organismo_pertenece" => $_REQUEST["organismo_pertenece"], # 
					"fecha_pedido" => $_REQUEST["fecha_pedido"], # 
					"orden_compra" => $_REQUEST["orden_compra"], # 
					"tipo_movimiento" => $_REQUEST["tipo_movimiento"], # 
					"notificacion_almacen" => $_REQUEST["notificacion_almacen"]# 
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
	$query_recordset = sprintf("SELECT * FROM `pedido_proveedor` WHERE Id_pedido_proveedor = %s",
		GetSQLValueString($_REQUEST["Id_pedido_proveedor"], "int")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);
	
	if ($num_rows > 0) {

		// build and execute the update query
		$row_recordset = mysql_fetch_assoc($recordset);
		$query_update = sprintf("UPDATE `pedido_proveedor` SET codigo_proveedor = %s,fecha_pedido = %s,cantidad= %s,producto_surtido= %s, orden_compra = %s,tipo_movimiento = %s,notificacion_almacen = %s WHERE Id_pedido_proveedor = %s", 
			GetSQLValueString($_REQUEST["codigo_proveedor"], "text"), 
			GetSQLValueString($_REQUEST["fecha_pedido"], "text"),
			GetSQLValueString($_REQUEST["cantidad"], "text"), 
			GetSQLValueString($_REQUEST["producto"], "text"),
			GetSQLValueString($_REQUEST["orden_compra"], "int"), 
			GetSQLValueString($_REQUEST["tipo_movimiento"], "text"), 
			GetSQLValueString($_REQUEST["notificacion_almacen"], "text"), 
			GetSQLValueString($row_recordset["Id_pedido_proveedor"], "int")
		);
		$ok = mysql_query($query_update);
		if ($ok) {
			// return the updated entry
			$toret = array(
				"data" => array(
					array(
						"Id_pedido_proveedor" => $row_recordset["Id_pedido_proveedor"], 
						"codigo_proveedor" => $_REQUEST["codigo_proveedor"], #
						"organizacion" => $_REQUEST["organizacion"], #
						"organismo_pertenece" => $_REQUEST["organismo_pertenece"], #
						"fecha_pedido" => $_REQUEST["fecha_pedido"], #
						"orden_compra" => $_REQUEST["orden_compra"], #
						"tipo_movimiento" => $_REQUEST["tipo_movimiento"], #
						"notificacion_almacen" => $_REQUEST["notificacion_almacen"]#
					)
				), 
				"metadata" => array()
			);
		} 
		
		else {
			// an update error, return it
			$toret = array(
				"data" => array("error" => mysql_error()), 
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
 * returns : an array of the form
 * array (
 * 		data => deleted_row_primary_key_value, 
 * 		metadata => array()
 * ) 
 */
function delete() {
	global $conn;

	// check to see if the record actually exists in the database
	$query_recordset = sprintf("SELECT * FROM `pedido_proveedor` WHERE Id_pedido_proveedor = %s",
		GetSQLValueString($_REQUEST["Id_pedido_proveedor"], "int")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);

	if ($num_rows > 0) {
		$row_recordset = mysql_fetch_assoc($recordset);
		$query_delete = sprintf("DELETE FROM `pedido_proveedor` WHERE Id_pedido_proveedor = %s", 
			GetSQLValueString($row_recordset["Id_pedido_proveedor"], "int")
		);
		$ok = mysql_query($query_delete);
		if ($ok) {
			// delete went through ok, return OK
			$toret = array(
				"data" => $row_recordset["Id_pedido_proveedor"], 
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


//-----------------------------------------------------------------------------------------------


function BuscarCodigoProveedor($NombreProv)
{
	global $conn;
	$query_select = "SELECT p.`codigo_proveedor` FROM proveedor p WHERE organizacion = '$NombreProv';";
	$query_result = mysql_query($query_select,$conn);
	$codigo_prov = mysql_fetch_array($query_result);
	return $codigo_prov[0];	
}

function CalcularImporte($NombreProv, $MesFecha)
{
	global $conn;
	
	$ano = substr($MesFecha,0,4);
	
	$mes = substr($MesFecha,5,2);
	
	$codigo = BuscarCodigoProveedor($NombreProv);
	
	$query_select = "select	p.cantidad, p.producto_surtido
       						FROM pedido_proveedor p, proveedor pr
       						where  p.codigo_proveedor = pr.codigo_proveedor
       						AND pr.codigo_proveedor = '$codigo'
       						AND tipo_movimiento = 'Pedido' AND substring(fecha_pedido,6,2) = '$mes'
       						AND substring(fecha_pedido,1,4) = '$ano';";
	$query_result = mysql_query($query_select,$conn);
	
	$importe = 0;
	
	for($i = 0; $fila = mysql_fetch_assoc($query_result); $i++) 
	{ 
        //for($j = 0; $j < mysql_num_fields($query_result); $j++)
        //{
        	//if($j == 1)
           // {
          	    $campo = mysql_field_name($query_result, 1); 
          	    $campo1 = mysql_field_name($query_result, 0);
          	    $producto = $fila[$campo]; 
          	    $cantidad = $fila[$campo1]; 
          	    $importe = $importe + BuscarPesoUnitario($producto) * $cantidad;
            //}   
          
        //}
    }
	$toret = array(
			"data" => array("error" => $importe), 
			"metadata" => array()
		);		
	return $toret;
}


function BuscarPesoUnitario($Producto)
{
	global $conn;
	$query_select = "SELECT t.`precio_mn` FROM tarjeta_estiba t WHERE t.`producto_surtido` = '$Producto';";
	$query_result = mysql_query($query_select,$conn);
	$pu = mysql_fetch_array($query_result);
	return $pu[0];
}


 function mostrar_proveedor()
{
			global $conn;
			$query_select = "SELECT organizacion FROM proveedor where estado ='Activo'";
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
	




 function mostrar_pedido_proveedor()
{
			global $conn;
			
			$NombreProv = $_REQUEST["name"];
			$fecha_pedid = $_REQUEST["fecha"];
			
			$ano = substr($fecha_pedid,0,4);
	
	        $mes = substr($fecha_pedid,5,2);
	
	        $codigo = BuscarCodigoProveedor($NombreProv);
			
			$query_select = "select	Id_pedido_proveedor, pr.codigo_proveedor, organizacion, p.cantidad, p.producto_surtido
       						FROM pedido_proveedor p, proveedor pr
       						where  p.codigo_proveedor = pr.codigo_proveedor
       						AND pr.codigo_proveedor = '$codigo'
       						AND tipo_movimiento = 'Pedido' AND substring(fecha_pedido,6,2)='$mes' 
       						AND substring(fecha_pedido,1,4)='$ano' ; ";
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
        case "mostrar_producto":
			$ret = Mostrar_producto();
		break;	
		 case "mostrar_proveedor":
			$ret = mostrar_code_nombre();
		break;	
		case "mostrar_proveedor_name":
			$ret = mostrar_proveedor();
		break;
		case "Calcular_Importe":
			$ret = CalcularImporte($_POST["name"],$_POST["fecha"]);
		break; 
		case "mostrar_pedido_proveedor":
			$ret =  mostrar_pedido_proveedor();
		break;
		
		}
}


$serializer = new XmlSerializer();
echo $serializer->serialize($ret);
die();
?>
