<script>
    $(document).ready(function() {
        $('#recordsTable').DataTable({
            "order": [[0, "asc"]], // Default sort by Name
            "pageLength": 10,
            "language": {
                "search": "Filter records:",
                "lengthMenu": "Show _MENU_ records per page",
                "info": "Showing _START_ to _END_ of _TOTAL_ records"
            }
        });

    // Existing Modal Script
    var editModal = document.getElementById('editModal');
    if (editModal) {
        editModal.addEventListener('show.bs.modal', function (event) {
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
