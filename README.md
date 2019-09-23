# jenkins-api-helper

For one project I built in my company I wanted to trigger jobs in Jenkins
from within the webapp. I created this class as a helper/wrapper to abstract away
all the handling when using the Jenkins REST API.  
It's pretty tailored to my use cases because of that, though.

Now I extracted that _'helper'_ from the project to upload it to GitHub,
thinking that it may help somebody else.

Why did I create this instead of using the existing _JenkinsKhan_ project?  
Actually I don't remember, either I had problems using it or I didn't want
to use dependencies for that. ¯\\\_(ツ)\_/¯


## Usage

Instantiate the helper first
```php
$jenkins = new JenkinsHelper('https://jenkins-domain.tld');
```
or, if you use Basic Authentification
```php
$jenkins = new JenkinsHelper('https://jenkins-domain.tld', 'user', 'password');
```

Then you can build/launch a job
```php
$jenkins->build('my-job');
```

This method also catches the response headers Jenkins may set,
which you can get via
```php
$queueUrl = $jenkins->getResponseHeader('location');
```
This example will actually give back a queue URL of your build,
which you can use to get the data of your build
```php
$jenkins->getBuildData($queueUrl);
```
This method is blocking and waits for the build to finish, though.