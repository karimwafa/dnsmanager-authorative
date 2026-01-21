<?php
require_once 'auth.php';
require_once 'db.php';
require_once 'PDNSClient.php';

// --- KEY GENERATION LOGIC ---

$newKeyInfo = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'generate') {
        $label = trim($_POST['label']);
        $description = trim($_POST['description']);
        $applyLocal = isset($_POST['apply_local']) && $_POST['apply_local'] === '1';

        try {
            $generatedKey = bin2hex(random_bytes(16));
        } catch (Exception $e) {
            $generatedKey = md5(uniqid(rand(), true));
        }

        $stmt = $pdo->prepare("INSERT INTO api_keys (label, description, key_string) VALUES (?, ?, ?)");
        $stmt->execute([$label, $description, encryptData($generatedKey)]);

        $applyResult = null;

        // Apply to local PowerDNS if checkbox is checked
        if ($applyLocal) {
            $configContent = "api=yes\napi-key={$generatedKey}\nwebserver=yes\nwebserver-address=0.0.0.0\nwebserver-allow-from=127.0.0.1,::1,10.0.0.0/8,192.168.0.0/16,172.16.0.0/12\nwebserver-port=8081\n";

            // Write config using sudo tee
            $writeCmd = "echo " . escapeshellarg($configContent) . " | sudo /usr/bin/tee /etc/powerdns/pdns.d/api.conf > /dev/null 2>&1";
            exec($writeCmd, $output, $writeResult);

            if ($writeResult === 0) {
                // Restart PowerDNS
                exec("sudo /bin/systemctl restart pdns 2>&1", $restartOutput, $restartResult);

                if ($restartResult === 0) {
                    // Update Primary Server (Local) in cluster_servers if exists
                    $encryptedKey = encryptData($generatedKey);
                    $stmt = $pdo->prepare("UPDATE cluster_servers SET api_key = ? WHERE host = '127.0.0.1' OR name LIKE '%Local%'");
                    $stmt->execute([$encryptedKey]);

                    $applyResult = 'success';
                } else {
                    $applyResult = 'restart_failed';
                }
            } else {
                $applyResult = 'write_failed';
            }
        }

        // Show success modal
        $newKeyInfo = [
            'label' => $label,
            'key' => $generatedKey,
            'applied' => $applyLocal,
            'applyResult' => $applyResult
        ];
    }

    if ($action === 'delete') {
        $idToDelete = $_POST['id'];
        $stmt = $pdo->prepare("DELETE FROM api_keys WHERE id = ?");
        $stmt->execute([$idToDelete]);
        header("Location: key_generator.php");
        exit;
    }
}

// Fetch all keys
$stmt = $pdo->query("SELECT * FROM api_keys ORDER BY created_at DESC");
$keys = $stmt->fetchAll();

$title = "API Key Manager";
require_once 'includes/header.php';
?>

<div class="container py-4">
    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h5 class="mb-0">Generated Keys History</h5>
            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#generateModal">
                + Generate New Key
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive-cards">
                <table class="table table-striped table-hover mb-0" style="table-layout: fixed;">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 20%;">Label</th>
                            <th style="width: 25%;">Description</th>
                            <th style="width: 20%;">API Key</th>
                            <th style="width: 15%;">Created At</th>
                            <th style="width: 20%; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if (empty($keys)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">No keys generated yet.</td>
                            </tr>
                        <?php else: ?>
                            <?php foreach ($keys as $k): ?>
                                <tr>
                                    <td data-label="Label" class="fw-bold"><?= htmlspecialchars($k['label']) ?></td>
                                    <td data-label="Description"><?= htmlspecialchars($k['description']) ?></td>
                                    <?php $displayKey = decryptData($k['key_string']); ?>
                                    <td data-label="API Key" class="font-monospace text-muted"><?= htmlspecialchars(substr($displayKey, 0, 10)) ?>...</td>
                                    <td data-label="Created At"><small><?= htmlspecialchars($k['created_at']) ?></small></td>
                                    <td data-label="Actions" class="text-end">
                                        <div class="btn-group btn-group-sm">
                                            <button class="btn btn-outline-primary copy-btn" data-key="<?= htmlspecialchars($displayKey, ENT_QUOTES, 'UTF-8') ?>">Copy</button>
                                            <form method="POST" class="d-inline" onsubmit="return confirm('Delete this key record?');">
                                                <input type="hidden" name="action" value="delete">
                                                <input type="hidden" name="id" value="<?= $k['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" style="border-top-left-radius: 0; border-bottom-left-radius: 0;">Delete</button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Generate Modal -->
<div class="modal fade" id="generateModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Generate New API Key</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST">
                <div class="modal-body">
                    <input type="hidden" name="action" value="generate">
                    <div class="mb-3">
                        <label class="form-label">Server Label</label>
                        <input type="text" name="label" class="form-control" placeholder="e.g. Server Jakarta" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description (Optional)</label>
                        <input type="text" name="description" class="form-control" placeholder="For backup node...">
                    </div>
                    <div class="form-check mb-2">
                        <input class="form-check-input" type="checkbox" name="apply_local" value="1" id="applyLocalCheck" checked>
                        <label class="form-check-label" for="applyLocalCheck">
                            <strong>Apply to Local PowerDNS</strong>
                        </label>
                        <div class="form-text">Update local PowerDNS config and restart service automatically</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Generate</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Success Modal -->
<?php if ($newKeyInfo): ?>
    <div class="modal fade" id="successModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title">✅ Key Generated Successfully!</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>API Key for <strong><?= htmlspecialchars($newKeyInfo['label']) ?></strong> has been created.</p>

                    <div class="input-group mb-4">
                        <input type="text" class="form-control font-monospace fs-5" value="<?= htmlspecialchars($newKeyInfo['key'], ENT_QUOTES, 'UTF-8') ?>" id="newKeyDisplay" readonly>
                        <button class="btn btn-outline-secondary" id="copyNewKeyBtn">Copy</button>
                    </div>

                    <?php if (!empty($newKeyInfo['applied'])): ?>
                        <?php if ($newKeyInfo['applyResult'] === 'success'): ?>
                            <div class="alert alert-success">
                                <i class="bi bi-check-circle me-2"></i>
                                <strong>Applied to Local PowerDNS!</strong><br>
                                <small>Config updated and PowerDNS restarted. Primary Server now uses this key.</small>
                            </div>
                        <?php elseif ($newKeyInfo['applyResult'] === 'restart_failed'): ?>
                            <div class="alert alert-warning">
                                <i class="bi bi-exclamation-triangle me-2"></i>
                                <strong>Config updated but restart failed!</strong><br>
                                <small>Run: <code>sudo systemctl restart pdns</code></small>
                            </div>
                        <?php else: ?>
                            <div class="alert alert-danger">
                                <i class="bi bi-x-circle me-2"></i>
                                <strong>Failed to apply to local PowerDNS</strong><br>
                                <small>Please update manually.</small>
                            </div>
                        <?php endif; ?>
                    <?php else: ?>
                        <div class="alert alert-secondary">
                            <h6>Configuration Instructions for Target Server:</h6>
                            <p class="small mb-1">Edit <code>/etc/powerdns/pdns.d/api.conf</code>:</p>
                            <pre class="bg-white p-2 border rounded mb-2">api=yes
api-key=<?= $newKeyInfo['key'] ?>
webserver=yes
webserver-address=0.0.0.0
webserver-allow-from=127.0.0.1,::1,10.0.0.0/8</pre>
                            <small class="text-danger">* Don't forget to restart PowerDNS service after saving!</small>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-primary" data-bs-dismiss="modal">Done</button>
                </div>
            </div>
        </div>
    </div>
<?php endif; ?>

<script>
    function copyToClipboard(text, button) {
        if (navigator.clipboard && navigator.clipboard.writeText) {
            navigator.clipboard.writeText(text).then(function() {
                showCopySuccess(button);
            }, function(err) {
                fallbackCopy(text, button);
            });
        } else {
            fallbackCopy(text, button);
        }
    }

    function fallbackCopy(text, button) {
        var textArea = document.createElement('textarea');
        textArea.value = text;
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        textArea.style.top = '-999999px';
        document.body.appendChild(textArea);
        textArea.focus();
        textArea.select();
        try {
            document.execCommand('copy');
            showCopySuccess(button);
        } catch (err) {
            alert('Failed to copy. Please copy manually.');
        }
        document.body.removeChild(textArea);
    }

    function showCopySuccess(button) {
        if (button) {
            var originalText = button.textContent;
            button.textContent = 'Copied!';
            button.classList.remove('btn-outline-primary', 'btn-outline-secondary');
            button.classList.add('btn-success');
            setTimeout(function() {
                button.textContent = originalText;
                button.classList.remove('btn-success');
                button.classList.add('btn-outline-primary');
            }, 2000);
        }
    }

    // Handle copy buttons in table
    document.querySelectorAll('.copy-btn').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var key = this.getAttribute('data-key');
            copyToClipboard(key, this);
        });
    });

    <?php if ($newKeyInfo): ?>
        // Show success modal after page load
        document.addEventListener('DOMContentLoaded', function() {
            var successModal = new bootstrap.Modal(document.getElementById('successModal'));
            successModal.show();

            // Handle copy button in success modal
            document.getElementById('copyNewKeyBtn').addEventListener('click', function() {
                var key = document.getElementById('newKeyDisplay').value;
                copyToClipboard(key, this);
            });
        });
    <?php endif; ?>
</script>

<?php require_once 'includes/footer.php'; ?>