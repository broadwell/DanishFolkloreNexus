package
// PMB XXX How do I give this a named package? Using the default empty package name is discouraged.
{
	import com.esri.aws.awx.geom.PointShape;
	import com.esri.aws.awx.map.layers.overlays.BubbleMarker;
	import com.esri.aws.awx.map.layers.overlays.style.IStyle;

	public class InformantBubbleMarker extends BubbleMarker
	{
		private var expanded:Boolean = false;
		
		public function InformantBubbleMarker(pointShape:PointShape=null, style:IStyle=null)
		{
			super(pointShape, style);
		}
		
		public function toggle():void
		{
			if (expanded) {
				super.collapse();
				expanded = false;
			} else {
				// Make sure it's on top.
				//super.rollOver();
				super.click();
				expanded = true;
			}
		}
		
	}
}