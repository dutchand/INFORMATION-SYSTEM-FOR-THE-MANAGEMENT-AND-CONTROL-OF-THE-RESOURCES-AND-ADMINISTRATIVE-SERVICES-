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
    $tipo= 'Ambas';
	/**
	 * the list of fields in the table. We need this to check that the sent value for the ordering is indeed correct.
	 */
	$fields = array('Id_pedido_alm','Id_pedido','Id_centro','fecha_pedido');

//	$where = "";
//	if (@$_REQUEST['filter'] != "") {
//		$where = "WHERE " . $filter_field . " LIKE " . GetSQLValueStringForSelect(@$_REQUEST["filter"], $filter_type);
//and pc.Id_pedido = pl.Id_pedido, platos_del_pedido pl	
//	}

      $CAMPOS_SELECCIONADOS = " pc.Id_pedido, pc.Id_centro, pc.fecha_pedido "; 
	
	  $WHERE_AND = "where pc.tipo = '$tipo' and (pc.clasificacion = 'Confirmado' or pc.clasificacion = 'Confirmado y pagado')";

      $FROM = " FROM pedido_cliente pc ";
      

	$order = "";
	if (@$_REQUEST["orderField"] != "" && in_array(@$_REQUEST["orderField"], $fields)) {
		$order = "ORDER BY " . @$_REQUEST["orderField"] . " " . (in_array(@$_REQUEST["orderDirection"], array("ASC", "DESC")) ? @$_REQUEST["orderDirection"] : "ASC");
	}
	
	//calculate the number of rows in this table
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `pedido_almacen` $where"); 
	$row_rscount = mysql_fetch_assoc($rscount);
	$totalrows = (int) $row_rscount["cnt"];
	
	//get the page number, and the page size
	$pageNum = (int)@$_REQUEST["pageNum"];
	$pageSize = (int)@$_REQUEST["pageSize"];
	
	//calculate the start row for the limit clause
	$start = $pageNum * $pageSize;

	//construct the query, using the where and order condition
		$query_recordset = "SELECT $CAMPOS_SELECCIONADOS $FROM $WHERE_AND $order";
	//$query_recordset = "SELECT Id_pedido_alm,Id_pedido,fecha,Id_centro FROM `pedido_almacen` $where $order";
	
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
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `pedido_almacen` $where"); 
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
	$query_insert = sprintf("INSERT INTO `pedido_almacen` (Id_pedido,fecha,Id_centro) VALUES (%s,%s,%s)" ,			GetSQLValueString($_REQUEST["Id_pedido"], "text"), # 
			GetSQLValueString($_REQUEST["fecha"], "text"), # 
			GetSQLValueString($_REQUEST["Id_centro"], "text")# 
	);
	$ok = mysql_query($query_insert);
	
	if ($ok) {
		// return the new entry, using the insert id
		$toret = array(
			"data" => array(
				array(
					"Id_pedido_alm" => mysql_insert_id(), 
					"Id_pedido" => $_REQUEST["Id_pedido"], # 
					"fecha" => $_REQUEST["fecha"], # 
					"Id_centro" => $_REQUEST["Id_centro"]# 
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
	$query_recordset = sprintf("SELECT * FROM `pedido_almacen` WHERE Id_pedido_alm = %s",
		GetSQLValueString($_REQUEST["Id_pedido_alm"], "int")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);
	
	if ($num_rows > 0) {

		// build and execute the update query
		$row_recordset = mysql_fetch_assoc($recordset);
		$query_update = sprintf("UPDATE `pedido_almacen` SET Id_pedido = %s,fecha = %s,Id_centro = %s WHERE Id_pedido_alm = %s", 
			GetSQLValueString($_REQUEST["Id_pedido"], "text"), 
			GetSQLValueString($_REQUEST["fecha"], "text"), 
			GetSQLValueString($_REQUEST["Id_centro"], "text"), 
			GetSQLValueString($row_recordset["Id_pedido_alm"], "int")
		);
		$ok = mysql_query($query_update);
		if ($ok) {
			// return the updated entry
			$toret = array(
				"data" => array(
					array(
						"Id_pedido_alm" => $row_recordset["Id_pedido_alm"], 
						"Id_pedido" => $_REQUEST["Id_pedido"], #
						"fecha" => $_REQUEST["fecha"], #
						"Id_centro" => $_REQUEST["Id_centro"]#
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


//------------------------------------------------
/*
function buscar_producto_del_plato($Pedido)
{
	global $conn;	
	$query_select = "SELECT p.`Id_producto`, t.`unidad_medida`, (p.`normatecnica` * pp.`cant_raciones`) AS Cantidad  FROM productos_del_plato p,
							platos_del_pedido pp, tarjeta_estiba t WHERE p.`Id_plato` = pp.`Id_plato` 
							AND p.`Id_producto` = t.`producto_espesifico` AND pp.`Id_pedido` = '$Pedido';";
	$recordset = mysql_query($query_select,$conn);
	//if we have rows in the table, loop through them and fill the array
	$toret = array();
	while ($row_recordset = mysql_fetch_assoc($recordset)) {
		array_push($toret, $row_recordset);
	}
	$toret = array(
		"data" => $toret, 
		"metadata" => array ());
	return $recordset;	
}


function buscar_norma_producto($Pedido)
{
	global $conn;	
	$query_select = "SELECT p.`normatecnica` FROM productos_del_plato p, platos_del_pedido pp WHERE p.`Id_plato` = pp.`Id_plato` AND pp.`Id_pedido` = '$Pedido';";
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


function NormaTecnicaXCantidad($Pedido)
{
	global $conn;	
	$query_select = "SELECT (p.`normatecnica`*pp.`cant_raciones`) AS Cantidad FROM productos_del_plato p, platos_del_pedido pp WHERE p.`Id_plato` = pp.`Id_plato` AND pp.`Id_pedido` = '$Pedido';";
	$recordset = mysql_query($query_select,$conn);
	//if we have rows in the table, loop through them and fill the array
	$toret = array();
	while ($row_recordset = mysql_fetch_assoc($recordset)) {
		array_push($toret, $row_recordset);
	}
	$toret = array(
		"data" => $toret, 
		"metadata" => array ());
	return $recordset;	
}
*/


function ResultadoFinal()
{
	global $conn;	
	
	$Pedido = $_REQUEST["Pedido"];
	
	$query_select = "SELECT p.`Id_producto`, t.`unidad_medida`, (p.`normatecnica` * pp.`cant_raciones`)AS Cantidad ,t.precio_mn  FROM productos_del_plato p,
       						platos_del_pedido pp, tarjeta_estiba t WHERE p.`Id_plato` = pp.`Id_plato`
       						AND p.`Id_producto` = t.`producto_espesifico` AND pp.`Id_pedido` = '$Pedido';";
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


  function vale_salida()
  {
  	
  	global $conn;	
	
	$Pedido = $_REQUEST["Pedido"];
//	$codigo=$Pedido;
//	 $fecha =
//	 $centro=
	$query_select = "  SELECT pc.`fecha_pedido`, p.`Id_producto`, t.`unidad_medida`, (p.`normatecnica` * pp.`cant_raciones`) AS Cantidad,
                       t.precio_mn  FROM productos_del_plato p, platos_del_pedido pp, tarjeta_estiba t, pedido_cliente pc
                       WHERE p.`Id_plato` = pp.`Id_plato` AND p.`Id_producto` = t.`producto_espesifico`
                       AND pp.`Id_pedido` = '$Pedido'; ";
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
  
  
  
  function AgregarValeSalida($codigo, $fecha, $centro)
  {
  	  
  	 $query_insert = "INSERT INTO `vale_de_salida` (codigo, Fecha, Id_centro) VALUES ('$codigo','$fecha','$centro')";
     $ok = mysql_query($query_insert);
  }
  
  
  
   
  
  
  
  function BuscarCentroCosto()
  {
  	global $conn;
  	
  	$Pedido = $_REQUEST["centro"];
	$query_select = "SELECT p.`Id_centro` FROM pedido_cliente p WHERE p.`Id_pedido` = '$Pedido';";
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
  
  

  function BuscarCartamenu()
  {
  	global $conn;
  	
	$query_select = "SELECT DISTINCT  Id_carta_menu, Id_centro, fecha FROM carta_menu ";
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
function delete() {
	global $conn;

	// check to see if the record actually exists in the database
	$query_recordset = sprintf("SELECT * FROM `pedido_almacen` WHERE Id_pedido_alm = %s",
		GetSQLValueString($_REQUEST["Id_pedido_alm"], "int")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);

	if ($num_rows > 0) {
		$row_recordset = mysql_fetch_assoc($recordset);
		$query_delete = sprintf("DELETE FROM `pedido_almacen` WHERE Id_pedido_alm = %s", 
			GetSQLValueString($row_recordset["Id_pedido_alm"], "int")
		);
		$ok = mysql_query($query_delete);
		if ($ok) {
			// delete went through ok, return OK
			$toret = array(
				"data" => $row_recordset["Id_pedido_alm"], 
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



function CalcularImportePedidoAlmacen($pedido)
{
     global $conn;
     
     $query_select = "SELECT t.`unidad_medida`, (p.`normatecnica` * pp.`cant_raciones`) AS Cantidad,
                      t.precio_mn  FROM productos_del_plato p, platos_del_pedido pp, tarjeta_estiba t, pedido_cliente pc
                      WHERE p.`Id_plato` = pp.`Id_plato` AND p.`Id_producto` = t.`producto_espesifico`
                      AND pp.`Id_pedido` = '$pedido' GROUP BY p.`Id_producto`;";
     $query_result = mysql_query($query_select,$conn);
	
	 $importe = 0.0;
	 
	 for($i = 0; $fila = mysql_fetch_assoc($query_result); $i++) 
	 { 
          $funidad_medida = mysql_field_name($query_result, 0); 
          	    
          $fcantidad =  mysql_field_name($query_result, 1);
          	    
          $fprecio_mn = mysql_field_name($query_result, 2);
          	    
          $unidad_medida = $fila[$funidad_medida];
          	    
          $cantidad = $fila[$fcantidad]; 
          	    
          $precio_mn = $fila[$fprecio_mn];
          	    
          	    if($unidad_medida == 'kg' || $unidad_medida == 'ml')          	    
          	    	$cantidad = $cantidad / 1000;   	   
          	       
          	    $importe = $importe + ( $cantidad *  $precio_mn);
      }
      $toret = array(
			"data" => array("error" => $importe), 
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
		case "Count":
			$ret = rowCount();
		break;
		case "ResultadoFinal":
			$ret = ResultadoFinal($_POST['Pedido']);
		break;
		case "Vale_salida":
			$ret =  vale_salida();
		break;
		case "Vale_salida":
			$ret =  vale_salida();
		break;case "show_centro":
			$ret =  BuscarCentroCosto();
		break;
		case "CalcularImportePedidoAlmacen":
		    $ret = CalcularImportePedidoAlmacen($_POST['Pedido']);
		break;    
		case "carta_menu_date_centro":
		    $ret =BuscarCartamenu();
		break;	
		//AgregarValeSalida($codigo, $fecha, $centro)
//		case "AgregarVale":
//		    $ret =AgregarValeSalida($_REQUEST["Pedido"], $_REQUEST["Pedido"], $_REQUEST["Pedido"]);
//		break;	
//  
	}
}


$serializer = new XmlSerializer();
echo $serializer->serialize($ret);
die();
?>
