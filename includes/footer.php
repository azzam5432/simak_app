        <footer style="margin-top: 30px; padding: 20px 0; border-top: 1px solid #e9ecef; text-align: center; color: #7f8c8d; font-size: 13px;">
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

<script src="/simak_app/public/assets/js/app.js?v=2"></script>

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