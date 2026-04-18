<?php
require_once 'auth.php';
require_once 'config.php';
require_once 'PDNSClient.php';

// Check Zone ID
$zoneId = $_GET['zone'] ?? '';
if (!$zoneId) die("Zone ID required.");

// Initialize Clients
$clients = [];
foreach ($servers as $server) {
    // DB uses 'api_key', fallback to 'key' if legacy
    $key = $server['api_key'] ?? $server['key'] ?? '';
    if ($key) {
        $clients[$server['name']] = new PDNSClient($server['host'], $server['port'], $server['key'] ?? $key);
    }
}
$primaryClient = reset($clients);

// Fetch Zone Details
$zoneRes = $primaryClient->request('GET', "zones/$zoneId");
if ($zoneRes['code'] != 200) die("Failed to load zone.");
$zone = $zoneRes['body'];

$title = "Manage Zone: " . $zone['name'];
require_once 'includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex justify-content-between align-items-center mb-4">
        <h3><i class="bi bi-globe me-2"></i><?= htmlspecialchars($zone['name']) ?></h3>
        <!-- Navigation handled by Navbar, but keeping context buttons -->
    </div>

    <div class="card mb-4 border-0 shadow-sm">
        <div class="card-header border-0 bg-transparent pt-4 pb-0">
            <h6 class="mb-0 fw-semibold text-primary"><i class="bi bi-plus-lg me-2"></i> Add New Record</h6>
        </div>
        <div class="card-body pt-3">
            <form action="actions.php" method="POST" class="row g-3">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="add_record">
                <input type="hidden" name="zone_id" value="<?= htmlspecialchars($zone['id']) ?>">

                <div class="col-md-3">
                    <label>Name</label>
                    <input type="text" name="name" class="form-control" placeholder="@ or subdomain" required>
                </div>
                <div class="col-md-2">
                    <label>Type</label>
                    <select name="type" class="form-select">
                        <option value="A">A</option>
                        <option value="AAAA">AAAA</option>
                        <option value="CNAME">CNAME</option>
                        <option value="TXT">TXT</option>
                        <option value="MX">MX</option>
                        <option value="NS">NS</option>
                        <option value="PTR">PTR</option>
                    </select>
                </div>
                <div class="col-md-5">
                    <label>Content</label>
                    <input type="text" name="content" class="form-control" placeholder="1.2.3.4" required>
                </div>
                <div class="col-md-2 d-flex align-items-end">
                    <button type="submit" class="btn btn-primary w-100">Add Record</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card shadow-sm border-0">
        <div class="card-header border-0 bg-transparent pt-4 pb-2">
            <h5 class="mb-0 fw-semibold text-dark"><i class="bi bi-card-list text-primary me-2"></i> Records</h5>
        </div>
        <div class="card-body px-0 pt-0">
            <div class="table-responsive-cards px-0">
                <table id="recordsTable" class="table table-hover mb-0 align-middle">
                    <thead>
                        <tr>
                            <th style="width: 20%;">Name</th>
                            <th style="width: 10%;">Type</th>
                            <th style="width: 10%;">TTL</th>
                            <th style="width: 45%;">Content</th>
                            <th style="width: 15%; text-align: right;" data-orderable="false">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($zone['rrsets'] as $rrset): ?>
                            <?php foreach ($rrset['records'] as $record): ?>
                                <tr>
                                    <td data-label="Name"><?= htmlspecialchars($rrset['name']) ?></td>
                                    <td data-label="Type"><span class="badge bg-secondary"><?= htmlspecialchars($rrset['type']) ?></span></td>
                                    <td data-label="TTL"><?= htmlspecialchars($rrset['ttl']) ?></td>
                                    <td data-label="Content" class="text-break"><?= htmlspecialchars($record['content']) ?></td>
                                    <td data-label="Actions" class="text-end">
                                        <div class="d-flex justify-content-end gap-2">
                                            <button class="btn btn-sm btn-outline-primary"
                                                data-bs-toggle="modal"
                                                data-bs-target="#editModal"
                                                data-name="<?= htmlspecialchars($rrset['name']) ?>"
                                                data-type="<?= htmlspecialchars($rrset['type']) ?>"
                                                data-ttl="<?= htmlspecialchars($rrset['ttl']) ?>"
                                                data-content="<?= htmlspecialchars($record['content']) ?>"
                                                title="Edit Record">
                                                <i class="bi bi-pencil-square"></i>
                                            </button>
                                            <form action="actions.php" method="POST" class="d-inline m-0" onsubmit="return confirm('Delete record <?= htmlspecialchars($rrset['name']) ?>?');">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_record">
                                                <input type="hidden" name="zone_id" value="<?= htmlspecialchars($zone['id']) ?>">
                                                <input type="hidden" name="name" value="<?= htmlspecialchars($rrset['name']) ?>">
                                                <input type="hidden" name="type" value="<?= htmlspecialchars($rrset['type']) ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger" title="Delete Record">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="actions.php" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="edit_record">
                <input type="hidden" name="zone_id" value="<?= htmlspecialchars($zone['id']) ?>">
                <input type="hidden" name="original_name" id="edit-original-name">
                <input type="hidden" name="original_type" id="edit-original-type">
                <input type="hidden" name="original_content" id="edit-original-content">

                <div class="modal-header">
                    <h5 class="modal-title">Edit Record</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label>Name</label>
                        <input type="text" name="name" id="edit-name" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>Type</label>
                        <select name="type" id="edit-type" class="form-select" readonly> <!-- Type change usually usually tricky, keep simplified -->
                            <option value="A">A</option>
                            <option value="AAAA">AAAA</option>
                            <option value="CNAME">CNAME</option>
                            <option value="TXT">TXT</option>
                            <option value="MX">MX</option>
                            <option value="NS">NS</option>
                            <option value="PTR">PTR</option>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label>Content</label>
                        <input type="text" name="content" id="edit-content" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label>TTL</label>
                        <input type="number" name="ttl" id="edit-ttl" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>
<script>
    $(document).ready(function() {
        // Initialize DataTables
        if ($.fn.DataTable) {
            $('#recordsTable').DataTable({
                "order": [
                    [0, "asc"]
                ], // Default sort by Name
                "pageLength": 10,
                "lengthMenu": [
                    [10, 25, 50, -1],
                    [10, 25, 50, "All"]
                ],
                "language": {
                    "search": "Filter records:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ entries",
                    "paginate": {
                        "first": "First",
                        "last": "Last",
                        "next": "Next",
                        "previous": "Previous"
                    }
                },
                "columnDefs": [{
                        "orderable": false,
                        "targets": 4
                    } // Disable sorting on Actions column (index 4)
                ]
            });
        } else {
            console.error("DataTables library not loaded!");
        }

        // Restoring Edit Modal Functionality
        var editModal = document.getElementById('editModal');
        if (editModal) {
            editModal.addEventListener('show.bs.modal', function(event) {
                var button = event.relatedTarget;
                var name = button.getAttribute('data-name');
                var type = button.getAttribute('data-type');
                var ttl = button.getAttribute('data-ttl');
                var content = button.getAttribute('data-content');

                document.getElementById('edit-original-name').value = name;
                document.getElementById('edit-original-type').value = type;
                document.getElementById('edit-original-content').value = content;

                document.getElementById('edit-name').value = name;
                document.getElementById('edit-type').value = type;
                document.getElementById('edit-ttl').value = ttl;
                document.getElementById('edit-content').value = content;
            });
        }
    });
</script>