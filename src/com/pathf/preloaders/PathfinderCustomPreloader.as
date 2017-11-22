package com.pathf.preloaders
{
	import flash.display.DisplayObject;
	import flash.display.GradientType;
	import flash.display.Sprite;
	import flash.filters.DropShadowFilter;
	import flash.geom.Matrix;
	import flash.text.TextField;
	import flash.text.TextFormat;

	public class PathfinderCustomPreloader extends com.pathf.preloaders.PreloaderDisplayBase
    {
    	[Embed("images/dannebrog_icon.gif") ]
        [Bindable] public var Logo:Class;
		
		[Embed(source="images/nexus_alpha.png") ]
		[Bindable] public var backImg:Class;
		
		/*[Embed(source="../images/title_nisser.gif") ]
		[Bindable] public var titleImg:Class;*/

        private var t:TextField;
        private var f:DropShadowFilter=new DropShadowFilter(2,45,0x000000,0.5)
        private var pathfLogo:DisplayObject;
        private var bar:Sprite=new Sprite();
        private var loadMsg:TextField;
        private var barFrame:Sprite;
		private var mainColor:uint=0xFDF3D8;
		private var barColor:uint=0x450415;
		private var lastProgress:uint = 0;
        //private var mainColor:uint=0x00AEEF;
        //private var mainColor:uint=0x450415;
		
		private var bgimage:DisplayObject;
		//private var titleimage:DisplayObject;
        
        public function PathfinderCustomPreloader()
        {
            super();
        }
        
        // This is called when the preloader has been created as a child on the stage.
        //  Put all real initialization here.
        override public function initialize():void
        {
            super.initialize();
            
			//this.backgroundImage = backImg;
			
            clear();  // clear here, rather than in draw(), to speed up the drawing
            
            var indent:int = 20;
            var height:int = 20;
            
			//creates all visual elements
            createAssets();
		}
		//this is our "animation" bit
        override protected function draw():void
        {
			/*
			if ((lastProgress < 90) && (int(_fractionLoaded*100) > 90)) {
				clear();
				createAssets(2);
			}
			*/
			
            t.text = int(_fractionLoaded*100).toString()+"%";
            //make objects below follow loading progress
            //positions are completely arbitrary
            //d tells us the x value of where the loading bar is at
            var d:Number=barFrame.x + barFrame.width * _fractionLoaded;
            t.x = d - t.width - 25;
            pathfLogo.x = d - pathfLogo.width;
            bar.graphics.beginFill(barColor,1);
            bar.graphics.drawRect(0,0,bar.width * _fractionLoaded,15);
            //bar.graphics.drawRoundRectComplex(0,0,bar.width * _fractionLoaded,15,12,0,0,12);
            bar.graphics.endFill();
        }
        
        protected function createAssets():void
        {
			/*if (pass == 1) {
				//temporarily display the title image
				titleimage = new titleImg;
				titleimage.x = stageWidth/2 - titleimage.width/2;
				titleimage.y = stageHeight/2 - titleimage.height/2;
				addChild(titleimage);
			}
			*/
			
			//if (pass == 2) {
				//create the background splash screen image
        		bgimage = new backImg;
				bgimage.x = stageWidth/2 - bgimage.width/2;
				bgimage.y = stageHeight/2 - bgimage.height/2;
				addChild(bgimage);
			//}
			
        	//create the logo
            pathfLogo = new Logo();
            pathfLogo.y = stageHeight/2 - pathfLogo.height*1.5;
            pathfLogo.filters = [f];
            addChild(pathfLogo);

            //create bar
            bar = new Sprite();
             //bar.graphics.drawRoundRectComplex(0,0,400,15,12,0,0,12);
            bar.graphics.drawRect(0,0,400,15);
            bar.x = stageWidth/2 - bar.width/2;
            bar.y = stageHeight/2 - bar.height/2;
            bar.filters = [f];
            addChild(bar);
            
            //create bar frame
            barFrame = new Sprite();
            barFrame.graphics.lineStyle(2,0xFFFFFF,1)
            //barFrame.graphics.drawRoundRectComplex(0,0,400,15,12,0,0,12);
            barFrame.graphics.drawRect(0,0,400,15);
            barFrame.graphics.endFill();
            barFrame.x = stageWidth/2 - barFrame.width/2;
            barFrame.y = stageHeight/2 - barFrame.height/2;
            barFrame.filters = [f];
            addChild(barFrame);
			
			pathfLogo.x = barFrame.x;
            
            //create text field to show percentage of loading
        	t = new TextField()
            t.y = barFrame.y-27;
            t.filters=[f];
            addChild(t);
            //we can format our text
            var s:TextFormat=new TextFormat("Verdana",18,barColor,null,null,null,null,null,"right");
            t.defaultTextFormat=s;
            
            //PMB Create "Loading" message
        	loadMsg = new TextField();
        	//loadMsg.x = stageWidth/2 - loadMsg.width/2;
        	loadMsg.x = barFrame.x;
        	loadMsg.y = t.y-27;
			loadMsg.filters=[f];
            addChild(loadMsg);
            //we can format our text
            var ts:TextFormat=new TextFormat("Verdana",16,barColor,null,true,null,null,null,"left");
            loadMsg.defaultTextFormat=ts;
            loadMsg.text = "Loading...";
        }
        
        protected function clear():void
        {    
            // Draw gradient background
            var b:Sprite = new Sprite;
			b.graphics.beginFill(mainColor,1);
			b.graphics.drawRect(0,0,stageWidth,stageHeight);
			//b.graphics.endFill();
			addChild(b);
			/*
            var matrix:Matrix =  new Matrix();
            matrix.createGradientBox(stageWidth, stageHeight, Math.PI/2);
            b.graphics.beginGradientFill(GradientType.LINEAR,   
                                        [0x000000, mainColor],             
                                        [1,1],                           
                                        [0,255],
                                        matrix
                                        );
            b.graphics.drawRect(0, 0, stageWidth, stageHeight);
            b.graphics.endFill(); 
            addChild(b);
			*/
			
        }

    }        
}