#!/bin/bash
rsync -av --exclude='.git' --exclude='node_modules' --exclude='.idea' /Users/majidisaloo/Documents/DCManage/modules/addons/dcmanage/ /Applications/XAMPP/xamppfiles/htdocs/whmcs/modules/addons/dcmanage/
echo "Sync complete."
