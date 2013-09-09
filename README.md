VuGen Local Replay
==================

Enables replay of web-based VuGen scripts against localhost during script development.

Installation Instructions
-------------------------

The installation instructions assume that you are using Windows.

1.  Install Apache and PHP. 
    The easiest way to do this is to install [WampServer](http://www.wampserver.com/en/).
    To check that it is working, navigate to [http://localhost/](http://localhost/) and check that 
    the WampServer Homepage is displayed.
2.  Sign up for a [GitHub](https://github.com/users) account, install the [GitHub for Windows]
    (http://windows.github.com/) client and clone the [VuGenLocalReplay](https://github.com/MyLoadTest/VuGenLocalReplay) repository.
    You should have now have a copy of the VuGenLocalReplay repository somewhere like 
    C:\Documents and Settings\Administrator\My Documents\GitHub\VuGenLocalReplay
3.  Reconfigure Apache by editing httpd.conf.
    The file is probably located at C:\wamp\bin\apache\Apache2.4.4\conf\httpd.conf
    
    Enable mod_rewrite by uncommenting this line:
        
        #LoadModule rewrite_module modules/mod_rewrite.so
    
    Change the DocumentRoot to point to the VuGenLocalReplay repository.
    
        # Change this...
        DocumentRoot "c:/wamp/www"
        <Directory "c:/wamp/www">
        
        # ...to this...
        DocumentRoot "C:\Documents and Settings\Administrator\My Documents\GitHub\VuGenLocalReplay/www"
        <Directory "C:\Documents and Settings\Administrator\My Documents\GitHub\VuGenLocalReplay/www">
    
    You will need to restart Apache for these changes to take effect.
    Click on the WampServer icon in the system tray, and select "Restart All Services".
    Confirm that your changes worked, by navigating to [http://localhost/](http://localhost/).
    You should see the VuGenLocalReplay homepage.
    
4.  Copy a recently recorded VuGen script to .\GitHub\VuGenLocalReplay\www\vugen
    Note that there should only be one VuGen script in this directory at a time.
5.  Open http://localhost/ for further configuration instructions.
    It will instruct you to modify your hosts file (C:\WINDOWS\system32\drivers\etc\hosts)
    and may also require you to change the listening ports in the httpd.conf file. 
    
Potential Problems
------------------

*   Sites that use HTTPS
*   Websites that include content from multiple host names
*   Websites that do not run on port 80, or that run on multiple ports
