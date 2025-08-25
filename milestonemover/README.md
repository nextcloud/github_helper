# GitHub Milestone Issue Mover

Bulk move GitHub issues/PRs between milestones across multiple repositories.

## Features

- 🔄 Bulk migration across multiple repos
- 🛡️ Automatic rate limit handling
- 📋 Resume interrupted runs
- 🧪 Dry run mode
- 💾 Progress tracking

## Usage

```bash
# Basic usage
php run.php "Source Milestone" "Destination Milestone"

# Options
--dry-run    Preview changes without applying
--resume     Continue from previous interrupted run
--help       Show usage info
```

## Examples

```bash
# Move issues
php run.php "Nextcloud 25" "Nextcloud 26"

# Preview first
php run.php "Nextcloud 25.0.4" "Nextcloud 25.0.5" --dry-run

# Resume interrupted run
php run.php "Nextcloud 25.0.4" "Nextcloud 25.0.5" --resume
```

## Requirements

- GitHub token with `repo` and `issues` permissions (set in `credentials.json`)
- Both milestones must already exist in target repositories

## How it Works

1. Finds source/destination milestones in each repo
2. Gets all issues assigned to source milestone
3. Updates each issue to destination milestone
4. Saves progress every 5 issues for resumption
5. Handles rate limits automatically (5000 requests/hour)
