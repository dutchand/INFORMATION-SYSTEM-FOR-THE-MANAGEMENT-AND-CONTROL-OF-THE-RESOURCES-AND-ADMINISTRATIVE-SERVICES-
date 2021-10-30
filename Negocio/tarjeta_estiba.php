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
$filter_field = "codigo_recepcion";

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
	$fields = array('codigo','Id_informe','codigo_proveedor','producto_generico','producto_espesifico','producto_surtido','cuenta','sub_cuenta','analisis','unidad_medida','precio_mlc','precio_mn');
//,'dia','mes','ano','documento_numero_clave','entrada','salida','saldo','seccion','estante','casilla'
//	$where = "";
//	if (@$_REQUEST['filter'] != "") {
//		$where = "WHERE " . $filter_field . " LIKE " . GetSQLValueStringForSelect(@$_REQUEST["filter"], $filter_type);	
//	}

		
	  $query_count = "SELECT COUNT(*) FROM tarjeta_estiba_occurencia;";		
	  $query_result = mysql_query($query_count,$conn);
	  $arreglo = mysql_fetch_array($query_result);
	  $limite = $arreglo[0] - 1;
	  $uno = 1;	
      //limit $limite,1;
      //$CAMPOS_SELECCIONADOS = "t.codigo,t.producto_generico,t.producto_espesifico,t.producto_surtido,t.cuenta,t.sub_cuenta,t.analisis,t.unidad_medida,t.precio_mlc,t.precio_mn,u.seccion,u.estante,u.casilla "; 
    
   	  //$WHERE_AND = "where t.codigo = u.codigo  $limite,1";
      
      //$FROM = " FROM  tarjeta_estiba t, ubicacion u, tarjeta_estiba_occurencia te LIMIT";
      $locura = "SELECT DISTINCT t.codigo,t.Id_informe,t.codigo_proveedor,t.producto_generico,t.producto_espesifico,t.producto_surtido,
       					t.cuenta,t.sub_cuenta,t.analisis,t.unidad_medida,t.precio_mlc,t.precio_mn
       					 FROM  tarjeta_estiba t ";  
		

	$order = "";
	if (@$_REQUEST["orderField"] != "" && in_array(@$_REQUEST["orderField"], $fields)) {
		$order = "ORDER BY " . @$_REQUEST["orderField"] . " " . (in_array(@$_REQUEST["orderDirection"], array("ASC", "DESC")) ? @$_REQUEST["orderDirection"] : "ASC");
	}
	
	//calculate the number of rows in this table
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `tarjeta_estiba` $where"); 
	$row_rscount = mysql_fetch_assoc($rscount);
	$totalrows = (int) $row_rscount["cnt"];
	
	//get the page number, and the page size
	$pageNum = (int)@$_REQUEST["pageNum"];
	$pageSize = (int)@$_REQUEST["pageSize"];
	
	//calculate the start row for the limit clause
	$start = $pageNum * $pageSize;

	//construct the query, using the where and order condition
	$query_recordset = "$locura $order";
	
	//$query_recordset = "SELECT codigo_recepcion,codigo,producto_generico,producto_espesifico,producto_surtido,cuenta,sub_cuenta,analisis,unidad_medida,precio_mlc,precio_mn,dia,mes,documento_numero_clave,entrada,salida,saldo FROM `tarjeta_estiba` $where $order";
	
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
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `tarjeta_estiba` $where"); 
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
    $p = $_REQUEST["producto_espesifico"];
	
    $fecha2 = $_REQUEST["Fechaentrar"];
    $dia2 = substr($fecha2,5,2);
    $mes2=substr($fecha2,8,2);
    $ano2 = substr($fecha2,0,4);
    $um = $_REQUEST["unidad_medida"];
    $saldo =  $_REQUEST["saldo"];
	   
	if($um =='Kg' || $um == 'Lts')
	   $saldo =$saldo*1000;
	   
	//build and execute the insert query
	$query_insert = sprintf("INSERT INTO `tarjeta_estiba` (codigo,Id_informe,codigo_proveedor,producto_generico,producto_espesifico,producto_surtido,unidad_medida,precio_mlc,precio_mn) VALUES (%s,%s,%s,%s,%s,%s,%s,%s,%s)" ,	
			GetSQLValueString($_REQUEST["codigo"], "text"), # 
			GetSQLValueString($_REQUEST["Code_Inform"], "text"), # 
			GetSQLValueString($_REQUEST["Code_prov"], "text"), # 
			GetSQLValueString($_REQUEST["producto_generico"], "text"), # 
			GetSQLValueString($_REQUEST["producto_espesifico"], "text"), # 
			GetSQLValueString($_REQUEST["producto_surtido"], "text"), # 
			
			GetSQLValueString($_REQUEST["unidad_medida"], "text"), # 
			GetSQLValueString($_REQUEST["precio_mlc"], "text"), # 
			GetSQLValueString($_REQUEST["precio_mn"], "text") # 
		
	);
	$ok = mysql_query($query_insert);
	//-------------------------------------------------------------------------------------------------
	$query_insert3 = sprintf("INSERT INTO `tarjeta_estiba_occurencia` (codigo,dia,mes,ano,documento_numero_clave,entrada,salida,saldo,firma) VALUES (%s,'$dia2','$mes2','$ano2',%s,%s,%s,'$saldo',%s)" ,			
            GetSQLValueString($_REQUEST["codigo"], "text"), # 
            GetSQLValueString($_REQUEST["documento_numero_clave"], "text"), # 
			GetSQLValueString($_REQUEST["entrada"], "text"), # 
			GetSQLValueString($_REQUEST["salida"], "text"), # 
			//GetSQLValueString($_REQUEST["saldo"], "text"), #           
			GetSQLValueString($_REQUEST["firma"], "text")#  
	);
	$ok *= mysql_query($query_insert3);	
	
	//-------------------------------------------------------------------------------------------------
	$query_insert2 = sprintf("INSERT INTO `producto` (codigo,clasificacion,fecha_vencimiento,tipo_producto) VALUES (%s,%s,%s,%s)" ,			//GetSQLValueString($_REQUEST["codigo_recepcion"], "text"), # 
		    GetSQLValueString($_REQUEST["codigo"], "text"), # 
		    GetSQLValueString($_REQUEST["classif"], "text"), #
		    GetSQLValueString($_REQUEST["fecha_venc"], "text"), #
			GetSQLValueString($_REQUEST["tipo_pro"], "text") #
			
	);
	$ok *= mysql_query($query_insert2);
	//--------------------------------------------------------------------------------------------------
	
	//--------------------------------------------------------------------------------------------------
	if ($ok) {
		// return the new entry, using the insert id
 $auditoria->agregar_a_auditoria($_REQUEST["username"],"Insertó una nueva tarjeta estiba para el producto: " .$_REQUEST["producto_surtido"],"0");
		
		
		$toret = array(
			"data" => array(
				array(
					"codigo_recepcion" => $_REQUEST["codigo_recepcion"], 
					"codigo" => $_REQUEST["codigo"], # 
					"producto_generico" => $_REQUEST["producto_generico"], # 
					"producto_espesifico" => $_REQUEST["producto_espesifico"], # 
					"producto_surtido" => $_REQUEST["producto_surtido"], # 
					"unidad_medida" => $_REQUEST["unidad_medida"], # 
					"precio_mlc" => $_REQUEST["precio_mlc"], # 
					"precio_mn" => $_REQUEST["precio_mn"], # 
					"dia" => $_REQUEST["dia"], # 
					"mes" => $_REQUEST["mes"], # 
					"documento_numero_clave" => $_REQUEST["documento_numero_clave"], # 
					"entrada" => $_REQUEST["entrada"], # 
					"salida" => $_REQUEST["salida"], # 
					"saldo" => $_REQUEST["saldo"]# 
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
//-------------------------------------------------------------------------------------
function find_code_proveedor($pass_value)
{
global $conn;
	$query_select = "SELECT codigo_proveedor FROM proveedor where organizacion = '$pass_value' ";
	$query_result = mysql_query($query_select,$conn);
	$saldo = mysql_fetch_array($query_result);
	return $saldo[0];		
}
//------------------------------------------------------------------------------------------
function existe_prov_en_IR($pass_value)
{
global $conn;
	$query_select = "SELECT * FROM informe_recepcion where codigo_proveedor = '$pass_value' ";
	$query_result = mysql_query($query_select,$conn);
	$saldo = mysql_fetch_array($query_result);
	return $saldo[0];		
}
//------------------------------------------------------------------------------------------
function find_existe($pass_value,$fecha)
{
global $conn;
	$query_select = "SELECT * FROM informe_recepcion where codigo_proveedor = '$pass_value' and fecha_recep='$fecha'";
	$query_result = mysql_query($query_select,$conn);
	$num_rows = mysql_num_rows($query_result);
	return $num_rows;	
}
//------------------------------------------------------------------------------------------

function Insert_informe()
{
	global $conn;
	$pass_ = $_REQUEST["proveedor"];
	$fecha_in =$_REQUEST["fecha_"];
	$pass_value = find_code_proveedor($pass_);
	$find=find_existe($pass_,$fecha_in);
	
	if($find >0)
	{
		$toret = array(
			"data" => array("error" => "Ya existe una acta de recepcion para este proveedor y la fecha."), 
			"metadata" => array() );
	}
	else
	{
     $query_insert = sprintf("INSERT INTO `informe_recepcion` (descripcion_informe,codigo_proveedor,fecha_recep) VALUES (%s,'$pass_value',%s)" ,	
			GetSQLValueString($_REQUEST["descripcion"], "text"), # 
			GetSQLValueString($_REQUEST["fecha_"], "text")# 
			
	);
	$ok = mysql_query($query_insert);	
     }
}
	 
	
//-------------------------------------------------------------------------------------------------------------
function mostrar_detalle_tarjeta($codigo)
{ 
	global $conn;	
	$query_select = "SELECT t.`codigo_occurencia`, t.`codigo`, t.`dia`, t.`mes`, t.`ano`, t.`documento_numero_clave`, t.`entrada`, t.`salida`, t.`saldo`, t.`firma` FROM tarjeta_estiba_occurencia t  where t.codigo = '$codigo' ;";
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
//----------------------------------------------------------------------------------
function ObtenerSaldoTarjeta($pass_value)
{
	global $conn;
			
	$query_count = "SELECT COUNT(*) FROM tarjeta_estiba_occurencia where codigo ='$pass_value'";		
	$query_result = mysql_query($query_count,$conn);
	$arreglo = mysql_fetch_array($query_result);
	$limite = $arreglo[0] - 1;
        
	$query_select = "SELECT saldo FROM tarjeta_estiba_occurencia where codigo = '$pass_value'  LIMIT $limite,1 ";
	$query_result = mysql_query($query_select,$conn);
	$saldo = mysql_fetch_array($query_result);
	return $saldo[0];			
}
//-----------------------------------------------------------------------------------


function Mostrarfechas() 
{
	$pass_value =$_REQUEST["proveedor"];
	$cc=find_code_proveedor($pass_value);
	$ccc= existe_prov_en_IR($cc);
	global $conn;	
     if($ccc>0)
     {
			
	$query_count = "SELECT COUNT(*) FROM informe_recepcion where codigo_proveedor ='$cc'";		
	$query_result = mysql_query($query_count,$conn);
	$arreglo = mysql_fetch_array($query_result);
	$limite = $arreglo[0] - 1;
	
	$query_select = "SELECT Id_informe,fecha_recep,codigo_proveedor FROM informe_recepcion where codigo_proveedor ='$cc' LIMIT $limite,1 ";
	$query_result = mysql_query($query_select,$conn);
	$ok = mysql_query($query_result);
	$toret = array();
	while ($row_recordset = mysql_fetch_assoc($query_result)) {
		array_push($toret, $row_recordset);
	}
	
	//create the standard response structure
	$toret = array(
		"data" => $toret, 
		"metadata" => array()
	);

	return $toret;
     }
     else
     {
     	 $toret = array(
			"data" => array(), 
			"metadata" => array() );
     }
     
}
//-------------------------------------------------------------------------------------
 function mostrar_proveedor()
{
			global $conn;
			
			$query_select = "SELECT codigo_proveedor,organizacion FROM proveedor where estado ='Activo'";
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
	
//------------------------------------------------------------------------------------
function Modificat_Tarjeta_estiba()
{	
	global $conn;	
	  	$auditoria = new TAuditoria;   
	
    $fecha2 = $_REQUEST["Fechaentrar2"];
    $entrada =$_REQUEST["entrada2"];
    $firma = $_REQUEST["username"];
    $dia2 = substr($fecha2,5,2);
    $mes2=substr($fecha2,8,2);
    $ano2 = substr($fecha2,0,4);
         
    $codigo = $_REQUEST["codigo"];
    $query_recordset = "SELECT codigo FROM `tarjeta_estiba` WHERE codigo = '$codigo';";
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);
	
	if ($num_rows >= 1) 
	{       
		    $saldo = ObtenerSaldoTarjeta($codigo);
		   	$documento_numero_clave = $_REQUEST["documento_numero_clave2"]; 
		   //	$entrada = $_REQUEST["saldos2"]; // aqui esta multiplicado el precio por la entrada 		   	
		   	$total = $saldo + $entrada;		   	
		    
		    $query_insert = "insert into tarjeta_estiba_occurencia (codigo,mes,dia,ano,documento_numero_clave,entrada,saldo,firma)values('$codigo','$dia2','$mes2','$ano2','$documento_numero_clave','$entrada','$total','$firma')";
		    mysql_query($query_insert,$conn); 
		    
   $auditoria->agregar_a_auditoria($_REQUEST["username"],"Modificó la tarjeta estiba para el producto: " .$_REQUEST["producto_surtido"]. " en : " .$_REQUEST["entrada2"],"0");
		    
	}	    
}
//--------------------------------------------------------------------------------------------------------------

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
	$query_recordset = sprintf("SELECT * FROM `tarjeta_estiba` WHERE codigo_recepcion = %s",
		GetSQLValueString($_REQUEST["codigo_recepcion"], "text")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);
	
	if ($num_rows > 0) {

		// build and execute the update query
		$row_recordset = mysql_fetch_assoc($recordset);
		$query_update = sprintf("UPDATE `tarjeta_estiba` SET codigo = %s,producto_generico = %s,producto_espesifico = %s,producto_surtido = %s,cuenta = %s,sub_cuenta = %s,analisis = %s,unidad_medida = %s,precio_mlc = %s,precio_mn = %s,dia = %s,mes = %s,documento_numero_clave = %s,entrada = %s,salida = %s,saldo = %s WHERE codigo_recepcion = %s", 
			GetSQLValueString($_REQUEST["codigo"], "text"), 
			GetSQLValueString($_REQUEST["producto_generico"], "text"), 
			GetSQLValueString($_REQUEST["producto_espesifico"], "text"), 
			GetSQLValueString($_REQUEST["producto_surtido"], "text"), 
			GetSQLValueString($_REQUEST["cuenta"], "int"), 
			GetSQLValueString($_REQUEST["sub_cuenta"], "int"), 
			GetSQLValueString($_REQUEST["analisis"], "text"), 
			GetSQLValueString($_REQUEST["unidad_medida"], "text"), 
			GetSQLValueString($_REQUEST["precio_mlc"], "int"), 
			GetSQLValueString($_REQUEST["precio_mn"], "int"), 
			GetSQLValueString($_REQUEST["dia"], "text"), 
			GetSQLValueString($_REQUEST["mes"], "text"), 
			GetSQLValueString($_REQUEST["documento_numero_clave"], "text"), 
			GetSQLValueString($_REQUEST["entrada"], "int"), 
			GetSQLValueString($_REQUEST["salida"], "int"), 
			GetSQLValueString($_REQUEST["saldo"], "int"), 
			GetSQLValueString($row_recordset["codigo_recepcion"], "text")
		);
		$ok = mysql_query($query_update);
		if ($ok) {
			// return the updated entry
			$toret = array(
				"data" => array(
					array(
						"codigo_recepcion" => $row_recordset["codigo_recepcion"], 
						"codigo" => $_REQUEST["codigo"], #
						"producto_generico" => $_REQUEST["producto_generico"], #
						"producto_espesifico" => $_REQUEST["producto_espesifico"], #
						"producto_surtido" => $_REQUEST["producto_surtido"], #
						"cuenta" => $_REQUEST["cuenta"], #
						"sub_cuenta" => $_REQUEST["sub_cuenta"], #
						"analisis" => $_REQUEST["analisis"], #
						"unidad_medida" => $_REQUEST["unidad_medida"], #
						"precio_mlc" => $_REQUEST["precio_mlc"], #
						"precio_mn" => $_REQUEST["precio_mn"], #
						"dia" => $_REQUEST["dia"], #
						"mes" => $_REQUEST["mes"], #
						"documento_numero_clave" => $_REQUEST["documento_numero_clave"], #
						"entrada" => $_REQUEST["entrada"], #
						"salida" => $_REQUEST["salida"], #
						"saldo" => $_REQUEST["saldo"]#
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
	$query_recordset = sprintf("SELECT * FROM `tarjeta_estiba` WHERE codigo = %s",
		GetSQLValueString($_REQUEST["codigo"], "text")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);

	if ($num_rows > 0) {
		$row_recordset = mysql_fetch_assoc($recordset);
		
		$query_delete3 = sprintf("DELETE FROM `ubicacion` WHERE codigo = %s", 
			GetSQLValueString($row_recordset["codigo"], "text")
		);
		$ok = mysql_query($query_delete3);
		
		//------------------------------------------------------------
		
		
		$query_delete = sprintf("DELETE FROM `tarjeta_estiba` WHERE codigo = %s", 
			GetSQLValueString($row_recordset["codigo"], "text")
		);
		$ok *= mysql_query($query_delete);
		//-----------------------------------------------------------
	 	
	   $query_delete3 = sprintf("DELETE FROM `tarjeta_estiba_occurencia` WHERE codigo = %s", 
			GetSQLValueString($row_recordset["codigo"], "text")
		);
		$ok *= mysql_query($query_delete3);
	//------------------------------------------------------------
	
	   $query_delete2 = sprintf("DELETE  FROM `producto` WHERE codigo = %s", 
			GetSQLValueString($row_recordset["codigo"], "text")
		);
		$ok *= mysql_query($query_delete2);
		
	//-----------------------------------------------------------
	
		if ($ok) {
			// delete went through ok, return OK
	 $auditoria->agregar_a_auditoria($_REQUEST["username"],"Elimino la tarjeta estiba para el producto: " .$_REQUEST["codigo"],"0");
			
			$toret = array(
				"data" => $row_recordset["codigo"], 
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
		case "ModificatTE":
			$ret = Modificat_Tarjeta_estiba();
		break;
		case "mostrar_detalle_tarjeta":
			$ret = mostrar_detalle_tarjeta($_POST['codigo']);
		break;	
		case "mostrar_proveedor":
			$ret = mostrar_proveedor();
		break;
		case "Insert_informe": 
			$ret = Insert_informe();
		break;
		case "Mostrar_fechas": 
			$ret = Mostrarfechas();
		break;
	}
}


$serializer = new XmlSerializer();
echo $serializer->serialize($ret);
die();
?>
