<?php
require_once(dirname(__FILE__) . "/Controlconn.php");
require_once(dirname(__FILE__) . "/functions.inc.php");
require_once(dirname(__FILE__) . "/XmlSerializer.class.php");
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
$filter_field = "Id_usuario";
$filter_field_2 = "nombre";
$filter_field_3 = "apellidos";
$filter_field_4 = "rol";

/**
 * we need to escape the value, so we need to know what it is
 * possible values: text, long, int, double, date, defined
 */
$filter_type = "text";
//$persona= new CGestionarPersona;



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
	$fields = array('Id_usuario','CI','contrasena','nombre','apellidos','rol');

   $AND_NOMBRE_USUARIO = "";
	if (@$_REQUEST['filter'] != "") {
		$AND_NOMBRE_USUARIO = "AND p.Id_usuario LIKE " . GetSQLValueStringForSelect(@$_REQUEST["filter"], $filter_type);	
	}
//	
//	$AND_PRIVILEGIO = "";
//	if (@$_REQUEST['rol'] != "Seleccionar") {
//		$AND_PRIVILEGIO = "AND a.rol LIKE " . GetSQLValueStringForSelect(@$_REQUEST["rol"], $filter_type);	
//	}

    
    $AND_Nombre = "";
	if (@$_REQUEST['filter'] != "") {
		$AND_Nombre = "AND a.nombre LIKE " . GetSQLValueStringForSelect(@$_REQUEST["filter"], $filter_type);	
	}

	
      $CAMPOS_SELECCIONADOS = "p.Id_usuario,p.CI, p.contrasena,a.nombre, a.apellidos, p.rol"; 
	
	  $WHERE_AND = "where p.CI = a.CI";

      $FROM = " FROM  usuario p, persona a ";
      

	$order = "";
	if (@$_REQUEST["orderField"] != "" && in_array(@$_REQUEST["orderField"], $fields)) {
		$order = "ORDER BY " . @$_REQUEST["orderField"] . " " . (in_array(@$_REQUEST["orderDirection"], array("ASC", "DESC")) ? @$_REQUEST["orderDirection"] : "ASC");
	}
	
	//calculate the number of rows in this table
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `usuario` $where"); 
	$row_rscount = mysql_fetch_assoc($rscount);
	$totalrows = (int) $row_rscount["cnt"];
	
	//get the page number, and the page size
	$pageNum = (int)@$_REQUEST["pageNum"];
	$pageSize = (int)@$_REQUEST["pageSize"];
	
	//calculate the start row for the limit clause
	$start = $pageNum * $pageSize;
    
   // $CAMPOS_SELECCIONADOS = "Id_usuario,CI,contrasena,nombre, apellidos, rol FROM persona,usuario WHERE usuario.CI = persona.CI";


	//construct the query, using the where and order condition
	
		   $query_recordset = "SELECT $CAMPOS_SELECCIONADOS $FROM $WHERE_AND  $AND_NOMBRE_USUARIO  $order ";
	
	//$query_recordset = "SELECT Id_usuario,contrasena,nombre,apellidos,rol FROM `usuario` $where $order";
	
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
 

function buscar_usuario() {
	global $conn;
	
	$query_select = "SELECT `Id_usuario` FROM `usuario`";
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
 
 
 /***
  * 
  * 
  * 
  */
function rowCount() {
	global $conn, $filter_field, $filter_type;

	$where = "";
	if (@$_REQUEST['filter'] != "") {
		$where = "WHERE " . $filter_field . " LIKE " . GetSQLValueStringForSelect(@$_REQUEST["filter"], $filter_type);	
	}

	//calculate the number of rows in this table
	$rscount = mysql_query("SELECT count(*) AS cnt FROM `usuario` $where"); 
	$row_rscount = mysql_fetch_assoc($rscount);
	$totalrows = (int) $row_rscount["cnt"];
	
	//create the standard response structure
	$toret = array(
		"data" => $totalrows, 
		"metadata" => array()
	);

	return $toret;
}
/***
 * para modificar la contrasena de un usuario ya registrado en el sistema.
 * 
 */


function Modificar_password() {	
	global $conn;
		$auditoria = new TAuditoria;

          
		$query_recordset = sprintf("SELECT Id_usuario FROM `usuario` WHERE Id_usuario = %s AND contrasena = %s", 
		    GetSQLValueString($_REQUEST["Id_usuario"], "text"),
		    GetSQLValueString($_REQUEST["actual_password"], "text")
	    );
	
	
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);	
	if ($num_rows == 1) {
			
	 
	  	if(  ($_REQUEST["new_password"] == $_REQUEST["confirm_password"]))  
	  	{	
		$query_update2 = sprintf("UPDATE `usuario` SET contrasena = %s WHERE Id_usuario = %s",
	  
			GetSQLValueString($_REQUEST["new_password"], "text"), 
			GetSQLValueString($_REQUEST["Id_usuario"], "text")
		);
		$ok = mysql_query($query_update2);	
		}
		else
	    $ok = false;
	    		
		if ($ok) {
			// return the updated entry
   			$auditoria->agregar_a_auditoria($_REQUEST["username"],"Modificó la contraseña del usuario: " .$_REQUEST["Id_usuario"],"0");
  	
			$toret = array(
				"data" => array(), 
				"metadata" => array()
			);
		//Registrar la creación del  usuario por el administrador en Auditoria	
		} 
		else {
		$toret = array(
			"data" => array("error" => "No existe este usuario o no es correcta la contraseña"), 
			"metadata" => array()
		);
		} 
		return $toret;    
	  }
	       
}	       
	
//-----------------------------------------------------------------------------------------	

 
 	function modificar_usuario()
 	{
 		global $conn;
 				$auditoria = new TAuditoria;
 		
 		$usuario = $_REQUEST["Id_usuario"];
 		$su_ci = $_REQUEST["CI"];
	// check to see if the record actually exists in the database
	$query_recordset = sprintf("SELECT Id_usuario FROM `usuario` WHERE Id_usuario = %s",
		GetSQLValueString($_REQUEST["Id_usuario"], "text")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);
	
	if ($num_rows == 1)
	{ 	
	 
	//------------------------------------------------------------------------------------------------			
	$query_update = sprintf("UPDATE `usuario` SET  Id_usuario = '$usuario' ,CI='$su_ci', rol=%s WHERE Id_usuario= %s", 
	       // GetSQLValueString($_REQUEST["Id_usuario"], "text"), 
			//GetSQLValueString($_REQUEST["CI"], "text"), 
			GetSQLValueString($_REQUEST["rol"], "text"),
			GetSQLValueString($_REQUEST["Id_usuario"], "text")	
			);
		$ok = mysql_query($query_update);	
		
		//------------------------------------------------------------------------
		$query_update2 = sprintf("UPDATE `persona` SET CI='$su_ci', nombre=%s, apellidos = %s WHERE CI= %s", 
			//GetSQLValueString($_REQUEST["CI"], "text"), 
			GetSQLValueString($_REQUEST["nombre"], "text"),
			GetSQLValueString($_REQUEST["apellidos"], "text"),
			GetSQLValueString($_REQUEST["CI"], "text")	
			);
	    	$ok = mysql_query($query_update2);	
		//----------------------------------------------------------------------------------------------		
 		// build and execute the update query
 			$auditoria->agregar_a_auditoria($_REQUEST["username"],"Modificó datos del usuario " .$_REQUEST["Id_usuario"]." en el sistema.","0");
 					
	}
 	else
 	   $ok="false";
 	
 return $ok;	
 	
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
 
function Existe($NombreUsuario)
{	
	if($NombreUsuario)
	{	
		global $conn;
		$query_select_usuario = "SELECT * FROM usuario WHERE Id_usuario = '$NombreUsuario'";	
		$recordset = mysql_query($query_select_usuario, $conn);
		$num_rows = mysql_num_rows($recordset);
		return $num_rows;	
	}
	return 0;
}
 
function insert() {
	global $conn;
	$personal= new CPersona;
	$Usuario=$_REQUEST["Id_usuario"];
	$auditoria = new TAuditoria;
	
    if (Existe($Usuario) > 0) 
	{
	   $toret = array(
			"data" => array("error" => "Lo sentimos ya existe el usuario: $Usuario"), 
			"metadata" => array() );
	}
	else 
	{
	//build and execute the insert query
	 $listo = true;
	 $rol= $_REQUEST["rol"];
	
	  $query_recordset = "SELECT COUNT(*) AS cantidad FROM `usuario` WHERE rol = 'JefeServicio'";
	  $recordset = mysql_query($query_recordset, $conn);
	  $tem_cant = mysql_fetch_array($recordset);
	  $cant_jefe_ser = (int) $tem_cant["cantidad"];
      if($cant_jefe_ser == 0 ){
      	$listo *= $listo;
      }
      else{
      	if($rol == "JefeServicio")
      	   $listo = false;
      }
      if($listo){
      	$personal->agregar_persona($_REQUEST["CI"],$_REQUEST["nombre"],$_REQUEST["apellidos"],$_REQUEST["cargo"],$conn);
	    $query_insert = sprintf("INSERT INTO `usuario` (Id_usuario,CI,contrasena,rol) VALUES (%s,%s,%s,%s)" ,			
	        GetSQLValueString($_REQUEST["Id_usuario"], "text"), # 
			GetSQLValueString($_REQUEST["CI"], "text"), # 
			GetSQLValueString($_REQUEST["contrasena"], "text"), # 
			//GetSQLValueString($_REQUEST["nombre"], "text"), # 
		//	GetSQLValueString($_REQUEST["apellidos"], "text"), # 
			GetSQLValueString($_REQUEST["rol"], "text") # 
	);
	
	    $ok = mysql_query($query_insert);
    
      }  
	  else
	    $ok = false;
	    
	  if ($ok) {
		// return the new entry, using the insert id
	    $auditoria->agregar_a_auditoria($_REQUEST["username"],"Agregó el nuevo usuario " .$_REQUEST["Id_usuario"]." al sistema.","0");
		
		$toret = array(
			"data" => array(
				array(
					"Id_usuario" => $_REQUEST["Id_usuario"], 
					//"CI" => $_REQUEST["CI"], # 
					"contrasena" => $_REQUEST["contrasena"], # 
				//	"nombre" => $_REQUEST["nombre"], # 
					//"apellidos" => $_REQUEST["apellidos"], # 
					"rol" => $_REQUEST["rol"]# 
				)
			), 
			"metadata" => array()
		);
		
	} else {
		// we had an error, return it to show error must change "error"
		$toret = array(
			"data" => array("No permite insertar con este privilegio: JefeServicio" ), 
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
function update() {
	global $conn;

	// check to see if the record actually exists in the database
	$query_recordset = sprintf("SELECT * FROM `usuario` WHERE Id_usuario = %s",
		GetSQLValueString($_REQUEST["Id_usuario"], "text")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);
	
	if ($num_rows > 0) {

		// build and execute the update query
		$row_recordset = mysql_fetch_assoc($recordset);
		$query_update = sprintf("UPDATE `usuario` SET contrasena = %s,nombre = %s,apellidos = %s,rol = %s WHERE Id_usuario = %s", 
			GetSQLValueString($_REQUEST["contrasena"], "text"), 
			GetSQLValueString($_REQUEST["nombre"], "text"), 
			GetSQLValueString($_REQUEST["apellidos"], "text"), 
			GetSQLValueString($_REQUEST["rol"], "text"), 
			GetSQLValueString($row_recordset["Id_usuario"], "text")
		);
		$ok = mysql_query($query_update);
		if ($ok) {
			// return the updated entry
			$toret = array(
				"data" => array(
					array(
						"Id_usuario" => $row_recordset["Id_usuario"], 
						"contrasena" => $_REQUEST["contrasena"], #
						"nombre" => $_REQUEST["nombre"], #
						"apellidos" => $_REQUEST["apellidos"], #
						"rol" => $_REQUEST["rol"]#
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
function delete_usuario() {
	global $conn;
 $auditoria = new TAuditoria;
	// check to see if the record actually exists in the database
       
    $query_recordset = sprintf("SELECT * FROM `usuario` WHERE CI = %s",
		GetSQLValueString($_REQUEST["CI"], "text")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);

	if ($num_rows > 0) {
	
		$row_recordset = mysql_fetch_assoc($recordset);
  //---------------------------------------------------------------------------------
		$query_delete2 = sprintf("DELETE FROM `persona` WHERE CI = %s", 
			GetSQLValueString($row_recordset["CI"], "text")
		);
		$ok *= mysql_query($query_delete2);
   //----------------------------------------------------------------------------------
	
		$query_delete = sprintf("DELETE FROM `usuario` WHERE CI = %s", 
			GetSQLValueString($row_recordset["CI"], "text")
		);
		$ok = mysql_query($query_delete);
				
	}
		if ($ok) {
			// delete went through ok, return OK
	  $auditoria->agregar_a_auditoria($_REQUEST["username"],"Eliminó el  usuario " .$_REQUEST["Id_usuario"]." al sistema.","0");
			
			$toret = array(
				"data" => $_REQUEST["Id_usuario"], 
				"metadata" => array()
			);
		} else {
			$toret = array(
				"data" => array("error" => mysql_error()), 
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
			$ret = delete_usuario();
		break;
		case "Count":
			$ret = rowCount();
		break;
		case "Modificar_Password":
			$ret = Modificar_password();
		break;
		case "BuscarIdusuario":
			$ret = buscar_usuario();
		break;
		case "Modificar_usuario":
			$ret = modificar_usuario();
		break;
		
	}
}


$serializer = new XmlSerializer();
echo $serializer->serialize($ret);
die();
?>
