<?php

require_once(dirname(__FILE__) . "/Controlconn.php");

class TAuditoria {
     
    function agregar_a_auditoria($usuario, $accion_ejecutada, $es_visita_admin) {
       $listo =false;      
       $fecha = date("Y\-m\-d\ H\:i\:s"); 
     //  $fecha_ultima_visita = date("Y\-m\-d\ H\:i\:s");        
    
	     $query_insert = "INSERT INTO `auditoria` (Id_usuario,fecha,accion_ejecutada) VALUES ('$usuario','$fecha','$accion_ejecutada')";
	     $listo = mysql_query($query_insert);
	   return $listo;	   
    }
        
    function sacar_ultima_fecha_visita(){ 
       //global $conn;
       $query_1 = "SELECT MAX(`id_auditoria`)AS id_aud FROM `auditoria` WHERE `fecha_ultima_visita` <> 0";
       $exito = mysql_query($query_1);
       $arreglo = mysql_fetch_array($exito);							 
	   $id_fecha = $arreglo["id_aud"];       
	   $query_2 = "SELECT `fecha_ultima_visita` FROM `auditoria` WHERE `id_auditoria` = '$id_fecha'";
	   $exito2 = mysql_query($query_2);
	   return $exito2; 	   	
    }
    
   
}
?>
