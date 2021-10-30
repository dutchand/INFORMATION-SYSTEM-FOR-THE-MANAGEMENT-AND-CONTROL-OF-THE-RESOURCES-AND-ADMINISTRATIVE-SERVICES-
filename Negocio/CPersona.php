<?php



 class CPersona
 {
 	
 	
 function agregar_persona($CI,$nombre,$apellidos,$cargo,$conn)
 {
 	global $conn;
 
	$query_insert = sprintf("INSERT INTO `persona` (CI,nombre,apellidos,cargo) VALUES (%s,%s,%s,%s)" ,	GetSQLValueString($_REQUEST["CI"], "text"), # 
			GetSQLValueString($_REQUEST["nombre"], "text"), # 
			GetSQLValueString($_REQUEST["apellidos"], "text"), # 
			GetSQLValueString($_REQUEST["cargo"], "text") # 
			
	);
	$ok = mysql_query($query_insert);
	
 	return $ok;
 }
 	
 	function delete_persona ($CI)
 	{
 		global $conn;
 		
 	$query_recordset = sprintf("SELECT * FROM `percona` WHERE CI = %s",
		GetSQLValueString($_REQUEST["CI"], "text")
	);
	$recordset = mysql_query($query_recordset, $conn);
	$num_rows = mysql_num_rows($recordset);

	if ($num_rows > 0) 
	{
		
		$row_recordset = mysql_fetch_assoc($recordset);
		$query_delete = sprintf("DELETE FROM `persona` WHERE CI = %s", 
			GetSQLValueString($row_recordset["CI"], "text")
		);	
		
		$ok = mysql_query($query_delete);	
	}
 		return $ok;
 		
 	}
 	
 	
 	
 }
 
?>
