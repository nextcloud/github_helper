#!/usr/bin/env bash
#

GIT=$(which "git")

if [ -x "$GIT" ]; then
	echo "Using git executable $GIT"
else
	echo "Could not find git executable $GIT" >&2
	exit 1
fi

GITHUB_CLI=$(which "gh")

if [ -x "$GITHUB_CLI" ]; then
	echo "Using GitHub CLI executable $GITHUB_CLI"
else
	echo "Could not find GitHub CLI executable $GITHUB_CLI" >&2
	exit 1
fi

APP_ID=$1
if [ "$APP_ID" ]; then
	echo "Preparing app $APP_ID"
else
	echo "Missing app id" >&2
	exit 1
fi

$GIT clone https://github.com/nextcloud-releases/$APP_ID.git

cd $APP_ID
$GIT remote set-url --push origin git@github.com:nextcloud-releases/$APP_ID.git

$GIT checkout --orphan main

cat > README.md<<'EOF'
Do not push any code to that repository.

# How to publish
1. On https://github.com/nextcloud, tag appropriate commit on the source repository
2. Push the new tag to this repository
3. Create release on this repository

# Automatic package and publish
1. Make sure you have the [necessary workflow](https://github.com/nextcloud/.github/blob/master/workflow-templates/appstore-build-publish.yml) on your https://github.com/nextcloud source repository
2. Make sure your tagged commit also have the workflow
3. Make sure this repository have the proper `APP_PRIVATE_KEY` secret set
4. Make the `nextcloud_release_service` user is a co-maintainer of your app on https://apps.nextcloud.com/
5. Make sure you have admin rights to this repository
EOF
$GIT add README.md
$GIT commit -m "Init readme"
$GIT push origin main

$GIT checkout -b activate-actions
mkdir -p .github/workflows/
cat > .github/workflows/blank.yml<<'EOF'
# This is a basic workflow to help you get started with Actions

name: CI

# Controls when the workflow will run
on:
  # Triggers the workflow on push or pull request events but only for the main2 branch
  push:
    branches: [ main ]
  pull_request:
    branches: [ main ]

  # Allows you to run this workflow manually from the Actions tab
  workflow_dispatch:

# A workflow run is made up of one or more jobs that can run sequentially or in parallel
jobs:
  # This workflow contains a single job called "build"
  build:
    # The type of runner that the job will run on
    runs-on: ubuntu-latest

    # Steps represent a sequence of tasks that will be executed as part of the job
    steps:
      # Checks-out your repository under $GITHUB_WORKSPACE, so your job can access it
      - uses: actions/checkout@v2

      # Runs a single command using the runners shell
      - name: Run a one-line script
        run: echo Hello, world!

      # Runs a set of commands using the runners shell
      - name: Run a multi-line script
        run: |
          echo Add other actions to build,
          echo test, and deploy your project.
EOF

$GIT add .github/workflows/blank.yml
$GIT commit -m "Activate github actions with a trick"
$GIT push --set-upstream origin $($GIT symbolic-ref --short HEAD)

$GITHUB_CLI pr create --base main --fill
$GIT push --delete origin activate-actions

