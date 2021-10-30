<?php
require_once(dirname(__FILE__) . "/Controlconn.php");


class CPlato
{
	
	
function mostrar_platos()
		{
			global $conn;
			
			$query_select = "SELECT Id_plato FROM plato";
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
	


//---------------------------------------------------------------------------------------------	
	
function Insertar_platos_pedido($Id_pedido,$Id_plato,$cant_raciones)
{
	global $conn;

	//build and execute the insert query
	$query_insert = sprintf("INSERT INTO `plato_del_pedido` (Id_pedido,Id_plato,cant_raciones) VALUES (%s,%s,%s)" ,			GetSQLValueString($_REQUEST["Id_pedido"], "text"), # 
			GetSQLValueString($_REQUEST["Id_plato"], "text"), # 
			GetSQLValueString($_REQUEST["cant_raciones"], "int")# 
	);
	$ok = mysql_query($query_insert);
	
	if ($ok) {
		// return the new entry, using the insert id
		$toret = array(
			"data" => array(
				array(
					"Id_plato" => $_REQUEST["Id_pedido"], 
					"precioventa" => $_REQUEST["Id_plato"], # 
					"normatecnica" => $_REQUEST["cant_raciones"]# 
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
//	
//	
//	function eliminar_platos()
//{
//	
//	global $conn;
//
//	// check to see if the record actually exists in the database
//	$query_recordset = sprintf("SELECT * FROM `plato_del_pedido` WHERE Id_pedido = %s",
//		GetSQLValueString($_REQUEST["Id_pedido"], "text")
//	);
//	
//	$recordset = mysql_query($query_recordset, $conn);
//	$num_rows = mysql_num_rows($recordset);
//
//	if ($num_rows > 0)
//	 {
//		$row_recordset = mysql_fetch_assoc($recordset);
//		$query_delete = sprintf("DELETE FROM `plato_del_pedido` WHERE Id_pedido = %s", 
//			GetSQLValueString($row_recordset["Id_pedido"], "text")
//		);	
//		
//		//$ok = mysql_query($query_delete);		
//	
//     }
// }	
//	
	
}


?>
