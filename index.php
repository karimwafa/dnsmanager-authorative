<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'PDNSClient.php';

// Initialize Clients
$clients = [];
foreach ($servers as $server) {
    // DB uses 'api_key', fallback to 'key' if legacy
    $key = $server['api_key'] ?? $server['key'] ?? '';
    if ($key) {
        $clients[$server['name']] = new PDNSClient($server['host'], $server['port'], $key);
    }
}

$zoneList = [];
$error = null;

// Fetch Zones from Primary
if (!empty($clients)) {
    $primaryClient = reset($clients); // Get first client
    try {
        $zones = $primaryClient->getZones();
        if (isset($zones['code']) && $zones['code'] != 200) {
            $error = "Failed to fetch zones from " . key($clients) . ": " . print_r($zones, true);
        } else {
            $zoneList = $zones['body'] ?? [];
        }
    } catch (Exception $e) {
        $error = "Connection error: " . $e->getMessage();
    }
} else {
    // If no servers configured
    $error = "No servers configured in the cluster. Please add a server in Settings.";
}

// Handle API Errors
if (isset($zones['code']) && $zones['code'] != 200) {
    $error = "Failed to fetch zones: " . print_r($zones, true);
} else {
    $zoneList = $zones['body'];
}
// START OF PAGE
$title = "DNS Author Manager";
require_once 'includes/header.php';
?>

<div class="container py-4">
    <!-- Header removed, using navbar -->

    <?php if (isset($error)): ?>
        <div class="alert alert-danger shadow-sm"><?= $error ?></div>
    <?php endif; ?>

    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header d-flex justify-content-between align-items-center border-0 bg-transparent pt-4 pb-2">
            <h5 class="mb-0 fw-semibold text-dark"><i class="bi bi-hdd-network text-primary me-2"></i> Managed Zones</h5>
            <div>
                <button class="btn btn-primary btn-sm shadow-sm" data-bs-toggle="modal" data-bs-target="#addZoneModal">
                    <i class="bi bi-plus-lg me-1"></i> New Zone
                </button>
            </div>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive-cards">
                <table class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width: 35%;">Zone Name</th>
                            <th style="width: 15%;">Type</th>
                            <th style="width: 20%;">Serial</th>
                            <th style="width: 15%;">Master IPs</th>
                            <th style="width: 15%; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (!empty($zoneList)): ?>
                            <?php foreach ($zoneList as $zone): ?>
                                <tr>
                                    <td data-label="Zone Name" class="fw-bold"><?= htmlspecialchars($zone['name']) ?></td>
                                    <td data-label="Type"><?= htmlspecialchars($zone['kind']) ?></td>
                                    <td data-label="Serial" class="font-monospace text-muted small"><?= htmlspecialchars($zone['serial']) ?></td>
                                    <td data-label="Master IPs">
                                        <?php if (!empty($zone['masters'])): ?>
                                            <?= implode(', ', $zone['masters']) ?>
                                        <?php else: ?>
                                            <span class="text-muted small">-</span>
                                        <?php endif; ?>
                                    </td>
                                    <td data-label="Actions" class="text-end">
                                        <div class="d-flex justify-content-end align-items-center gap-2">
                                            <a href="view_zone.php?zone=<?= urlencode($zone['id']) ?>" class="btn btn-sm btn-outline-primary" title="Manage Zone">
                                                <i class="bi bi-gear"></i> Manage
                                            </a>
                                            <form action="actions.php" method="POST" class="d-inline m-0" onsubmit="return confirm('Delete this zone from ALL servers?');">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_zone">
                                                <input type="hidden" name="zone" value="<?= htmlspecialchars($zone['id']) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Zone">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="5" class="text-center py-4 text-muted">No zones found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add Zone Modal -->
<div class="modal fade" id="addZoneModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="actions.php" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="create_zone">
                <div class="modal-header">
                    <h5 class="modal-title">Add New Zone</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Zone Name</label>
                        <input type="text" name="domain" class="form-control" required placeholder="example.com">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Nameservers</label>
                        <input type="text" name="nameservers" class="form-control" value="ns1.sriboga-smg.co.id,ns2.sriboga-smg.co.id">
                        <div class="form-text">Comma separated, include trailing dot if needed.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Zone</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>