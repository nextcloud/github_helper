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
	echo "Using Microsoft GitHub CLI executable $GITHUB_CLI"
fi

APP_ID=$1
if [ "$APP_ID" ]; then
	echo "Preparing app $APP_ID"
else
	echo "Missing app id" >&2
	exit 1
fi

ORIGINAL_DIR=$(pwd)
WORK_DIR=$(mktemp -d)
cd "${WORK_DIR}"

$GIT clone https://github.com/nextcloud-gmbh/$APP_ID.git

cd $APP_ID
$GIT remote set-url --push origin git@github.com:nextcloud-gmbh/$APP_ID.git

$GIT checkout --orphan gmbh-main

cat > README.md<<'EOF'
# $APP_ID

This repository is a clone of https://github.com/nextcloud/$APP_ID
This branch is empty and contains information related to this repo.

You can find the main and other stable branches here:
 - https://github.com/nextcloud-gmbh/$APP_ID/tree/main
 - https://github.com/nextcloud-gmbh/$APP_ID/branches

EOF
$GIT add README.md
$GIT commit -m "Init readme"
$GIT push origin gmbh-main

$GIT checkout -b activate-actions
mkdir -p .github/workflows/
cat > .github/workflows/blank.yml<<'EOF'
# This is a basic workflow to help you get started with Actions

name: CI

# Controls when the workflow will run
on:
  # Triggers the workflow on push or pull request events but only for the main2 branch
  push:
    branches: [ 'gmbh-main' ]
  pull_request:
    branches: [ 'gmbh-main' ]

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

if [ -x "$GITHUB_CLI" ]; then
  $GITHUB_CLI pr create --base gmbh-main --fill
  $GIT push --delete origin activate-actions
else
  xdg-open "https://github.com/nextcloud-gmbh/${APP_ID}/pull/new/activate-actions" 2>/dev/null
fi

cd "${ORIGINAL_DIR}"
rm -Rf "${WORK_DIR}"
