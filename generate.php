<?php
use Github\Client;
use Github\ResultPager;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/mysql.php';

if (getenv('SECURITY_TOKEN') != SECURITY_TOKEN) {
    die("Wrong token".PHP_EOL);
}

$mysql = new PDOWrapper();

$client = new Client();
$client->authenticate(GITHUB_TOKEN, null, Github\Client::AUTH_ACCESS_TOKEN);

//default starting cursor (2019-01-01)
$after = 'Y3Vyc29yOnYyOpK5MjAxOC0xMi0zMVQxMzoxNzo1MyswMTowMM4OZeJt';

$query = '
{
  repository(name: "PrestaShop", owner: "PrestaShop") {
    id
    pullRequests(labels: "QA ✔️", states: MERGED, orderBy: {field: CREATED_AT, direction: ASC}, first: 100, after: "%AFTER%") {
      edges {
        cursor
        node {
          id
          title
          number
          createdAt
          mergedAt
          timelineItems(itemTypes: [LABELED_EVENT, UNLABELED_EVENT], last: 100) {
            nodes {
              ... on UnlabeledEvent {
                createdAt
                label {
                  id
                  name
                }
              }
              ... on LabeledEvent {
                createdAt
                label {
                  id
                  name
                }
              }
            }
          }
        }
      }
    }
  }
}';

echo sprintf("---%s%s", date('Y-m-d H:i:s'), PHP_EOL);

$prs_data = $client->api('graphql')->execute(str_replace('%AFTER%', $after, $query));

while(count($prs_data['data']['repository']['pullRequests']['edges']) > 0) {
    $prs = $prs_data['data']['repository']['pullRequests']['edges'];
    echo sprintf("Found %s PRs...%s", count($prs), PHP_EOL);
    foreach ($prs as $pr) {
        $after = $pr['cursor'];
        //does this PR already exists in database ?
        $sql = 'SELECT id FROM `pr` WHERE pr_id = :pr_id;';
        $pr_exists = $mysql->query($sql, ['pr_id' => $pr['node']['number']]);
        if (isset($pr_exists['id'])) {
            continue;
        }

        $pr_update_data = [];
        //setting up its state
        $gone_in_qa = false;
        $currently_in_qa = false;
        $number_of_times_in_qa = 0;
        $time_before_first_wfqa = 0;
        $total_time_as_wfqa = 0;
        $labels = [];
        $events = $pr['node']['timelineItems']['nodes'];
        foreach ($events as $event) {
            //is this label in the array of tracked labels ?
            if (!in_array($event['label']['name'], ['waiting for QA', 'QA ✔️']) && !in_array($event['label']['name'], $labels)) {
                $labels[] = $event['label']['name'];
            }
            if ($event['label']['name'] == 'waiting for QA') {
                if (!$gone_in_qa) {
                    //first time we're going in QA !
                    $gone_in_qa = true;
                    $time_before_first_wfqa = strtotime($event['createdAt']) - strtotime($pr['node']['createdAt']);
                }
                if (!$currently_in_qa) {
                    //we were not in a QA process
                    $currently_in_qa = true;
                    $number_of_times_in_qa++;
                    $start_of_qa_time = strtotime($event['createdAt']);
                } else {
                    //we were IN a QA process
                    $currently_in_qa = false;
                    $total_time_as_wfqa += strtotime($event['createdAt']) - $start_of_qa_time;
                }
            }
        }
        if ($currently_in_qa) {
            //oops, we merged without removing "waiting for QA"...
            $total_time_as_wfqa += strtotime($event['createdAt']) - $start_of_qa_time;
        }
        if ($gone_in_qa) {
            $pr_data = [
                'pr_id' => $pr['node']['number'],
                'name' => $pr['node']['title'],
                'cursor' => $after,
                'created' => date('Y-m-d H:i:s', strtotime($pr['node']['createdAt'])),
                'merged' => date('Y-m-d H:i:s', strtotime($pr['node']['mergedAt'])),
                'time_before_first_wfqa' => $time_before_first_wfqa,
                'times_of_wfqa_labelling' => $number_of_times_in_qa,
                'total_time_as_wfqa' => $total_time_as_wfqa,
            ];
            //inserting in DB
            $sql = 'INSERT INTO `pr` (`pr_id`, `name`, `gh_cursor`, `created`, `merged`,
            `time_before_first_wfqa`, `times_of_wfqa_labelling`,`total_time_as_wfqa`) 
    VALUES (:pr_id, :name, :cursor, :created, :merged, :time_before_first_wfqa, :times_of_wfqa_labelling, :total_time_as_wfqa);';
            $mysql->query($sql, $pr_data);
            $pr_db_id = $mysql->lastInsertId();
            //labels
            foreach($labels as $label) {
                $sql = 'INSERT INTO `label` (`pr_id`, `name`) VALUES (:pr_id, :name);';
                $mysql->query($sql, ['pr_id' => $pr_db_id, 'name' => $label]);
            }
        }
    }
    //relaunch the query with the next batch
    $prs_data = $client->api('graphql')->execute(str_replace('%AFTER%', $after, $query));
};
