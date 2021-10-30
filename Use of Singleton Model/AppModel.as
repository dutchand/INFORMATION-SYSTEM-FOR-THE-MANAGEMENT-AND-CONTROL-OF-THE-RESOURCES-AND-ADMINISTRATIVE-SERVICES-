package Modulos.Model
{
	import mx.collections.ArrayCollection;
	
   [Bindable]
	public class AppModel 
	{
		private static var _instance:AppModel;
		
		
		public function AppModel()
		{
			if( _instance != null )
			{
				throw new Error( "Model is a singleton. Use Model.instance instead." );
			}
			_instance = this;
	 	}
		
		public static function get instance():AppModel
		{
			if(_instance == null){
				_instance = new AppModel();
			}
			return _instance;
		}
		
		public static var vo:*;
		public static var vo1:*;
		public static var vo2:*;
		public static var vo3:*;
		public static var vo4:*;
		public static var vo5:*;
		public static var vo6:*;
		public static var vo7:*;
		
		public static var UsuarioDestino:String; 
		
		public static var UsuarioOrigen:String;
		
		public static var CuentaBancaria:String;
		
		public static var Asunto:String;
		
		public static var Mensaje:String;
		
		public static var Detalles:*;
		
		public var indice:int = 0;
						
		public static var teo:ArrayCollection = new ArrayCollection(); 
	
	    public static var detalleproducto:ArrayCollection = new ArrayCollection();
		
        public var usuario:String = AppModel.nombre_usuario;
	    public var roles:String = AppModel.privilegiado;
	 
	    public static var adminstrador:String = "Administrador.swf";
	    public static var especialista_contabilidad:String = "EspecialistaContabilidad.swf";
	    public static var jefe_servicio:String = "JefeServicio.swf";
	    public static var jefe_recreacion:String = "JefeRecreacion.swf";
        public static var jefe_almacen:String = "JefeAlmacen.swf";

	  	    
        public static var privilegiado:String = "";
	    public static var nombre_usuario:String = ""; 	
	}
}