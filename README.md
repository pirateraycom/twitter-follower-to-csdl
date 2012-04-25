# Twitter followers -> DataSift CSDL

Convert all the followers of an account into a single CSDL hash

## Usage

* Clone this repo
* Change into the directory
* Copy example-config.php to config.php and fill in all the details (see below for how to get Twitter credentials)
* `php fetch.php <screen_name_here>`
* The DataSift CSDL hash will be created
* Any errors will be reports

## Getting twitter credentials

* Go to dev.twitter.com and login with your twitter account
* Create an application
* Get the Consumer Key and Consumer Secret and place in the config.php file
* At the bottom of the screen choose "Create Access Tokens"
* Grab the access token and access token secret and put into the config.php file

