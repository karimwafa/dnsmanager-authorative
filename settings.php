<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'PDNSClient.php';

$title = "Cluster Settings";
require_once 'includes/header.php';

// Helper for status check
function checkStatus($server)
{
    // DB field is 'api_key', but let's support 'key' if alias not used
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
                                            <form action="actions.php" method="POST" class="d-inline" onsubmit="return confirm('Remove this server?');">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_server">
                                                <input type="hidden" name="id" value="<?= $server['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Remove">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </form>
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

            <div class="card shadow-sm">
                <div class="card-header"><i class="bi bi-plus-lg me-2"></i>Add a new server to the cluster</div>
                <div class="card-body">
                    <form action="actions.php" method="POST" class="row g-3 align-items-end">
                        <?php csrf_field(); ?>
                        <input type="hidden" name="action" value="add_server">
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label small text-muted">Server Name</label>
                            <input type="text" name="name" class="form-control" placeholder="e.g. Surabaya Node" required>
                        </div>
                        <div class="col-12 col-sm-6 col-lg-3">
                            <label class="form-label small text-muted">IP Address</label>
                            <input type="text" name="host" class="form-control" placeholder="103.x.x.x" required>
                        </div>
                        <div class="col-6 col-sm-4 col-lg-1">
                            <label class="form-label small text-muted">Port</label>
                            <input type="number" name="port" class="form-control" value="8081" required>
                        </div>
                        <div class="col-12 col-sm-8 col-lg-3">
                            <label class="form-label small text-muted">API Key</label>
                            <div class="input-group">
                                <input type="text" name="key" id="apiKeyInput" class="form-control" required>
                                <button type="button" class="btn btn-outline-secondary" onclick="generateKey()">Generate</button>
                            </div>
                        </div>
                        <div class="col-12 col-lg-2">
                            <button type="submit" class="btn btn-primary w-100">
                                <i class="bi bi-plus-lg me-1"></i>Add Server
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        </div>

        <script>
            function generateKey() {
                // Generate a random 32-character hex string
                const array = new Uint8Array(16);
                window.crypto.getRandomValues(array);
                const hex = Array.from(array, byte => byte.toString(16).padStart(2, '0')).join('');
                document.getElementById('apiKeyInput').value = hex;
            }
        </script>

        <?php require_once 'includes/footer.php'; ?>