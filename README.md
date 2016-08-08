## GitHub script for releases

This is a script to update the milestones across multiple repos
in the same way. It's main purpose is to create new milestones.

Whom to ask if something is unclear: [Morris Jobke](https://github.com/morrisjobke)

### How to run it

```
$ composer install
$ cp credentials.dist.json credentials.json
$ # add your GitHub API token to credentials.json
$ cp config.dist.json config.json
$ # configure the behaviour - see config section below
$ php releases.php
```

This will run the script in dry mode. To actually do the API call you need to uncomment one of the 6 `continue;` statements in `releases.php`. Each has a comment for which update call it is good for. As a hint: always just comment **one** of the 6 `continue`statements. The **rename** operations should always be executed **before** the **add** operations. So following order is recommended:

* run php releases.php & check output
* comment `continue` of rename of milestone
* run php releases.php & check output
* uncomment `continue` of rename of milestone
* comment `continue` of add of milestone
* run php releases.php & check output

### Config

Note: The milestones are in following format: X.Y.Z and an optional suffix (one of: `-current`, `-next`, `-current-maintenance`, `-next-maintenance`)


Open the file `config.json` and edit following values:

* `org`: this is the organisation or user that holds all the repos
* `repos`: a list of repos that should be updated at once
* `dueDates`: a list of key value pairs with a milestone as key and a date that then will be set as due date
* `renameMilestones`: a list of key value pairs with the old milestone name as key and the new name as value
* `addMilestones`: a list of milestones that should be added
* `versionAdded`: a list of key value pairs with the repo as key and a version number as value. The milestones are only applied (add/rename/delete) if the version of the milestone is bigger or equal then the specified version number.
