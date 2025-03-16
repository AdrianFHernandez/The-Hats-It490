#!/usr/bin/bash

# Setting up the project environment
echo "Setting up the project environment"
sudo cp -f otherFiles/.htaccess /var/www/sample/
sudo cp -f otherFiles/001-sample.conf /etc/apache2/sites-available/

# Enabling the site
echo "Enabling the site"
sudo a2ensite 001-sample.conf



# Building frontend
cd WebServer/frontend && npm run build
cd ../
ls
# Moving backend to the web server directory
sudo cp -r -f backend /var/www/sample/

# Restarting the Apache server
echo "Restarting the Apache server"
sudo service apache2 restart

echo "Project setup complete"

# Open the browser
# open http://www.sample.com
