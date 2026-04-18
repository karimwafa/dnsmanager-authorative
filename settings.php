<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'PDNSClient.php';

    $title = "Cluster Settings";
    require_once 'includes/header.php';

    // Get current local API Key from config/pdns
    $local_pdns_key = '';
    $pdns_conf_paths = ['/etc/powerdns/pdns.conf', '/etc/powerdns/pdns.d/api.conf'];
    foreach ($pdns_conf_paths as $path) {
        if (file_exists($path)) {
            $conf = file_get_contents($path);
            if (preg_match('/api-key=(.*)/', $conf, $matches)) {
                $local_pdns_key = trim($matches[1]);
                break; // Found it
            }
        }
    }

    // Helper for status check
    function checkStatus($server)
    {
        $key = $server['api_key'] ?? $server['key'] ?? '';
    if (!isset($server['host'], $server['port']) || !$key) return false;
    $client = new PDNSClient($server['host'], $server['port'], $key);
    return $client->testConnection();
}
?>

<div class="container py-4">
    <div class="row">
        <div class="col-md-12">
            <div class="card mb-4">
                <div class="card-header bg-white d-flex align-items-center">
                    <h5 class="mb-0"><i class="bi bi-hdd-network me-2"></i>Cluster Nodes</h5>
                </div>
                <div class="card-body p-0">
                    <div class="table-responsive-cards">
                        <table class="table table-striped table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Hostname / Name</th>
                                    <th>IP Address</th>
                                    <th>Port</th>
                                    <th class="text-center">Status</th>
                                    <th>DNS Role</th>
                                    <th class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($servers as $index => $server): ?>
                                    <?php
                                    $isOnline = checkStatus($server);
                                    $role = $server['role'] ?? 'sync';
                                    ?>
                                    <tr>
                                        <td data-label="Server Name" class="fw-bold"><?= htmlspecialchars($server['name']) ?></td>
                                        <td data-label="IP Address">
                                            <span class="d-none d-lg-inline"><?= htmlspecialchars($server['host']) ?></span>
                                            <span class="d-lg-none"><?= htmlspecialchars($server['host']) ?>:<?= htmlspecialchars($server['port']) ?></span>
                                        </td>
                                        <td data-label="Port"><?= htmlspecialchars($server['port']) ?></td>
                                        <td data-label="Status" class="text-center">
                                            <?php if ($isOnline): ?>
                                                <span class="badge bg-success">Online</span>
                                            <?php else: ?>
                                                <span class="badge bg-danger">Offline</span>
                                            <?php endif; ?>
                                        </td>
                                        <td data-label="DNS Role">
                                            <form action="actions.php" method="POST" id="role-form-<?= $server['id'] ?>">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="update_role">
                                                <input type="hidden" name="id" value="<?= $server['id'] ?>">
                                                <select name="role" class="form-select form-select-sm" onchange="this.form.submit()" style="min-width: 160px;">
                                                    <option value="sync" <?= $role === 'sync' ? 'selected' : '' ?>>Synchronize Changes</option>
                                                    <option value="standalone" <?= $role === 'standalone' ? 'selected' : '' ?>>Standalone</option>
                                                </select>
                                            </form>
                                        </td>
                                        <td data-label="Actions" class="text-end">
                                            <div class="d-flex justify-content-end align-items-center">
                                                <form action="actions.php" method="POST" class="d-inline m-0" onsubmit="return confirm('Remove this server?');">
                                                    <?php csrf_field(); ?>
                                                    <input type="hidden" name="action" value="delete_server">
                                                    <input type="hidden" name="id" value="<?= $server['id'] ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                                        <i class="bi bi-trash"></i> Delete
                                                    </button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <div class="card-footer bg-white border-top-0">
                    <div class="alert alert-info py-2 mb-0 small">
                        <i class="bi bi-info-circle me-1"></i>
                        <strong>Synchronize Changes:</strong> Changes made here will propagate to this server.<br>
                        <strong>Standalone:</strong> No changes will be pushed to this server.
                    </div>
                </div>
            </div>

            <div class="card shadow-sm border-0">
                <div class="card-header border-0 bg-transparent pt-4 pb-0">
                    <h5 class="mb-0 fw-semibold text-dark"><i class="bi bi-plus-lg text-primary me-2"></i> Add Node to Cluster</h5>
                </div>
                <div class="card-body">
                    <form action="actions.php" method="POST" class="row g-3 align-items-end">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_server">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label small fw-medium text-muted">Server Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Surabaya Node" required>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label small fw-medium text-muted">IP Address</label>
                            <input type="text" name="host" class="form-control" placeholder="103.x.x.x" required>
                        </div>
                        <div class="col-6 col-md-2 col-lg-1">
                            <label class="form-label small fw-medium text-muted">Port</label>
                            <input type="number" name="port" class="form-control" value="8081" required>
                        </div>
                        <div class="col-12 col-sm-8 col-lg-3">
                            <label class="form-label small fw-medium text-muted">PowerDNS API Key <i class="bi bi-question-circle" title="Ambil dari /etc/powerdns/pdns.conf di server tujuan"></i></label>
                            <input type="password" name="key" class="form-control" placeholder="Daemon API Key" required>
                        </div>
                        <div class="col-12 col-lg-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-lg me-1"></i>Add Server
                            </button>
                        </div>
                    </form>
                </div>
                <?php if ($local_pdns_key): ?>
                <div class="card-footer bg-light border-0 py-3">
                    <div class="d-flex align-items-center justify-content-between">
                        <div class="small text-muted">
                            <i class="bi bi-shield-lock me-1"></i> <strong>Kunci API Server Ini:</strong> 
                            <code class="ms-1 bg-white px-2 py-1 rounded border"><?= $local_pdns_key ?></code>
                        </div>
                        <div class="small text-muted fst-italic">Copy ini ke server lawan jika ingin menghubungkan balik.</div>
                    </div>
                </div>
                <?php endif; ?>
            </div>
        </div>


        <?php require_once 'includes/footer.php'; ?>