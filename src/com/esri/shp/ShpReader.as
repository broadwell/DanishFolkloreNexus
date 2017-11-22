package com.esri.shp
{
    import com.esri.ags.geometry.Extent;
    import com.esri.ags.geometry.MapPoint;
    
    import flash.utils.ByteArray;
    import flash.utils.Endian;
    
    import mx.collections.ArrayCollection;
    import mx.controls.Alert;

    public final class ShpReader
    {
        private var m_src:ByteArray;
        private var m_hdr:ShpHeader;
        private var m_xmin:Number;
        private var m_ymin:Number;
        private var m_xmax:Number;
        private var m_ymax:Number;
        //private var m_parts:Array;
        
        //private var points:ByteArray = new ByteArray();
		private var points:Array;
		private var m_parts:ArrayCollection;

        public var recordNumber:int;
        public var contentLength:uint;
        public var contentLengthBytes:uint;
        public var shapeType:int;

        public function ShpReader(src:ByteArray)
        {
            m_src = src;
            m_hdr = new ShpHeader(src);
        }

        public function get shpHeader():ShpHeader
        {
            return m_hdr;
        }

        public function get extent():Extent
        {
            return m_hdr.extent;
        }

        private function readRecordHeader():void
        {
            m_src.endian = Endian.BIG_ENDIAN;

            recordNumber = m_src.readInt();
            contentLength = m_src.readInt();
            contentLengthBytes = contentLength + contentLength - 4;

            m_src.endian = Endian.LITTLE_ENDIAN;

            shapeType = m_src.readInt();
            //Alert.show("Record " + recordNumber + ": content length (bytes) is " + contentLengthBytes + ", type is " + shapeType);
        }

        public function hasMore():Boolean
        {
        	//Alert.show("position is " + m_src.position + " bytesAvailable are " + m_src.bytesAvailable);
            return m_src.bytesAvailable > 0;
        }

        public function readMapPoint():MapPoint
        {
            readRecordHeader();
            const x:Number = m_src.readDouble();
            const y:Number = m_src.readDouble();
            return new MapPoint(x, y);
        }

        public function readMapPoint2(mapPoint:MapPoint):MapPoint
        {
            readRecordHeader();
            mapPoint.x = m_src.readDouble();
            mapPoint.y = m_src.readDouble();
            return mapPoint;
        }

        protected function readParts():void
        {
            readRecordHeader();

            m_xmin = m_src.readDouble();
            m_ymin = m_src.readDouble();
            m_xmax = m_src.readDouble();
            m_ymax = m_src.readDouble();

			//Alert.show("xmin is " + m_xmin + ", ymin is " + m_ymin + ", xmax is " + m_xmax + ", ymax is " + m_ymax);

            const partCount:int = m_src.readInt();
            const pointCount:int = m_src.readInt();

			//Alert.show("part and point counts are " + partCount + " " + pointCount);

            const partOffsets:Array = [];
            // PMB var partOffsets:Array = new Array(partCount);
            while (partCount--)
            {
                partOffsets.push(m_src.readInt());
            }
            //Alert.show("added offsets to part array");
            points = new Array();
            while (pointCount--)
            {
                points.push(m_src.readDouble());
                points.push(m_src.readDouble());
            }
            m_parts = new ArrayCollection();
            var removed:int = partOffsets.shift();
            while (partOffsets.length)
            {
                var split:int = partOffsets.shift() * 2;
                m_parts.addItem(points.splice(0, split - removed));
                removed = split;
            }
            //m_parts.push(points);
            m_parts.addItem(points);
        }

		public function readShpParts():ArrayCollection {
			readParts();
			return m_parts;
		}
/*
        public function readShpPolygon():ShpPolygon
        {
            readParts();
            //return new ShpPolygon(new Extent(m_xmin, m_ymin, m_xmax, m_ymax), m_parts);
            return m_parts;
        }*/
        public function readShpPoints():Array {
        	readParts();
        	//Alert.show("length of points array " + points.length/2 + " points (length / 2)");
        	return points;
        	//return m_parts;
        }

    }
}