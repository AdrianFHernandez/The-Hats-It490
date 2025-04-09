Authorize ssh using '''ssh-copy-id user@remote_host'''


Create bundle.ini like the following. 

[bundle]
name = "login_pkg"
host_type = "WEB"
version = 1

[files]
include[] = "Clusters.ini"
include[] = "see_test.txt"

[commands]
execute[] = "echo \"Starting post-installs   hh tasks\n\""
sudo[] = "cp -f see_test.txt /var/www/sample/"

[processes]
bounce[] = "apache2"

