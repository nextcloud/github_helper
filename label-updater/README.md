## GitHub script to copy labels

### How to run it

```
$ composer install
$ cp credentials.dist.json credentials.json
$ # add your GitHub API token to credentials.json
$ # configure the behaviour - see config section below
$ php run.php [--init] <repo-name>
```

* `--init`: deletes all labels before creating/updating them

### Config

Open the file `config.json` and edit following values:

* `org`: this is the organisation or user that holds all the repos
* `master`: this is the repository which contains all labels that should be copied
* `repos`: a list of repos that should be updated at once
* `exclude`: a list of regex patterns that should not be copied
