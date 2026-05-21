# Creating a fork repository

## nextcloud-gmbh

1. 🛑 Do not **fork** the public repository ☣️
2. Create the repository: https://github.com/organizations/nextcloud-gmbh/repositories/new
    - Name: *Exact same name as on https://github.com/nextcloud/*
    - Visibility: `Private`
    - Template: `No template`
    - Add Readme: `Off`
    - Add .gitignore: `No .gitignore`
    - Add license: `None`
3. Run `./setup-gmbh-repository.sh <repo-name>`
4. Settings > Collaborators and teams:

    Grant `Role: maintainer` to the respective GitHub team or user

## nextcloud-releases

1. Create the repository: https://github.com/organizations/nextcloud-releases/repositories/new
    - Name: *Exact same name as on https://github.com/nextcloud/*
    - Visibility: `Public`
    - Template: `No template`
    - Add Readme: `Off`
    - Add .gitignore: `No .gitignore`
    - Add license: `None`
2. Run `./setup-release-repository.sh <repo-name>`
3. Settings > Collaborators and teams:

    Grant `Role: maintainer` to the respective GitHub team or user

4. Settings > Secrets and variables > Actions:

    Create a new **Repository secrets** named `APP_PRIVATE_KEY` with the apps private key or ask a team member with admin permissions to do that

5. Add the `nextcloud_release_service` user as co-maintainer of the app on https://apps.nextcloud.com/
