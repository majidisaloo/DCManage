#!/bin/bash
ssh mh@my.onlineserver.ir "cd /home/mh/public_html/whmcs/modules/addons/dcmanage; git pull; chown -R mh:mh ."
echo "Live Sync complete."
