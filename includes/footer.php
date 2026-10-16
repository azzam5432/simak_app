        <footer class="text-center text-muted small mt-5 pt-3" style="color: #7f8c8d;">
            <p>
                <i class="fas fa-copyright"></i> <?= date('Y') ?> SIMAK - Sistem Informasi & Manajemen Akademik
            </p>
            <p>
                <i class="fas fa-building"></i> Politeknik Mitra Industri | 
                <i class="fas fa-user"></i> Developed by Azzam Maulana Hamzah
            </p>
        </footer>
    </div>
</div>

<script src="/simak_app/public/assets/js/app.js?v=3"></script>

<?php 
$flash = getFlashMessage(); 
if ($flash): 
?>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            showToast('<?= addslashes($flash['message']) ?>', '<?= $flash['type'] ?>');
        });
    </script>
<?php endif; ?>

</body>
</html>