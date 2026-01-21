    </main>

    <!-- Footer -->
    <footer class="text-center py-4 text-muted small">
        <div class="container">
            DNS Author Manager &copy; <?= date('Y') ?>
        </div>
    </footer>

    <!-- jQuery (Required for DataTables) -->
    <script src="https://code.jquery.com/jquery-3.7.0.min.js"></script>
    <!-- Bootstrap JS Bundle -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <!-- DataTables JS -->
    <script src="https://cdn.datatables.net/1.13.6/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.13.6/js/dataTables.bootstrap5.min.js"></script>
    <?php if (isset($extra_js)) echo $extra_js; ?>
    </body>

    </html>