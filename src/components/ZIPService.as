package components
{
	import com.nochump.util.zip.ZipEntry;
	import com.nochump.util.zip.ZipFile;
	
	import flash.utils.IDataInput;
	
	import mx.core.mx_internal;
	import mx.messaging.Channel;
	import mx.messaging.ChannelSet;
	import mx.messaging.messages.IMessage;
	import mx.rpc.AsyncToken;
	import mx.rpc.http.mxml.HTTPService;
	
//	import mx.controls.Alert;
    
    use namespace mx_internal;

    public class ZIPService extends HTTPService {
    	
    	protected static var binaryChannel:Channel;

    	protected static var binaryChannelSet:ChannelSet;

 		public function ZIPService(rootURL:String=null, destination:String=null)
		{
			super(rootURL, destination);
		}

        override public function send(parameters:Object = null):AsyncToken

    	{
			
    		if ( (this.url.indexOf(".zip") === -1) && (this.url.indexOf(".dfl") === -1) ) {
    			return super.send(parameters);
    		}
    		
            if ( useProxy == false )
            {
                /* force the use of our binary channel */
                if ( binaryChannelSet == null )
                {
                    var dcs:ChannelSet = new ChannelSet();
                    binaryChannel = new DirectHTTPBinaryChannel("direct_http_binary_channel");
                    dcs.addChannel(binaryChannel);            
                    channelSet = dcs;
            		binaryChannelSet = dcs;
                }

                else if ( channelSet != binaryChannelSet )
                {
                    channelSet = binaryChannelSet;
                }       			
            }

            return super.send(parameters);	
    	}

        override mx_internal function processResult(message:IMessage, token:AsyncToken):Boolean

        {
        	if ( (this.url.indexOf(".zip") === -1) && (this.url.indexOf(".dfl") === -1) ) {
        		return super.processResult(message, token);
        	}

        	/* Otherwise, we're trying to load a ZIP file */
            var body:Object = message.body;
            if (body == null )

            {	
			    _result = null;
                return true;
            }

            else if ( body is IDataInput )

            {
				const zipFile:ZipFile = new ZipFile(body as IDataInput);
        		for each (var entry:ZipEntry in zipFile.entries) {
        			const name:String = entry.name.toLowerCase().replace(/\.(xml|shp)/,'');
            		if (this.url.indexOf(name) !== -1) { 
            			message.body = zipFile.getInput(entry).toString();
						//Alert.show("returning unzipped file for " + name);
                		return super.processResult(message, token);	
            		}
        		}
            }
            return false;
        }
    }
}