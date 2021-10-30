<?php
require_once(dirname(__FILE__) . "/Controlciroaconn.php");
require_once(dirname(__FILE__) . "/functions.inc.php");
require_once(dirname(__FILE__) . "/XmlSerializer.class.php");
require_once(dirname(__FILE__) . "/Ccentro.php");
require_once(dirname(__FILE__) . "/CPersona.php");
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

	/**
	 * the list of fields in the table. We need this to check that the sent value for the ordering is indeed correct.
	 */
	$fields = array('Id_pedido','Id_centro','CI','nombre', 'apellidos','num_personas','tipo_actividad','fecha_pedido','fecha_actividad','hora','hora_fin','clasificacion','cantidad','tipo');

	$AND_PEDIDO = "";
	if (@$_REQUEST['filter'] != "") {
		$AND_PEDIDO = "AND a.Id_pedido LIKE " . GetSQLValueStringForSelect(@$_REQUEST["filter"], $filter_type);	
	}
    
//    $AND_Nombre = "";
//	if (@$_REQUEST['search_names'] != "") {
//		$AND_Nombre = "AND p.nombre LIKE " . GetSQLValueStringForSelect(@$_REQUEST["search_names"], $filter_type);	
//	}
     
    
     $CAMPOS_SELECCIONADOS = "a.Id_pedido,a.Id_centro,a.CI,p.nombre, p.apellidos,a.num_personas,a.tipo_actividad,a.fecha_pedido,a.fecha_actividad,a.hora,a.hora_fin,a.clasificacion,pl.cantidad,a.tipo"; 
	
	  $WHERE_AND = "where p.CI = a.CI and pl.Id_pedido = a.Id_pedido";

      $FROM = " FROM  pedido_cliente a, persona p, cobro pl";
      
	$order = "";
	if (@$_REQUEST["orderField"] != "" && in_array(@$_REQUEST["orderField"], $fields)) {
		$order = "ORDER BY " . @$_REQUEST["orderField"] . " " . (in_array(@$_REQUEST["orderDirection"], array("ASC", "DESC")) ? @$_REQUEST["orderDirection"] : "ASC");
	}
	
	//calculate the number of rows in this table
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `pedido_cliente` $where"); 
	$row_rscount = mysql_fetch_assoc($rscount);
	$totalrows = (int) $row_rscount["cnt"];
	
	//get the page number, and the page size
	$pageNum = (int)@$_REQUEST["pageNum"];
	$pageSize = (int)@$_REQUEST["pageSize"];
	
	//calculate the start row for the limit clause
	$start = $pageNum * $pageSize;

	//construct the query, using the where and order condition
	$query_recordset = "SELECT $CAMPOS_SELECCIONADOS $FROM $WHERE_AND $AND_PEDIDO  ";
	//$query_recordset = "SELECT Id_pedido,Id_centro,CI,num_personas,tipo_actividad,fecha_pedido,fecha_actividad,hora,clasificacion,Id_cobro FROM `pedido_cliente` $where $order";
	
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
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `pedido_cliente` $where"); 
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
function verify_date($centro, $date)
{
 	global $conn;
	$query_recordset = sprintf("SELECT * FROM `pedido_cliente` WHERE Id_centro = '$centro' AND fecha_actividad = '$date'");
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);
	return $num_rows;
}
//------------------------------------------------------------------- 
 
function verify_($centro, $date)
{
 	global $conn;
	$query_recordset = sprintf("SELECT fecha_actividad FROM `pedido_cliente` WHERE Id_centro = '$centro' AND fecha_actividad = '$date'");
	$query_result = mysql_query($query_recordset, $conn);
	$date= mysql_fetch_array($query_result);
	 return $date[0];		
}

//-------------------------------------------------------------------
function insert() 
{
	  global $conn;

      $personal = new CPersona;
      $fecha_pedido = date("Y\-m\-d"); 
      	$auditoria = new TAuditoria;
      
      $cadena_fecha = $_REQUEST["fecha_actividad"];
      //$centro_fecha = $_REQUEST["Id_centro"];
      $centro = $_REQUEST["Id_centro"];
      
               
      if(verify_date($centro, $cadena_fecha) > 0  )
      {
          // we had an error, return it
		 $toret = array(
			"data" => array("error" => "Ya existe una actividad para esta fecha en el centro"), 
			"metadata" => array() );
      }
      else
      {
	      $hours=  $_REQUEST["hora"].":".$_REQUEST["minute"]." ".$_REQUEST["sona"];
	      $hoursfin=  $_REQUEST["horafin"].":".$_REQUEST["minute1"]." ".$_REQUEST["sonafin"];
          $costo_por_plato = $_REQUEST["Id_cobro"] ;
     //--------------------------------------------------------------------------------------------------------------------------
 	      $personal->agregar_persona($_REQUEST["CI"],$_REQUEST["nombre"],$_REQUEST["apellidos"],$_REQUEST["cargo"],$conn);
 
          $query_insert2 = sprintf("INSERT INTO `cliente` (CI,organizacion,direccion,telefono) VALUES (%s,%s,%s,%s)" ,	 
                     GetSQLValueString($_REQUEST["CI"], "text"),#
			         GetSQLValueString($_REQUEST["organizacion"], "text"), # 
			         GetSQLValueString($_REQUEST["direccion"], "text"), # 
			         GetSQLValueString($_REQUEST["telefono"], "text") #  
		  );
	      $ok = mysql_query($query_insert2);
	//----------------------------------------------------------------------------------------------------------------------------
     
	//build and execute the insert query
	$query_insert = sprintf("INSERT INTO `pedido_cliente` (Id_pedido,Id_centro,CI,num_personas,tipo_actividad,fecha_pedido,fecha_actividad,hora,hora_fin,clasificacion,tipo) VALUES (%s,%s,%s,%s,%s,'$fecha_pedido','$cadena_fecha','$hours','$hoursfin',%s,%s)" ,			
	        GetSQLValueString($_REQUEST["Id_pedido"], "text"), # 
			GetSQLValueString($_REQUEST["Id_centro"], "text"), # 
			GetSQLValueString($_REQUEST["CI"], "text"), # 
			GetSQLValueString($_REQUEST["num_personas"], "int"), # 
			GetSQLValueString($_REQUEST["tipo_actividad"], "text"), # 
			GetSQLValueString($_REQUEST["clasificacion"], "text"), # 
			//GetSQLValueString($_REQUEST["Id_cobro"], "text"), #
			GetSQLValueString($_REQUEST["tipo"], "text")# 
			 
	);
	$ok = mysql_query($query_insert);
	
	          $type = $_REQUEST["tipo"];	
	          $moneda = 'MN';    
	           $query_insert3 = sprintf("INSERT INTO `cobro` ( Id_pedido,tipo,moneda,cantidad) VALUES (%s,'$type','$moneda',%s)" ,	
		    	GetSQLValueString($_REQUEST["Id_pedido"], "text"), # 
			    GetSQLValueString($_REQUEST["Id_cobro"], "text") # 
	           );
	           $ok = mysql_query($query_insert3);	
  $auditoria->agregar_a_auditoria($_REQUEST["username"],"Agregó una nueva solicitud para  el " .$cadena_fecha." al sistema.","0");
	
	//----------------------------------------------------------------------------------------------------------------------------
     }    	
	return $toret;
}



 //-----------------------------------------------------------------------------
 
 
 //-----------------------------------------------------------------------------
 function  mostrar_centros()
{
	$centro= new Ccentro;
	$toret = $centro->mostrar_centros();	
	return $toret;		
}
 //-----------------------------------------------------------------------------

  function mostrar_costo_por_centro()
{
			global $conn;
			$centro= $_REQUEST["costo"];
			$query_select = "SELECT costo_por_recreacion from centro_de_costo where Id_centro='$centro' ";
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
 //----------------------------------------------------------
function  modificar_solicitud()
{
        global $conn;
      	$auditoria = new TAuditoria;
     
       $cadena_fecha = $_REQUEST["fecha_actividad2"];
       $centro = $_REQUEST["Id_centro2"];

	$query_recordset = sprintf("SELECT Id_pedido FROM `pedido_cliente` WHERE Id_pedido = %s",
		GetSQLValueString($_REQUEST["Id_pedido2"], "text")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);
	$toret = array();
	
	$pass= verify_($centro, $cadena_fecha);
           
           if ($cadena_fecha!=$pass)
   {
	if ($num_rows > 0) 
	{
		
		if(verify_date($centro, $cadena_fecha) > 0)
		{
			$toret = array(
			    "data" => array("error" => "Ya existe una actividad para esta fecha en el centro"), 
			    "metadata" => array() );
		}
		else
		{
		    $query_update = sprintf("UPDATE `pedido_cliente` SET Id_centro=%s, num_personas = %s,tipo_actividad = %s,fecha_actividad =%s ,hora = %s,hora_fin=%s,clasificacion = %s,tipo=%s WHERE Id_pedido = %s", 
			                      GetSQLValueString($_REQUEST["Id_centro2"], "text"), 
			                      GetSQLValueString($_REQUEST["num_personas2"], "int"), 
			                      GetSQLValueString($_REQUEST["tipo_actividad2"], "text"), 
			                      GetSQLValueString($_REQUEST["fecha_actividad2"], "text"), 
			                      GetSQLValueString($_REQUEST["hora2"], "text"), 
			                      GetSQLValueString($_REQUEST["horafin2"], "text"), 
			                      GetSQLValueString($_REQUEST["clasificacion2"], "text"), 
			                      GetSQLValueString($_REQUEST["tipo2"], "text"), 
			                      GetSQLValueString($_REQUEST["Id_pedido2"], "text")
		     );
		     mysql_query($query_update);
	//---------------------------------------------------------------------------------------------------------------------
			
		     $query_recordset2 = sprintf("SELECT Id_pedido FROM `cobro` WHERE Id_pedido = %s",
		                    GetSQLValueString($_REQUEST["Id_pedido2"], "text")
	         );
	         $recordset2 = mysql_query($query_recordset2, $conn);
	         $num_rows2 = mysql_num_rows($recordset2);
	
	         if ($num_rows2 == 1) 
	         {
		         // build and execute the update query
		         //$row_recordset = mysql_fetch_assoc($recordset);
		
		         $query_update2 = sprintf("UPDATE `cobro` SET cantidad = %s WHERE Id_pedido = %s", 
			             GetSQLValueString($_REQUEST["Id_cobro2"], "text"), 
			             GetSQLValueString($_REQUEST["Id_pedido2"], "text")
		         );
		         mysql_query($query_update2);	
	          }
	//-------------------------------------------------------------------------------------------------------		
		  $auditoria->agregar_a_auditoria($_REQUEST["username"],"Modificó la solicitud para  el" .$cadena_fecha." en el sistema.","0");
		
	  }
	}
   }
   	else
		{
		    $query_update = sprintf("UPDATE `pedido_cliente` SET Id_centro=%s, num_personas = %s,tipo_actividad = %s,fecha_actividad =%s ,hora = %s,hora_fin=%s,clasificacion = %s,tipo=%s WHERE Id_pedido = %s", 
			                      GetSQLValueString($_REQUEST["Id_centro2"], "text"), 
			                      GetSQLValueString($_REQUEST["num_personas2"], "int"), 
			                      GetSQLValueString($_REQUEST["tipo_actividad2"], "text"), 
			                      GetSQLValueString($_REQUEST["fecha_actividad2"], "text"), 
			                      GetSQLValueString($_REQUEST["hora2"], "text"), 
			                      GetSQLValueString($_REQUEST["horafin2"], "text"), 
			                      GetSQLValueString($_REQUEST["clasificacion2"], "text"), 
			                      GetSQLValueString($_REQUEST["tipo2"], "text"), 
			                      GetSQLValueString($_REQUEST["Id_pedido2"], "text")
		     );
		     mysql_query($query_update);
	//---------------------------------------------------------------------------------------------------------------------
  $auditoria->agregar_a_auditoria($_REQUEST["username"],"modifico la solicitud para  el" .$cadena_fecha." en el sistema.","0");
			
		     $query_recordset2 = sprintf("SELECT Id_pedido FROM `cobro` WHERE Id_pedido = %s",
		                    GetSQLValueString($_REQUEST["Id_pedido2"], "text")
	         );
	         $recordset2 = mysql_query($query_recordset2, $conn);
	         $num_rows2 = mysql_num_rows($recordset2);
	
	         if ($num_rows2 == 1) 
	         {
		         // build and execute the update query
		         //$row_recordset = mysql_fetch_assoc($recordset);
		
		         $query_update2 = sprintf("UPDATE `cobro` SET cantidad = %s WHERE Id_pedido = %s", 
			             GetSQLValueString($_REQUEST["Id_cobro2"], "text"), 
			             GetSQLValueString($_REQUEST["Id_pedido2"], "text")
		         );
		         mysql_query($query_update2);	
	          }
	//-------------------------------------------------------------------------------------------------------		
		
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
	$query_recordset = sprintf("SELECT * FROM `pedido_cliente` WHERE Id_pedido = %s",
		GetSQLValueString($_REQUEST["Id_pedido"], "text")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);
	
	if ($num_rows > 0) {

		// build and execute the update query
		$row_recordset = mysql_fetch_assoc($recordset);
		$query_update = sprintf("UPDATE `pedido_cliente` SET Id_centro = %s,CI = %s,num_personas = %s,tipo_actividad = %s,fecha_pedido = %s,fecha_actividad = %s,hora = %s,clasificacion = %s,Id_cobro = %s WHERE Id_pedido = %s", 
			GetSQLValueString($_REQUEST["Id_centro"], "text"), 
			GetSQLValueString($_REQUEST["CI"], "text"), 
			GetSQLValueString($_REQUEST["num_personas"], "int"), 
			GetSQLValueString($_REQUEST["tipo_actividad"], "text"), 
			GetSQLValueString($_REQUEST["fecha_pedido"], "text"), 
			GetSQLValueString($_REQUEST["fecha_actividad"], "text"), 
			GetSQLValueString($_REQUEST["hora"], "text"), 
			GetSQLValueString($_REQUEST["clasificacion"], "text"), 
			GetSQLValueString($_REQUEST["Id_cobro"], "text"), 
			GetSQLValueString($row_recordset["Id_pedido"], "text")
		);
		$ok = mysql_query($query_update);
		if ($ok) {
			// return the updated entry
			$toret = array(
				"data" => array(
					array(
						"Id_pedido" => $row_recordset["Id_pedido"], 
						"Id_centro" => $_REQUEST["Id_centro"], #
						"CI" => $_REQUEST["CI"], #
						"num_personas" => $_REQUEST["num_personas"], #
						"tipo_actividad" => $_REQUEST["tipo_actividad"], #
						"fecha_pedido" => $_REQUEST["fecha_pedido"], #
						"fecha_actividad" => $_REQUEST["fecha_actividad"], #
						"hora" => $_REQUEST["hora"], #
						"clasificacion" => $_REQUEST["clasificacion"], #
						"Id_cobro" => $_REQUEST["Id_cobro"]#
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
	$query_recordset = sprintf("SELECT * FROM `pedido_cliente` WHERE Id_pedido = %s",
		GetSQLValueString($_REQUEST["Id_pedido"], "text")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);

	if ($num_rows > 0) {
		$row_recordset = mysql_fetch_assoc($recordset);
		
		//---------------------------------------
		
		$query_delete = sprintf("DELETE FROM `pedido_cliente` WHERE Id_pedido = %s", 
			GetSQLValueString($row_recordset["Id_pedido"], "text")
		);	
		
		$ok = mysql_query($query_delete);
		//---------------------------------------
	$query_delete2 = sprintf("DELETE  FROM `platos_del_pedido` WHERE Id_pedido = %s", 
			GetSQLValueString($row_recordset["Id_pedido"], "text")
		);
		$ok *= mysql_query($query_delete2);
	   //---------------------------------------
		if ($ok) {
			// delete went through ok, return OK
	 $auditoria->agregar_a_auditoria($_REQUEST["username"],"Elimino la solicitud " .$_REQUEST["Id_pedido"]." del sistema.","0");
			
			$toret = array(
				"data" => $row_recordset["Id_pedido"], 
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
	    case "MostrarCentros":
			$ret = mostrar_centros();
		break;
		 case "modificar_solicitud":
			$ret = modificar_solicitud();
		break;
		case "mostrar_costo":
			$ret = mostrar_costo_por_centro();
		break;
		
		
	}
}


$serializer = new XmlSerializer();
echo $serializer->serialize($ret);
die();
?>
