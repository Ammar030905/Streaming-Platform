    <?php if(isset($_SESSION['admin_id'])): ?>
        </div> <!-- End Main Content -->
    </div> <!-- End d-flex wrapper -->
    <?php else: ?>
    </main>
    <?php endif; ?>
    
    <script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script src="<?= asset_url('/assets/js/main.js') ?>"></script>
</body>
</html>
