# Journal Blender

The Journal Blender is a Drupal 8 module that allows a group of people to share the burden of keeping up with new articles posted in scientific journals. Using a list of journal ISSN numbers, the system automatically polls the CrossRef system for new articles from those sources and assigns them randomly to the participants. Users can comment, vote for, and share articles with one another. Each week, the two articles with the most votes are "starred" so that they can be flagged for group discussion. Optional Slack integration allows notifications from the system to be posted to a Slack workspace.

![Screenshot](https://github.com/kncrabtree/blender/blob/master/doc/images/screenshot.png)

## Requirements

* Drupal 8.x
* JQuery

## Installation

1. Unpack into modules/custom/blender, and ensure permissions are correctly set.
2. Enable the module in the Adminstration>Extend menu.
3. The module adds the "Blender Active User" and "Blender Passive User" Roles. An "active" user will be regularly assigned articles, while a passive user will not. Passive users may still comment, vote, and send/receive article recommendations. Assign roles as desired to users through Drupal's People menu.
4. In addition, there are additional permissions "access blender" and "administer blender" that can be added to other existing roles. Users with the "administer blender" permission (adminstrators only by default) can add, edit, and disable journals in the system, and can configure Slack settings.
5. Add journals using the "Add Journals" task link. Each time the Drupal Cron job runs, active journals that have not been polled in the last day will be queued for querying.

## Slack Integration

To use with Slack, you must create a custom app for your workspace (if demand ever warrants, perhaps I can create a public Slack app for this). The app should have the "Bots" and "Permissions" features enabled, and the application scopes required are "bot", "chat:write:bot", and "im:write". Once your app is installed to your workspace, you will need its OAuth token and the bot user's token.

In the Drupal Configuration Administration Menu, you can configure Slack integration settings in the Blender>Slack Integration section. There you can toggle whether Slack support is enabled and provide the necessary authentication tokens. You can also select which channel messages will be posted to using the Slack Channel ID.

To allow recommendations to be sent to individual users, each user's Slack ID must be set. The Slack workspace administrator can download a CSV file with all user's IDs (typically a 9 character string starting with 'U'). Each user's Slack ID can be set in their profile.

## Usage

On each cron run, logs will be made showing which journals were queried, what API calls were made to CrossRef, and how many unique articles were added to the system. When an active user logs in and accesses the system using the "Journals" task link, they are sent to their inbox, which shows the articles assigned to them. A red bar on the left of an article indicates that it is new. The user can archive the article, bookmark it, comment on it, mark it unread, vote for it, or recommend it to the user. When Slack integration is enabled, comments and votes will be posted to the indicated Slack channel and recommendation notifications will be sent to the indicated user.

Each week, the system identifies the two articles with the most votes (minimum 2) to flag for discussion. These articles receive a star in the system. Articles that are more than 6 months old and that have not been bookmarked, commented on, or voted for are purged from the system.

## Questions/Comments

Please open an issue request through GitHub (https://github.com/kncrabtree/blender) if you have a feature request or find a bug.
