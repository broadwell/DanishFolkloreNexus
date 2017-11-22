package com.esri.shp
{
    import com.esri.ags.geometry.Extent;
    
    import mx.controls.Alert;
    
    import flash.utils.ByteArray;
    import flash.utils.Endian;

    public final class ShpHeader
    {
    	public var fileCode:int;
        public var fileLength:int;
        public var version:int;
        public var shapeType:int;
        public var xmin:Number;
        public var ymin:Number;
        public var xmax:Number;
        public var ymax:Number;
        public var zmin:Number;
        public var zmax:Number;
        public var mmin:Number;
        public var mmax:Number;

        public function ShpHeader(src:ByteArray)
        {
            src.endian = Endian.BIG_ENDIAN;
            
            //const signature:int = src.readInt();
			//Alert.show("signature is " + signature);
			/* PMB if the signature's wrong, we have bigger problems!
            if (signature != 9994)
            {
                throw new Error("Not a valid signature. Expected 9994");
            }*/
            
            fileCode = src.readInt();
            //Alert.show("fileCode is " + fileCode.toString());
            
            src.position += 5 * 4;

			// File length is in 16-bit words for some reason
            fileLength = src.readInt();
			//Alert.show("fileLength is " + fileLength*2 + " bytes");
			
            src.endian = Endian.LITTLE_ENDIAN;

            version = src.readInt();

            shapeType = src.readInt();
			//Alert.show("shapeType is " + shapeType);

            xmin = src.readDouble();
            ymin = src.readDouble();
            xmax = src.readDouble();
            ymax = src.readDouble();
            zmin = src.readDouble();
            zmax = src.readDouble();
            mmin = src.readDouble();
            mmax = src.readDouble();
            //Alert.show("xmin: " + xmin + "ymin: " + ymin + "xmax: " + xmax + "ymax: " + ymax + "zmin: " + zmin + "zmax: " + zmax + "mmin: " + mmin + "mmax: " + mmax);
            // trace(xmin, ymin, xmax, ymax);
        }

        public function get extent():Extent
        {
            return new Extent(xmin, ymin, xmax, ymax);
        }

    }
}