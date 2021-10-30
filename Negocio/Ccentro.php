<?php

class Ccentro
{
	
	
function mostrar_centros()
		{
			global $conn;
			
			$query_select = "SELECT Id_centro FROM centro_de_costo";
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
	
	
	
	
	
function mostrar_centros_carta()
		{
			global $conn;
			
			$query_select = "SELECT DISTINCT Id_centro FROM carta_menu";
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
