# deal-challenge

This project provides the following functionality

* Given a URL, provide a shortened URL redirect wrapper. The shortened URL when accessed, will record the access request details and redirect the user to the original request. 
 
## Requirements

* [Vagrant](https://www.vagrantup.com/)
 

## Installation

After cloning the repository, Execute `vagrant up` in the project root directory.

Once deployed, the project may be accessed via [Localhost](https://localhost:8443)


## Development

This project is based on the [Symfony](https://symfony.com/) framework. 

Symfony was selected for comfort but also happens to be a very well supported and enterprise quality framework. 

Bootstrap 4 has been used for some basic styling.


## Testing

To run the current test suite, execute `phpunit` in the project root directory.

## Solution Notes

This is a pure Redis solution though a MySQL, Postgres, or SqLite database backend would be a better option in hindsight.

Pagination should be added as the lists currently only return the last 10 URL's created.

Shortened URL's are not completely short. They are simply a Base-62 Encoding based on a SHA-256 hash of the original URL.

## TODO

Every Long URL should always lead to the same Base-62 Encoding. From here, We can further shorten the URL based on an available sequence. 