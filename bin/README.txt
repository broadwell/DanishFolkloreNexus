Danish Folktales, Legends, and Other Stories
Edited and Translated by Timothy R. Tangherlini
University of Washington Press, 2013

Interface design by Peter M. Broadwell and Timothy R. Tangherlini

TECHNICAL DOCUMENTATION AND TROUBLESHOOTING GUIDE
================================================= 

This README file contains instructions for running the installation
programs provided on the DVD for Windows and Mac OS X, as well as
information about the software required to view the Danish Folklore
Nexus, instructions on how to troubleshoot problems with network or file
system access from within the Danish Folklore Nexus, and suggestions for
installing and running the Danish Folklore Project on other operating 
systems, including Linux and mobile devices.

Installing on Windows or Mac OS X
-------------------------------------------------

To install the Danish Folklore nexus from the DVD, simply run the installer
program that corresponds to your computer's operating system. These 
installers can be found in the base folder on the disc. For Mac OS X, 
open "DFLMacInstaller.pkg" and for Windows, run "DFLWindowsInstaller.exe".

Each installer provides three installation options: 
(a) run the digital materials entirely from the DVD
(b) install all of the materials except for the maps onto your hard disk 
(unfortunately, this option requires that you have an Internet connection
in order for the maps to appear)
(c) install all of the materials including the maps to your hard disk. This 
option offers the best performance but also requires 1.75 gigabytes of free
space.

After running the install program, you should relaunch your Web browser 
before attempting to access the Danish Folklore Nexus. To launch the
Danish Folklore Nexus, simply double click on the file labeled "Launch 
Danish Folklore.html" on the disc or on your computer, depending on the 
installation option you have chosen. You may also choose to manually open 
this file from within your preferred Web browser. If you elected to install 
the Danish Folklore Nexus onto your hard disk and have a recent version of 
Mac OS X or Windows, you also may find a "Danish Folklore" entry in 
your "Applications" or "All Programs" menu, which will provide a shortcut
to launch the Danish Folklore Nexus in a Web browser.

When away from the computer on which the digital materials are installed, 
a reduced content version of the Danish Folklore Nexus can be accessed at 
http://www.purl.org/danishfolktales/
Updates and bug fixes can be downloaded from 
http://www.purl.org/danishfolktales/updates

Troubleshooting installation problems
-------------------------------------------------

The installation program may fail if you do not have user-level permissions
to install files on the computer, if Adobe Flash Player is not installed,
or if you are trying to run the installation program on an unsupported 
operating system. At present, Windows XP and newer are supported, as are
Mac OS X versions 10.4 and above. You may download a copy of Adobe Flash
Player at the URL given below. If your user account does not have
permission to install programs on the local computer, you may need to ask
your system administrator to help you install the Danish Folklore Nexus.

For recent versions of Mac OS X, you may need to control-click the
DFLMacInstaller.pkg file, then select "Open" from the menu that appears and
confirm via the subsequent dialog box that you wish to install a program
from an unidentified developer.

Troubleshooting browser access problems
-------------------------------------------------

Note that the maps window of the Danish Folklore Nexus will switch
automatically to a reduced level of functionality when it detects a
network problem that blocks access to the online maps. Once the connection
to the Internet has been restored, clicking the "Go online" button in the
map window will restore the more detailed base (street) and aerial maps.

If your browser experiences a problem accessing the Danish Folklore data
files, you will see see an error message in your Web browser when you try
to launch the Danish Folklore Nexus. Most of the  interface will remain
blank, although a map may appear in the map window.

First, try closing your Web browser and running the install program again.
If this does not solve the problem, try opening "Launch Danish 
Folklore.html" in a different Web browser. This may enable you to avoid
further configuration steps; recent versions of the Google Chrome browser
in particular require special settings changes to run the Danish Folklore
Nexus (see the next section).

Your browser may also warn you via a pop-up message about potential 
unauthorized network access when you first try to view "Launch Danish 
Folklore.html" and may give you the option of changing the network access 
settings for the Danish Folklore Nexus. Sometimes, however, this message
is not generated, or it is intercepted by the the browser's pop-up blocker.

Configuring Flash Player "trust" settings
-------------------------------------------------

The solution to most browser access problems is to ensure that the Adobe
Flash Player "trusts" the location from which it is running the Danish
Folklore Nexus and will grant it the file system and network access that
the it needs to run normally.

Recent versions of Google Chrome, however, use a proprietary version of the
Adobe Flash Player that does not obey custom "trust" settings, and
therefore must be disabled in favor of the standard Adobe Flash Player in
order for the Danish Folklore Nexus to work properly. This can be done by
typing "chrome://plugins" into the Chrome URL bar, then locating the Adobe
Flash Player section in the resulting list. Most systems will have two
entries in this section; the entry of type "PPAPI (out-of-process)" and
with a Location field that ends in "PepperFlashPlayer.plugin" should be
disabled by clicking on its  "Disable" link. Please ensure that the entry
of Type "NPAPI" is enabled before restarting Chrome and reloading the
Danish Folklore Nexus.

You may skip the above steps if you are not using Google Chrome.
The primary step in resolving access problems is to manually grant the
Danish Folklore Nexus access to the Internet and the files stored locally
on your computer by adding the "Danish Folklore" folder to a list of
locations trusted by the Flash Player. You may access this list via two
different interfaces:

More recent versions of the Adobe Flash Player use a settings manager
labeled "Flash Player" that is accessible from the "System Preferences"
panel in Mac OS X, or the Control Panel in Windows (usually under the
"System and Security" sub-panel). This panel also may be accessed by
right-clicking (Windows) or control-clicking (Mac OS X) the Danish Folklore
Nexus in a Web browser and choosing "Global Settings" in the menu that
appears.

Once you have opened the Flash Player settings manager, select the 
"Advanced" tab at the top, then scroll down to the "Developer Tools"
section and click on the "Trusted Location Settings" button. This will
bring up a list of trusted locations. Click the "Add" button.

For systems running Mac OS X, you should choose the location
/Volumes/DFL/Danish Folklore/
if you wish to run the Danish Folklore Nexus directly from the DVD.
If, however, you have chosen to install some or all of the data files to 
your local disk, you will also want to select the location of the 
"Danish Folklore" folder on the local computer, which is most likely
/Application/Danish Folklore/

To run the Danish Folklore Nexus directly from the DVD on a Windows-based 
system, the location of the files will be the letter of the drive 
containing the Danish Folklore disc, followed by the "Danish Folklore" 
directory. For many Windows-based systems, this value will be
D:\Danish Folklore\
The location of the files if you have installed them onto your Windows
computer is most likely
C:\Program Files\Danish Folklore\

Once you have entered the trusted location(s) in the input field, click 
"Confirm." You have now instructed the Flash Player to allow the Danish 
Folklore Nexus access to the data files and potentially the map files
stored on the disc, on your computer, or on the Internet.

For these new settings to take effect, you must close your Web browser and 
then re-open the "Launch Danish Folklore.html" file in the "Danish
Folklore" folder on the DVD or your computer. If you have installed the
Danish Folklore Nexus locally, you may also be able to run the "Danish 
Folklore" shortcut in the Windows Start menu or Mac OS X Applications menu.

Alternately, you can enter the "trusted" locations using a version of the
Flash Player Settings Manager that is accessed via the web browser. For
newer versions of Adobe Flash Player, this option is superseded by the
local settings manager described above, but it may be necessary to us it
with older versions of Adobe Flash Player. To access the panel, open one of
the following URLs with your Web browser:
http://www.purl.org/danishfolktales/flash
or
http://www.macromedia.com/support/documentation/en/flashplayer/help/settings_manager04.html

Under the Global Security Settings tab in the Settings Manager window
(the tab looks like a globe with a padlock in front of it), it is best to
have the "Always ask" button selected. Add the location of the Danish 
Folklore files on the disc and/or the local file system to the "Always
trust files in these locations" box clicking the "Edit locations..."
pulldown menu, selecting "Add location..." and choosing the locations
mentioned above.

Tips for Linux and other operating systems 
-----------------------------------------------

The Danish Folklore Nexus can run on any operating system, including Linux, 
provided it supports a modern Web browser and the Adobe Flash Player
software. Although the DVD provides installation programs only for Mac OS X
and Windows, it is fairly easy to perform a manual installation of the
Danish Folklore Nexus on most other systems simply by copying files from 
the DVD to locations on the local file system.

If you wish to run the Danish Folklore Nexus entirely from the local
file system, simply copy the full contents of the "Danish Folklore" folder
on the DVD to a location on your computer. If you would like to avoid
copying the large map files to your computer and instead wish to load the 
maps on demand via the Internet, copy all of the contents of the "Danish
Folklore" folder to your disk *except* for the "tiles" folder.

To view the Danish Folklore Nexus in a Web browser from either the local
disk or the DVD, you need to configure the Flash Player to "trust" the 
location of the "Danish Folklore" folder, meaning that it will allow the 
Danish Folklore Nexus Flash viewer to access the files stored on the
DVD or local disk and possibly also the Internet. There are two ways to 
enable this access:
1) Manually add the location of the "Danish Folklore" folder to the list
of trusted locations via the Adobe Flash Player Global Settings Manager, as
described in the Troubleshooting section above.
2) Create a Flash "trust" configuration file and place it in the
appropriate folder on the local file system. Such a "trust" file simply
lists the location of the "Danish Folklore" folder to be granted
file system and/or Internet access in order to run the Danish Folklore
Nexus. You can view examples of the "trust" files for Windows and
Mac OS X installations in the "Files" folder of the DVD; the trust files
use the file extension ".cfg".
The trust file must be placed in a folder that the Flash Player
consults when running a new program. The location of this folder varies 
by operating system, and you may need to consult the Adobe Flash Player 
online documentation to find the proper location for your system.
On computers running Windows XP, this location is usually
C:\Windows\System32\Macromed\Flash\FlashPlayerTrust
but for Windows 7 and later, it is in the user's AppData folder, i.e.,
C:\Users\USERNAME\AppData\Roaming\Macromedia\Flash Player\#Security\FlashPlayerTrust
and for Mac OS X, it is
/Library/Application Support/Macromedia/FlashPlayerTrust
or
/Users/USERNAME/Library/Preferences/Macromedia/Flash Player/#Security/FlashPlayerTrust

Installing a Web Browser and Flash
-------------------------------------------------

If your browser is unable to display the Danish Folklore data, you may 
need to install an appropriate Web browser and Adobe Flash software. The
interface should work with any browser that supports Adobe Flash Player.
These include the most up-to-date versions of Mozilla Firefox, Safari for 
Mac OS X, Internet Explorer, and Google Chrome.
Note that some versions of Google Chrome use a proprietary Adobe Flash
Player plugin by default that is *not* compatible with the Danish Folklore
Nexus. Please consult the troubleshooting section above for instructions
on how to disable this version of the Adobe Flash Player in favor of the
standard Flash Player.

You can download and install the latest version of Adobe Flash Player from
this address:
http://get.adobe.com/flashplayer/

The Danish Folklore Nexus on mobile devices
-------------------------------------------------

Mobile phones and tablets often do not support Flash-based applications
like the Danish Folklore Nexus. Some third-party apps, however, do provide
this capability, including the Puffin Web browser, which is available for
iOS and Android devices:
http://www.puffinbrowser.com

In the event that future mobile and desktop computing environments entirely 
revoke their support of Flash in favor of newer technologies, a 
next-generation version of the Danish Folklore Nexus may become available
at the permanent update URL mentioned above:
http://www.purl.org/danishfolktales/updates

End User License Agreements (EULA)
-------------------------------------------------

End User License Agreements (EULA) for each of the installed components are 
included in the respective download packages.
