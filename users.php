<?php
$title = "User Management";
require_once 'includes/header.php';
require_once 'db.php';
require_once 'auth.php'; // Ensure user is logged in

// Fetch all users
try {
    $stmt = $pdo->query("SELECT id, username, role, created_at FROM app_users ORDER BY created_at DESC");
    $users = $stmt->fetchAll();
} catch (PDOException $e) {
    die("Database error: " . $e->getMessage()); // Simple error handling for now
}
?>

<div class="container py-4">
    <div class="card shadow-sm border-0">
        <div class="card-header border-0 bg-transparent d-flex justify-content-between align-items-center pt-4 pb-2">
            <h5 class="mb-0 fw-semibold text-dark"><i class="bi bi-people-fill text-primary me-2"></i> User Management</h5>
            <button class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addUserModal">
                <i class="bi bi-person-plus-fill me-1"></i> Add New User
            </button>
        </div>
        <div class="card-body p-0">
            <div class="table-responsive-cards">
                <table class="table table-hover mb-0 align-middle">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 10%;">ID</th>
                            <th style="width: 25%;">Username</th>
                            <th style="width: 20%;">Role</th>
                            <th style="width: 25%;">Created At</th>
                            <th style="width: 20%; text-align: right;">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td data-label="ID"><?= $user['id'] ?></td>
                                <td data-label="Username" class="fw-bold"><?= htmlspecialchars($user['username']) ?></td>
                                <td data-label="Role"><span class="badge bg-secondary"><?= htmlspecialchars($user['role']) ?></span></td>
                                <td data-label="Created At"><small class="text-muted"><?= $user['created_at'] ?></small></td>
                                <td data-label="Actions" class="text-end">
                                    <div class="d-flex justify-content-end align-items-center gap-2">
                                        <button class="btn btn-sm btn-outline-primary"
                                            data-bs-toggle="modal"
                                            data-bs-target="#editUserModal"
                                            data-id="<?= $user['id'] ?>"
                                            data-username="<?= htmlspecialchars($user['username']) ?>">
                                            <i class="bi bi-pencil"></i> Edit
                                        </button>
                                        <?php if ($user['username'] !== 'admin' && $user['username'] !== $_SESSION['username']): ?>
                                            <form action="actions.php" method="POST" class="d-inline m-0" onsubmit="return confirm('Delete user <?= htmlspecialchars($user['username']) ?>?');">
                                                <?php csrf_field(); ?>
                                                <input type="hidden" name="action" value="delete_user">
                                                <input type="hidden" name="id" value="<?= $user['id'] ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-danger">
                                                    <i class="bi bi-trash"></i> Delete
                                                </button>
                                            </form>
                                        <?php else: ?>
                                            <span class="text-muted small fst-italic">Protected</span>
                                        <?php endif; ?>
                                    </div>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>

<!-- Add User Modal -->
<div class="modal fade" id="addUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="actions.php" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="add_user">
                <div class="modal-header">
                    <h5 class="modal-title">Add New User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" name="username" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Password</label>
                        <input type="password" name="password" class="form-control" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm Password</label>
                        <input type="password" name="confirm_password" class="form-control" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create User</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit User Modal -->
<div class="modal fade" id="editUserModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <form action="actions.php" method="POST">
                <?php csrf_field(); ?>
                <input type="hidden" name="action" value="edit_user">
                <input type="hidden" name="id" id="edit-user-id">
                <div class="modal-header">
                    <h5 class="modal-title">Edit User</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Username</label>
                        <input type="text" id="edit-username" class="form-control" readonly disabled>
                        <div class="form-text">Username cannot be changed.</div>
                    </div>
                    <hr>
                    <h6 class="mb-3">Change Password <small class="text-muted fw-normal">(Leave blank to keep current)</small></h6>
                    <div class="mb-3">
                        <label class="form-label">New Password</label>
                        <input type="password" name="password" class="form-control">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Confirm New Password</label>
                        <input type="password" name="confirm_password" class="form-control">
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

<script>
    var editUserModal = document.getElementById('editUserModal');
    editUserModal.addEventListener('show.bs.modal', function(event) {
        var button = event.relatedTarget;
        var id = button.getAttribute('data-id');
        var username = button.getAttribute('data-username');

        var modalIdInput = editUserModal.querySelector('#edit-user-id');
        var modalUsernameInput = editUserModal.querySelector('#edit-username');

        modalIdInput.value = id;
        modalUsernameInput.value = username;
    });
</script>

<?php require_once 'includes/footer.php'; ?>