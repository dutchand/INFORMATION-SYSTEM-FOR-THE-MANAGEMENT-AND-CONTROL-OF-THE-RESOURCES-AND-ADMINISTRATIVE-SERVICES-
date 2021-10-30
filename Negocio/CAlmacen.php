<?php
/*
 * Created on Mar 15, 2010
 *
 * To change the template for this generated file go to
 * Window - Preferences - PHPeclipse - PHP - Code Templates
 */
require_once(dirname(__FILE__) . "/Controlconn.php");
 
 
  

  
 class CAlmacen
 {
 	   
//--------------------------------------------------------------------------------------------------------- 	    
 	function agregar_producto_almacen($Id_producto,$cantidad_producto,$fecha_entrada,$conn)
 	{   global $conn;
 	  
 	   $fecha_entrada = date("Y\-m\-d\ H\:i\:s"); 
 	   $_REQUEST["fecha_entrada"]=$fecha_entrada;
 	global $conn;
 		$query_insert = sprintf("INSERT INTO `almacen` (Id_producto,cantidad_producto,fecha_entrada) VALUES (%s,%s,%s)" ,		GetSQLValueString($_REQUEST["Id_producto"], "text"), # 
			GetSQLValueString($_REQUEST["cantidad_producto"], "text"), # 
			GetSQLValueString($_REQUEST["fecha_entrada"], "text") # 
	);
	      $ok = mysql_query($query_insert);
	 	
 	       return $ok;
 	}
 	
//----------------------------------------------------------------------------------------------------------


 	
 	function modificar_producto_almacen($Id_producto,$new_cant,$fecha_modify,$conn)
 	{
 		global $conn;
 		$fecha_modify = date("Y\-m\-d\ H\:i\:s"); 
 	   $_REQUEST["fecha_modify"]=$fecha_modify;
 		$query_recordset = sprintf("SELECT Id_producto FROM `almacen` WHERE Id_producto = %s ", 
		    GetSQLValueString($_REQUEST["Id_producto"], "text")
		   
	    );
	
	
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);	
	if ($num_rows == 1)
	{ 	
 		$query_update2 = sprintf("UPDATE `almacen` SET cantidad_producto = cantidad_producto + %s, fecha_entrada = %s WHERE Id_producto= %s",
	  
			GetSQLValueString($_REQUEST["new_cant"], "text"), 
			GetSQLValueString($_REQUEST["fecha_modify"], "text"),
			GetSQLValueString($_REQUEST["Id_producto"], "text")
		);
		$ok = mysql_query($query_update2);	
			
	}
 	else
 	   $ok="false";
 	
 return $ok;	
 	
 }
  
function mostrar_productos()
		{
			global $conn;
			$query_select = "SELECT Id_producto FROM almacen";
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
  
  
 }
?>
