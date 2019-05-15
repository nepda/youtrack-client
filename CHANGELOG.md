# Changelog

### 2019-05-15 - v1.8.1

* Fixed #62 - Docs improved for setUrl in attachment - Thanks [@ArtemGoutsoul](https://github.com/ArtemGoutsoul)

### 2019-05-09 - v1.8.0

YouTrack is changing the legacy API and dropping support. This will stop some functionality from working.
Maybe it's time to re-create the whole library. Some support work will still be done for this project.

* Fixed #60
* Fixed #59
* Fixed #57
* Fixed #56
* Fixed #55

### 2018-05-14 - v1.7.9

* Fixed #53 - Improved URL parameter handling with proper and unified encoding - Thanks [@zrino](https://github.com/zrino)

### 2018-04-13 - v1.7.8

* Merged #52 - Add: delete workitem method  - Thanks [@hkarasek](https://github.com/hkarasek)

### 2018-02-28 - v1.7.7

* Merged #50 - Version improvements - Thanks [@shane-smith](https://github.com/shane-smith)/[@ethicalwd](https://github.com/ethicalwd)
* Merged #51 - Implement all parameters for GET users endpoint - Thanks [@darthCoffeeCup](https://github.com/darthCoffeeCup)
* Use short array syntax everywhere now

### 2018-02-23 - v1.7.6

* Fixed #48 Check if time tracking is enabled for project - Thanks [@the-JJ](https://github.com/the-JJ)

### 2018-01-23 - v1.7.5

* Fixed #46 Fetch info for current user #47 - Thanks [@the-JJ](https://github.com/the-JJ)
* Changed doc block URL's to generic version https://www.jetbrains.com/help/youtrack/incloud/ 
    without specific version in URL

### 2018-01-17 - v1.7.4

* Just formatting stuff

### 2018-01-17 - v1.7.3

* Fixed #43, Merged #44 - Thanks [@the-JJ](https://github.com/the-JJ)
 
### 2017-08-23 - v1.7.2
 
### 2017-08-22 - v1.7.1

### 2017-05-29 - v1.7.0

* Fixed #31 - Added support for permanent token authentication - Thanks [@the-JJ](https://github.com/the-JJ)
* Marked the old username/password login as deprecated (will be removed in 2.x)

### 2017-05-18 - v1.6.3

* Fixed #37 - Added simple tags array to issues (`foreach ($issue->getTags() as $tag) { echo $tag; }`)

### 2017-04-27 - v1.6.2

* Fixed #36 - Creating and updating comments (added methods `createComment` & `updateComment`)
* Updated URLs to YouTrack docs for version 2017.2

### 2017-03-27 - v1.6.1

* Improved error reporting if returned XML was invalid/couln't be parsed

### 2017-03-15 - v1.6.0

* Merged "Added Version bundle support #35" - [sciamannikoo](https://github.com/sciamannikoo)
* Implemented `\Iterator` interface for `BaseObject`, so you can iterate through all attributes in all YouTrack objects
  * E.g. in `examples/get-version-bundle.php` (example for both: version bundle and iteration)

### 2017-02-03 - v1.5.4

* Added readme section for standalone setup with composer
* Added datetime convert method (will be used for all date attributes in the future). Currently only used for 
  `\YouTrack\Build::$assembleDate`
* Update to PHPUnit 5.* (deprecated `getMock` method replaced)
* Updated the URLs to official documentation pages (Version: YouTrack 2017.1)

### 2017-01-19 - v1.5.3

* Merged from [production-minds](https://github.com/production-minds/youtrack-client)

### 2017-01-19 - v1.5.2

* Added method getSprintById

### 2016-10-03 - v1.5.1

* Fixed #28, added `$verifySsl` parameter to constructor.

### 2016-10-01 - v1.5.0

* Merged #27, Changed verify_ssl default value to true, and fixed some cURL settings according to
 this config - [perk11](https://github.com/perk11)

### 2016-08-11 - v1.4.2

* Merged #26, added PHPDocs for method for Issue

### 2016-08-04 - v1.4.1

* Fixed #25, added support for multiple values for enum fields

### 2016-04-26 - v1.4.0

* Merged #24 Add methods for getting issue history - [pilov-pa](https://github.com/pilov-pa)
* Improved XML call for getting issue history and worktimes and some other calls.

### 2016-04-25 - v1.3.0

* Merged #23 Remove dublicated xml declaration - [pilov-pa](https://github.com/pilov-pa)
* Added AgileBoard settings call

### 2016-02-15 - v1.2.4

* Merged #22 Fix error getting login of the author of WorkItem - [stingmu](https://github.com/stingmu)

### 2016-02-14 - v1.2.3

* Merged #19 Fix of create issue method - [angerslave](https://github.com/Angerslave)
* Removed non project related lines from .gitignore

### 2016-02-11 - v1.2.2

* Merged #19 Re-added PHP 5.4 support, Thanks to Angerslave

### 2016-02-08 - v1.2.1

* Just README and CHANGELOG updates

### 2016-02-08 - v1.2.0

* Merged #15 (new methods available) Thanks [@angerslave](https://github.com/Angerslave)

### 2015-12-06 - v1.1.0

* Dropped support for PHP lower than 5.6
* Merged #14
* Merged #13
* Merged #12
* Merged #11
* Merged #10
* Merged #9
* Merged #8
* Merged #7

### 2016-02-08 - v1.0.10

* A lot of new methods added (updateIssue, deleteIssue, createAttachment, importAttachment, importLinks, importIssues, importWorkitems, getWorkitems)
* Merged #7 from REDLINK/fix-createIssue
* Merged #8 from REDLINK/replace-urlencode-with-rawurlencode
* Merged #9 from REDLINK/feature-createAttachment
* Merged #10 from REDLINK/set-mimetype
* Merged #12 from REDLINK/feature-newIssueMethods

### 2015-02-10 - v1.0.9

* Merged #6 'Fetch issues by filter only + with comments'. Thanks [@chabberwock](https://github.com/chabberwock)

### 2015-01-15 - v1.0.8

* Merged #5 'Fixed executing queries.'. Thanks [@wdamien](https://github.com/wdamien)
* Added example for executing simple command

### 2014-12-02 - v1.0.7

* Improved error handling - On 404 error, the call will throw a `YouTrack\NotFoundException`
* Improved error handling - `YouTrack\Exception`/`YouTrack\Error` is now aware of JSON responses
* Improved connection request method. If the body is array it will no longer check if the file exists (Notice was thrown by PHP)

### 2014-11-29 - v1.0.6

* Fixed Issue #4, Improved `getAccessibleProjects`, see `examples/get-all-projects.php`. Thanks [@openWebX](https://github.com/openWebX)

### 2014-11-29 - v1.0.5

* Fixed Issue #3, Added method `getUserRoles`, see `examples/get-user-roles.php`. Thanks [@openWebX](https://github.com/openWebX)
* Improved exception handling (on 403 errors, an `NotAuthorizedException` will be thrown)
* YouTrack-Exceptions are now `YouTrack\Error` aware (`$e->getYouTrackError()`)

### 2014-11-05 - v1.0.4

* Fixed Issue #2, Throw exception `YouTrack\IncorrectLoginException` on incorrect login or password.

### 2014-10-14 - v1.0.3

* Added support for long parameter values for method `createIssue` (It was not possible to do a request with more than 8205 chars (InCloud, nginx 414-Error))
* Improved DocBlocs for Connection class methods

### 2014-09-01 - v1.0.2

* Added more parameters (full support now) for `executeCommand`. Thanks [@1ed](https://github.com/1ed). See [Apply Command to an Issue](http://confluence.jetbrains.com/display/YTD5/Apply+Command+to+an+Issue)

### 2014-09-01 - v1.0.1

* Added executeCountQueries ([Get Number of Issues for Several Queries](http://confluence.jetbrains.com/display/YTD5/Get+Number+of+Issues+for+Several+Queries)). See `./examples/query-count.php`. (Thanks [Limelyte](https://github.com/Limelyte/youtrack/commit/4e4f30e2a118e20f8f364119c37f3e17f38addfa)).
