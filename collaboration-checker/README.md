# collaboration-checker

```bash
php check.php [--verbose] github_user
```

Tests whether the provider user has collaborative access to the repos in the organizations `nextcloud`, `nextcloud-release` and `nextcloud-gmbh`. 

The result in JSON format shows the repos to which the user has access to, with the permission level and role name. Repos are not listed when:
1. The user lacks permissions
2. The user has read permissions on repos of public organizations
3. The user has simple write permissions on repos of `nextcloud`
