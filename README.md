# prestashop-pr-stats

This tool gather all data from the PrestaShop/PrestaShop repository about Pull Requests.
Only **merged** Pull Requests with the label "QA ✔" are counted.

It then calculate some stats using the events timeline associated with each PR:
* duration before a PR is labeled "Waiting for QA"
* number of times a PR is labeled "Waiting for QA" (which represents the number of back-and-forth the PR had to have)
* total time spent in "Waiting for QA"

### Hot to install
Use composer install to install all the dependencies.

Create a database using the `schema.sql` file at the root of the project.

Copy the config.php.dist file:

* change the values inside for your correct MySQL values
* add your Github token
* add a security token (any passphrase will do)

and rename it config.php.

### How to use
Use the `generate.php` file to insert data into your database. 

:warning: you must declare an environment variable `SECURITY_TOKEN` identical to the one you set up above. 

```
SECURITY_TOKEN=my_token php generate.php
```

The script will gather all PR merged since the last one, using Github API cursors stored in the database.

Then use the `index.php` file to browse the data.
