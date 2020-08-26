<?php

require_once __DIR__ . '/mysql.php';
$mysql = new PDOWrapper();

//dates
$sql = 'SELECT 
    DATE(min(merged)) min_merged,
    DATE(max(merged)) max_merged
    FROM pr;';
$db_dates = $mysql->query($sql);
$start_date = $db_dates['min_merged'];
$end_date = $db_dates['max_merged'];

if (isset($_GET['start_date']) && isset($_GET['end_date'])) {
    $start_date = date('Y-m-d', strtotime($_GET['start_date']));
    $end_date = date('Y-m-d', strtotime($_GET['end_date']));
}

//get the data
$sql = "SELECT YEARWEEK(merged, 3) week,
STR_TO_DATE(CONCAT(YEARWEEK(merged, 3),' Monday'), '%x%v %W') weekday,
AVG(time_before_first_wfqa) avg_time_before_first_wfqa,
AVG(times_of_wfqa_labelling) avg_times_of_wfqa_labelling,
AVG(total_time_as_wfqa) avg_total_time_as_wfqa
FROM `pr` 
WHERE 1=1
AND merged BETWEEN :start_date AND :end_date
group by YEARWEEK(merged, 3)
order by week ASC;";
$data = [
        'start_date' => $start_date,
        'end_date' => $end_date,
];

$data_results = $mysql->query($sql, $data);
$js_labels = [];
$avg_time_before_first_wfqa = [];
$avg_times_of_wfqa_labelling = [];
$avg_total_time_as_wfqa = [];
foreach($data_results as $data_result) {
    $js_labels[] = $data_result['weekday'];
    $avg_time_before_first_wfqa[] = $data_result['avg_time_before_first_wfqa'];
    $avg_times_of_wfqa_labelling[] = $data_result['avg_times_of_wfqa_labelling'];
    $avg_total_time_as_wfqa[] = $data_result['avg_total_time_as_wfqa'];
}

$colors = [
    'rgb(55, 55, 150)',
    'rgb(50, 132, 184)',
    'rgb(41, 158, 72)',
    'rgb(158, 76, 41)',
    'rgb(158, 152, 41)',
    'rgb(41, 158, 49)',
    'rgb(129, 157, 199)',
];
?>
<!doctype html>
<html lang="en">
<head>
    <!-- Required meta tags -->
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">

    <!-- Bootstrap CSS -->
    <link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/css/bootstrap.min.css" integrity="sha384-JcKb8q3iqJ61gNV9KGb8thSsNjpSL0n8PARn9HuZOnIxN0hoP+VmmDGMN5t9UJ0Z" crossorigin="anonymous">

    <style>
        .container {
            background-color: #EEE;
            margin-top: 20px;
            padding-top: 10px;
            padding-bottom: 10px;
        }
    </style>

    <title>PrestaShop Issues Stats</title>
</head>
<body>
<div class="container">
    <h1>PrestaShop PRs Stats</h1>
    <form>
        <div class="form-row">
            <div class="col">
                <h4>PRs merge dates interval</h4>
            </div>
        </div>
        <div class="form-row">
            <div class="col">
                <input type="date" class="form-control" id="start_date_merged" name="start_date_merged" value="<?php echo $start_date; ?>" placeholder="Start Date">
            </div>
            <div class="col">
                <input type="date" class="form-control" id="end_date_merged" name="end_date_merged" value="<?php echo $end_date; ?>" placeholder="End Date">
            </div>
            <div class="col">
                <button type="submit" class="btn btn-primary">Display data</button>
            </div>
        </div>
        <div class="container-labels">
            <div class="form-row">
                <div class="col">
                    <h4>Types of labels</h4>
                </div>
            </div>


        </div>
    </form>
    <hr>
    <canvas id="bar_data"></canvas>
    <canvas id="pie_data"></canvas>
</div>
<script src="https://code.jquery.com/jquery-3.5.1.min.js" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/popper.js@1.16.1/dist/umd/popper.min.js" integrity="sha384-9/reFTGAW83EW2RDu2S0VKaIzap3H66lZH81PoYlFhbGU+6BZp6G7niu735Sk7lN" crossorigin="anonymous"></script>
<script src="https://stackpath.bootstrapcdn.com/bootstrap/4.5.2/js/bootstrap.min.js" integrity="sha384-B4gt1jrGC7Jh4AgTPSdUtOBvfO8shuf57BaghqFfPlYxofvL8/KUEfYiJOMMV+rV" crossorigin="anonymous"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@2.8.0"></script>
<script>
    const colors = ['rgb(55, 55, 150)',
        'rgb(50, 132, 184)',
        'rgb(41, 158, 72)',
        'rgb(158, 76, 41)',
        'rgb(158, 152, 41)',
        'rgb(41, 158, 49)',
        'rgb(129, 157, 199)',
        'rgb(55, 55, 150)',
        'rgb(50, 132, 184)',
        'rgb(41, 158, 72)',
        'rgb(158, 76, 41)',
        'rgb(158, 152, 41)',
        'rgb(41, 158, 49)',
        'rgb(129, 157, 199)'];
    let config_bar = {
        type: 'bar',
        data: {
            datasets: [{
                data: <?php echo json_encode($avg_time_before_first_wfqa); ?>,
                backgroundColor: 'rgb(55, 55, 150)',
                label: 'Average time before first WFQA'
            },{
                data: <?php echo json_encode($avg_times_of_wfqa_labelling); ?>,
                backgroundColor: 'rgb(158, 76, 41)',
                label: 'Average number of times a PR is labelled WFQA'
            },{
                data: <?php echo json_encode($avg_total_time_as_wfqa); ?>,
                backgroundColor: 'rgb(41, 158, 72)',
                label: 'Average time in WFQA'
            }

            ],
            labels: <?php echo json_encode($js_labels); ?>
        },
        options: {
            title: {
                display: true,
                text: 'Various data about PRs'
            },
            responsive: true
        }
    };

    window.onload = function() {
      var ctx_bar = document.getElementById('bar_data').getContext('2d');
      window.myBar = new Chart(ctx_bar, config_bar);
    };
</script>
</body>
</html>
