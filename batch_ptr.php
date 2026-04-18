<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'PDNSClient.php';

// Initialize Clients
$clients = [];
foreach ($servers as $server) {
    $key = $server['api_key'] ?? $server['key'] ?? '';
    if ($key) {
        $clients[$server['name']] = new PDNSClient($server['host'], $server['port'], $key);
    }
}
$primary = reset($clients);
$zones = $primary->getZones()['body'];

$msg = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $zoneId = $_POST['zone_id'];
    $subnet = $_POST['subnet']; // e.g., 103.170.13
    $domain = $_POST['domain']; // e.g., sriboga-smg.co.id
    $start = (int)$_POST['start'];
    $end = (int)$_POST['end'];

    $count = 0;
    foreach ($clients as $client) {
        for ($i = $start; $i <= $end; $i++) {
            // Logic for 103.170.13.x
            // PTR Name: x.13.170.103.in-addr.arpa (Constructed from Zone)
            // But user selects Zone ID.

            // Allow user to specify pattern
            // For now, simple assumption: Zone is reverse zone.
            // Name: $i
            // Content: subnet-replaced-dash-$i.domain

            $ptrName = "$i"; // Relative to zone
            $parts = explode('.', $subnet);
            $dashSubnet = implode('-', $parts);
            $ptrContent = "$dashSubnet-$i.$domain";

            if (substr($ptrContent, -1) !== '.') $ptrContent .= '.';

            $client->addRecord($zoneId, $ptrName, 'PTR', $ptrContent);
        }
    }
    $msg = "Batch operation sent to cluster for range $start-$end.";
}

$title = "Batch PTR Generator";
require_once 'includes/header.php';
?>

<div class="container py-4">
    <?php if ($msg): ?><div class="alert alert-success"><?= $msg ?></div><?php endif; ?>

    <div class="card shadow-sm border-0">
        <div class="card-header border-0 bg-transparent pt-4 pb-2">
            <h5 class="mb-0 fw-semibold text-dark"><i class="bi bi-arrow-repeat text-primary me-2"></i> Batch PTR Generator</h5>
        </div>
        <div class="card-body">
            <form method="POST">
                <div class="mb-3">
                    <label>Select Reverse Zone</label>
                    <select name="zone_id" class="form-select">
                        <?php foreach ($zones as $z): ?>
                            <option value="<?= htmlspecialchars($z['id']) ?>"><?= htmlspecialchars($z['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="mb-3">
                    <label>Subnet (e.g., 103.170.13)</label>
                    <input type="text" name="subnet" class="form-control" value="103.170.13">
                </div>
                <div class="mb-3">
                    <label>Target Domain (e.g., sriboga-smg.co.id)</label>
                    <input type="text" name="domain" class="form-control" value="sriboga-smg.co.id">
                </div>
                <div class="row">
                    <div class="col">
                        <label>Start IP</label>
                        <input type="number" name="start" class="form-control" value="1">
                    </div>
                    <div class="col">
                        <label>End IP</label>
                        <input type="number" name="end" class="form-control" value="254">
                    </div>
                </div>
                <button type="submit" class="btn btn-primary mt-3">Generate & Push to Cluster</button>
            </form>
        </div>
    </div>

</div>

<?php require_once 'includes/footer.php'; ?>