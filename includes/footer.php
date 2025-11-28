</main> <!-- Fechando a tag main do header -->
<?php if (!defined('BASE_URL')) require_once __DIR__ . '/config.php'; ?>
</div> <!-- Fechando a tag d-flex do header -->

<!-- Bootstrap JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js" integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz" crossorigin="anonymous"></script>

<!-- Custom JS para o sidebar -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const sidebarCollapse = document.getElementById('sidebarCollapse');
    const sidebar = document.querySelector('.sidebar');

    if (sidebarCollapse) {
        sidebarCollapse.addEventListener('click', function () {
            sidebar.classList.toggle('active');
        });
    }
});
</script>

</body>
</html>