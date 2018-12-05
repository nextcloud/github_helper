# Github helper scripts

This is a little collection of useful helper scripts.

## credentials.json

All the scripts inside this repo need a `credentials.json` in their home folder (e.g. `brancher/` ). The content should be a JSON object with username (your github account name) and apikey (generate one in [Settings > Developer settings > Personal access tokens](https://github.com/settings/tokens)), e.g.:
```json
{
  "username": "nickvergessen",
  "apikey": "1234567890abcdef1234567890abcdef12345678"
}
```

## Changelog generator

* provide the credentials by placing a copy of `credentials.dist.json` as `credentials.json` in the root of this folder
* go into `changelog/`
* install [composer](https://getcomposer.org/download/) dependencies: `composer install`

### Generate changelog command

Syntax:

 ```
php index.php $repoName generate:changelog $base $head --format=$format
 ```

The format setting has the following options:
 * Github (default)
 * forum (`--format=forum` - Markdown with absolute links)
 * changelog page on our website (`--format=html` - HTML list with absolute links)

E.g. to generate the changelog for the upcoming 13.0.1 server release. This script automatically derives the milestone from the first argument "v13.0.0" will mean that the milestone "13.0.1" will be checked for pending pull requests:

```
php index.php server generate:changelog v13.0.0 stable13
```

E.g. generate the changelog for the upcoming 3.3.0 Android release:

```
php index.php android generate:changelog stable-3.3.0 stable-3.3.x
```


