<footer id="footer" class="bg-light mt-4 pt-4" role="contentinfo" style="border-top:1px solid #e6e6e6;">
    <div class="container">
        <div class="row mt-2">
            <div class="col-12 d-flex flex-column flex-md-row justify-content-between align-items-center gap-2 py-2">
                <div class="text-muted small fw-semibold text-center text-md-start">
                    • Versión <?= htmlspecialchars($empresa['version'] ?? '3.5') ?> — Hecho con ❤️ para <?= htmlspecialchars($empresa['nombre'] ?? 'Fundación Ambulancias Lentas') ?> por <span title="Juan Fernando Cepeda Gorrón">&#91;el&#93;</span> &copy; <?= date('Y') ?> •
                </div>
                <?php
                    $logoVigilado = $empresa['logo_vigilado'] ?? (defined('LOGO_VIGILADO') ? LOGO_VIGILADO : null);
                    if (!empty($logoVigilado)):
                ?>
                    <img alt="Vigilado" class="img-fluid" src="<?= BASE_URL . htmlspecialchars($logoVigilado) ?>" style="max-height:44px;">
                <?php endif; ?>
            </div>
        </div>
    </div>
</footer>

<!-- El script de inicialización de Select2 fue movido a form-bundle.v1.js -->

</div> <!-- cierre del container-fluid -->
</body>
</html>
