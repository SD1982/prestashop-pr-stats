<?php
use Github\Client;
use Github\ResultPager;

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/mysql.php';

if (getenv('SECURITY_TOKEN') != SECURITY_TOKEN) {
//    die("Wrong token".PHP_EOL);
}

$mysql = new PDOWrapper();

$client = new Client();
$client->authenticate(GITHUB_TOKEN, null, Github\Client::AUTH_ACCESS_TOKEN);

$after = 'Y3Vyc29yOnYyOpK5MjAxOC0xMi0zMVQxMzoxNzo1MyswMTowMM4OZeJt';
$query = '
{
  repository(name: "PrestaShop", owner: "PrestaShop") {
    id
    pullRequests(labels: "QA ✔️", states: MERGED, orderBy: {field: CREATED_AT, direction: ASC}, first: 100, after: "'.$after.'") {
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

$prs_data = $client->api('graphql')->execute($query);

do {
    $prs = $prs_data['data']['repository']['pullRequests']['edges'];
    echo sprintf("Found %s PRs...%s", count($prs), PHP_EOL);
    foreach ($prs as $pr) {
        //does this PR already exists in database ?
        $sql = 'SELECT id FROM `pr` WHERE pr_id = :pr_id;';
        $pr_exists = $mysql->query($sql, ['pr_id' => $pr['node']['number']]);
        if (isset($pr_exists['id'])) {
            echo sprintf("PR %s found in database, skipping...%s", $pr['node']['number'], PHP_EOL);
        }
        //putting the cursor to iterate on the next results
        $after = $pr['cursor'];
        $pr_data = [
            'pr_id' => $pr['node']['number'],
            'name' => $pr['node']['title'],
            'created' => date('Y-m-d H:i:s', strtotime($pr['node']['createdAt'])),
            'merged' => date('Y-m-d H:i:s', strtotime($pr['node']['mergedAt'])),
        ];
        //inserting in DB
        $sql = 'INSERT INTO `pr` (`pr_id`, `name`, `created`, `merged`) 
    VALUES (:pr_id, :name, :created, :merged);';
        $mysql->query($sql, $pr_data);
        $pr_db_id = $mysql->lastInsertId();
        $pr_update_data = [];
        //setting up its state
        $gone_in_qa = false;
        $currently_in_qa = false;
        $number_of_times_in_qa = 0;
        $total_time_as_wfqa = 0;
        $labels = [];
        $events = $pr['node']['timelineItems']['nodes'];
        foreach ($events as $event) {
            $event_data = [
                'pr_id' => $pr_db_id,
                'date' => date('Y-m-d H:i:s', strtotime($event['createdAt'])),
            ];
            //is this label in the array of tracked labels ?
            if (!in_array($event['label']['name'], ['waiting for QA', 'QA ✔️']) && !in_array($event['label']['name'], $labels)) {
                $labels[] = $event['label']['name'];
            }
            if ($event['label']['name'] == 'waiting for QA') {
                if (!$gone_in_qa) {
                    //first time we're going in QA !
                    $gone_in_qa = true;
                    $pr_update_data['time_before_first_wfqa'] = strtotime($event['createdAt']) - strtotime($pr['node']['createdAt']);
                }
                if (!$currently_in_qa) {
                    //we were not in a QA process
                    $currently_in_qa = true;
                    $number_of_times_in_qa++;
                    $start_of_qa_time = strtotime($event['createdAt']);
                    $event_data['type'] = 'add';
                } else {
                    //we were IN a QA process
                    $currently_in_qa = false;
                    $total_time_as_wfqa += strtotime($event['createdAt']) - $start_of_qa_time;
                    $event_data['type'] = 'remove';
                }
                $sql = 'INSERT INTO `event` (`pr_id`, `date`, `type`) 
    VALUES (:pr_id, :date, :type); ';
                $mysql->query($sql, $event_data);
            }

        }
        $pr_update_data['times_of_wfqa_labelling'] = $number_of_times_in_qa;
        $pr_update_data['total_time_as_wfqa'] = $total_time_as_wfqa;
        $pr_update_data['id'] = $pr_db_id;
        $sql = 'UPDATE `pr` SET 
    `time_before_first_wfqa` = :time_before_first_wfqa,
    `times_of_wfqa_labelling` = :times_of_wfqa_labelling,
    `total_time_as_wfqa` = :total_time_as_wfqa
    WHERE id = :id;';
        $mysql->query($sql, $pr_update_data);

        //labels
        foreach($labels as $label) {
            $sql = 'INSERT INTO `label` (`pr_id`, `name`) VALUES (:pr_id, :name);';
            $mysql->query($sql, ['pr_id' => $pr_db_id, 'name' => $label]);
        }
    }
    $prs_data = $client->api('graphql')->execute($query);
} while(count($prs_data['data']['repository']['pullRequests']['edges']) > 0 && 0);