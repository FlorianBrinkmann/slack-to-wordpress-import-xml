# slack-to-wordpress-import-xml
Converts a Slack export to a XML file which can be imported into WordPress.

## Usage

1. Place the `slack-to-wordpress-converter.php` in the Slack export directory.
2. Run the script via command line or GET params in the browser:
    1. Command line: `php slack-to-wordpress-converter.php start-date="01.01.2016" end-date="05.01.2016"`
    2. Call the `slack-to-wordpress-converter.php` in the browser and add start and/or end date with `slack-to-wordpress-converter.php?start-date="01.01.2016"&end-date="05.01.2016"

You can drop one or both date params to get:
- all messages from a start date until the last message (do not specify `end-date`).
- all messages up to a specific date from the first message of the export (do not specify `start-date`).
- all messages from the export (leave both params away).