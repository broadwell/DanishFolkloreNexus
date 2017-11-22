package com.esri
{
	import com.esri.ags.layers.ArcGISTiledMapServiceLayer;
	
	import flash.net.URLRequest;
	import flash.net.URLRequestHeader;

	public class NewArcGISTiledMapServiceLayer extends ArcGISTiledMapServiceLayer
	{
		private var m_baseURL : String;
		
		//private var _baseURL:String = "data/tiles/Denmark";
		
		/*private function padWithZeroes(inputString:String, desiredLength:int=0):String {
			
			while (inputString.length < desiredLength) {
				inputString = "0" + inputString;
			}
			return inputString;
		}*/
		
		public function NewArcGISTiledMapServiceLayer(url:String=null)
		{
			super(url);
		}
 		/*
		override public function set url(value:String):void
  		{
        	super.url = value;
        	if( value )
        	{
            	var index : int = value.lastIndexOf( "/" );
            	m_baseURL = value.substr( 0, index );
        	}
        	else
        	{
            	m_baseURL = "";
        	}                
    	}        
        */
    	override protected function getTileURL(
        	level:Number,
        	row:Number,
        	col:Number
    	):URLRequest
    	{
    		//var levelHex:String = padWithZeroes(level.toString(10), 2);
    		//var rowHex:String = padWithZeroes(row.toString(16), 8);
    		//var colHex:String = padWithZeroes(col.toString(16), 8);
    		
    		/* Directory structure on danishfolklore.s3.amazonaws.com is as follows:
    		/L + Level number in hex code, 2 digits
    		/R + Row number in hex code, 8 digits
    		/C + Column number in hex code, 8 digits
    		.png */
        	//return new URLRequest( m_baseURL + "/l" + level + "r" + row + "c" + col + ".jpg" );
        	/* Row naming scheme is a bit messed up for Level 13: the "R" is missing from the directory name */
        	/*if (level == 13)
        		return new URLRequest(m_baseURL + "/L" + levelHex + "/" + rowHex + "/C" + colHex + ".png");
        	else*/
        		//return new URLRequest(m_baseURL + "/L" + levelHex + "/R" + rowHex + "/C" + colHex + ".png");
        		return new URLRequest(super.url + "/tile/" + level + "/" + row + "/" + col);
        	/*import mx.controls.Alert;
        	Alert.show("requesting tile URL " + _baseURL + "/L" + levelHex + "/R" + rowHex + "/C" + colHex + ".png");
        	*/
        	
    	}


	}
}