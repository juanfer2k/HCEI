<footer class="text-center mt-4" id="footer" style="padding:15px; border-top:1px solid #ddd;">
    <p><?= htmlspecialchars($empresa['nombre']) ?> | 
    NIT: <?= htmlspecialchars($empresa['nit']) ?><br>
    <a href="https://<?= htmlspecialchars($empresa['web']) ?>"><i class="bi bi-globe2"></i> <?= htmlspecialchars($empresa['web']) ?></a></p>

    <p class="text-muted" style="font-size:0.9em;">
        Registro basado en estándares HL7-FHIR para la Historia Clínica Electrónica Interoperable en Colombia según la Ley 2015 de 2020.<br /><br />
        Versión <?= htmlspecialchars($empresa['version']) ?> | Copyright 
        <span title="Juan Fernando Cepeda Gorrón" aria-label="Juan Fernando Cepeda Gorrón">&#91;el&#93;</span> &copy; 
        <?php echo date("Y"); ?><br> Teléfono: <a href="tel:+573505427424" style="text-decoration:none; color:#0d6efd;">+57 350 542 7424</a>
    </p>
</footer>
